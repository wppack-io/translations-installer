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
use Composer\Plugin\PluginInterface;

/**
 * Composer plugin that downloads translations from wordpress.org for every
 * WordPress package (core, plugins, themes) as it is installed or updated.
 * See TranslationsDownloader for the configuration reference.
 */
final class TranslationsInstaller implements EventSubscriberInterface, PluginInterface
{
    private TranslationsDownloader $downloader;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->downloader = new TranslationsDownloader($composer, $io);
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
            $this->downloader->update($operation->getPackage());
        }
    }

    public function onPostPackageUpdate(PackageEvent $event): void
    {
        $operation = $event->getOperation();
        if ($operation instanceof UpdateOperation) {
            $this->downloader->update($operation->getTargetPackage());
        }
    }

    public function deactivate(Composer $composer, IOInterface $io): void {}

    public function uninstall(Composer $composer, IOInterface $io): void {}
}
