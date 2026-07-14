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
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Util\HttpDownloader;

/**
 * Downloads translations for WordPress packages. Shared by the event hooks
 * (per-package on install/update) and the translations:* commands.
 *
 * Configured through `extra` in the root composer.json:
 *
 *     "extra": {
 *         "wordpress-translations": ["ja", "fr_FR"],
 *         "wordpress-translations-dir": "web/wp-content/languages"
 *     }
 */
final class TranslationsDownloader
{
    /** @var list<string> Locales to download (e.g. ja, fr_FR). */
    private array $languages;

    /** Full path to the languages target directory. */
    private string $wpLanguagesDir;

    private HttpDownloader $httpDownloader;

    public function __construct(Composer $composer, private readonly IOInterface $io)
    {
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

    /** Null unless the package is a WordPress core/plugin/theme. */
    public function translatableFor(PackageInterface $package): ?Translatable
    {
        $type = PackageType::tryFrom($package->getType());
        if ($type === null) {
            return null;
        }

        // The slug on wordpress.org is the package name without the vendor.
        $slug = explode('/', $package->getName(), 2)[1] ?? $package->getName();

        return new Translatable($type, $slug, $package->getVersion(), $this->languages, $this->wpLanguagesDir, $this->httpDownloader);
    }

    /**
     * Install missing or outdated translations for one package. No-op unless
     * the package is a WordPress core/plugin/theme. API and download failures
     * are reported and swallowed, so the surrounding Composer run never
     * fails because of translations.
     */
    public function update(PackageInterface $package): void
    {
        $translatable = $this->translatableFor($package);
        if ($translatable === null) {
            return;
        }

        $installed = [];
        $available = false;
        try {
            foreach ($translatable->status() as $status) {
                if ($status->state !== LocaleState::NotAvailable) {
                    $available = true;
                }
                if ($status->needsDownload()) {
                    $translatable->download($status);
                    $installed[] = $status->locale;
                }
            }
        } catch (\Exception $e) {
            $this->io->writeError(sprintf(
                '  - Skipped translations for <info>%s</info>: <error>%s</error>',
                $package->getName(),
                $e->getMessage(),
            ));

            return;
        }

        if (!$available) {
            $this->io->writeError(sprintf(
                '  - No translations of <info>%s</info> for the configured locales',
                $package->getName(),
            ));

            return;
        }

        if ($installed === []) {
            $this->io->writeError(sprintf(
                '  - Translations for <info>%s</info> are up to date',
                $package->getName(),
            ));

            return;
        }

        foreach ($installed as $locale) {
            $this->io->writeError(sprintf(
                '  - Installed <comment>%s</comment> translations for <info>%s</info>',
                $locale,
                $package->getName(),
            ));
        }
    }
}
