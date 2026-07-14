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

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Installs missing and outdated translations for every installed WordPress
 * package. Failures are reported per package and never fail the command,
 * matching the behaviour of the install/update hooks.
 */
final class TranslationsUpdateCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('translations:update')
            ->setDescription('Download missing and outdated wordpress.org translations for all installed WordPress packages');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $composer = $this->requireComposer();
        $downloader = new TranslationsDownloader($composer, $this->getIO());

        foreach ($composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages() as $package) {
            $downloader->update($package);
        }

        return 0;
    }
}
