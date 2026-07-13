<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\TranslationsInstaller;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Util\HttpDownloader;

/**
 * Composer plugin that downloads translations from wordpress.org for every
 * WordPress package (core, plugins, themes) as it is installed or updated.
 *
 * Configured through `extra` in the root composer.json:
 *
 *     "extra": {
 *         "wordpress-translations": ["ja", "fr_FR"],
 *         "wordpress-translations-dir": "web/wp-content/languages"
 *     }
 */
final class TranslationsInstaller implements EventSubscriberInterface, PluginInterface
{
    /** @var list<string> Locales to download (e.g. ja, fr_FR). */
    private array $languages = [];

    /** Full path to the languages target directory. */
    private string $wpLanguagesDir = '';

    private IOInterface $io;

    private HttpDownloader $httpDownloader;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->io = $io;
        $this->httpDownloader = $composer->getLoop()->getHttpDownloader();

        $extra = $composer->getPackage()->getExtra();

        $languages = $extra['wordpress-translations'] ?? null;
        if (!is_array($languages) || $languages === []) {
            throw new \RuntimeException("The 'wordpress-translations' extra key is missing or empty — add it to the root composer.json (see README)");
        }
        $this->languages = array_values(array_map(strval(...), $languages));

        $targetDir = $extra['wordpress-translations-dir'] ?? null;
        if (!is_string($targetDir) || $targetDir === '') {
            throw new \RuntimeException("The 'wordpress-translations-dir' extra key is missing or empty — add it to the root composer.json (see README)");
        }
        $vendorDir = (string) $composer->getConfig()->get('vendor-dir');
        $this->wpLanguagesDir = dirname($vendorDir) . '/' . $targetDir;
    }

    /** @return array<string, string> */
    public static function getSubscribedEvents(): array
    {
        return [
            'post-package-install' => 'onPostPackageInstall',
            'post-package-update' => 'onPostPackageUpdate',
        ];
    }

    public function onPostPackageInstall(PackageEvent $event): void
    {
        $operation = $event->getOperation();
        if ($operation instanceof InstallOperation) {
            $this->downloadTranslations($operation->getPackage());
        }
    }

    public function onPostPackageUpdate(PackageEvent $event): void
    {
        $operation = $event->getOperation();
        if ($operation instanceof UpdateOperation) {
            $this->downloadTranslations($operation->getTargetPackage());
        }
    }

    /**
     * No-op unless the package is a WordPress core/plugin/theme. API and
     * download failures are reported and swallowed, so the Composer run
     * itself never fails because of translations.
     */
    private function downloadTranslations(PackageInterface $package): void
    {
        $type = PackageType::tryFrom($package->getType());
        if ($type === null) {
            return;
        }

        // The slug on wordpress.org is the package name without the vendor.
        $slug = explode('/', $package->getName(), 2)[1] ?? $package->getName();

        try {
            $translatable = new Translatable($type, $slug, $package->getVersion(), $this->languages, $this->wpLanguagesDir, $this->httpDownloader);
            $results = $translatable->fetch();
        } catch (\Exception $e) {
            $this->io->writeError(sprintf(
                '  - Skipped translations for <info>%s</info>: <error>%s</error>',
                $package->getName(),
                $e->getMessage(),
            ));

            return;
        }

        if ($results === []) {
            $this->io->writeError(sprintf(
                '  - No translations of <info>%s</info> for the configured locales',
                $package->getName(),
            ));

            return;
        }

        foreach ($results as $language) {
            $this->io->writeError(sprintf(
                '  - Installed <comment>%s</comment> translations for <info>%s</info>',
                $language,
                $package->getName(),
            ));
        }
    }

    public function deactivate(Composer $composer, IOInterface $io): void {}

    public function uninstall(Composer $composer, IOInterface $io): void {}
}
