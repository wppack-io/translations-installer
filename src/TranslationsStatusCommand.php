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
 * Read-only report of the translation state for every installed WordPress
 * package and configured locale. Exits 1 when anything is missing or
 * outdated, so it doubles as a CI freshness check.
 */
final class TranslationsStatusCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('translations:status')
            ->setDescription('Show the freshness of wordpress.org translations for all installed WordPress packages');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $composer = $this->requireComposer();
        $io = $this->getIO();
        $downloader = new TranslationsDownloader($composer, $io);

        $needsUpdate = 0;
        foreach ($composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages() as $package) {
            $translatable = $downloader->translatableFor($package);
            if ($translatable === null) {
                continue;
            }

            try {
                $statuses = $translatable->status();
            } catch (\Exception $e) {
                $io->writeError(sprintf('<info>%s</info>: <error>%s</error>', $package->getName(), $e->getMessage()));

                continue;
            }

            $io->write(sprintf('<info>%s</info>', $package->getName()));
            foreach ($statuses as $status) {
                $io->write('  - ' . str_pad($status->locale, 7) . $this->describe($status));
                if ($status->needsDownload()) {
                    ++$needsUpdate;
                }
            }
        }

        if ($needsUpdate > 0) {
            $io->write(sprintf('%d translation(s) can be updated. Run `composer translations:update`.', $needsUpdate));

            return 1;
        }

        $io->write('All translations are up to date.');

        return 0;
    }

    private function describe(LocaleStatus $status): string
    {
        return match ($status->state) {
            LocaleState::UpToDate => sprintf('Up to date (%s)', self::format($status->localRevision)),
            LocaleState::Outdated => sprintf('<comment>Outdated</comment>   (local %s → remote %s)', self::format($status->localRevision), self::format($status->remoteUpdated)),
            LocaleState::Missing => '<comment>Missing</comment>',
            LocaleState::NotAvailable => 'Not available',
        };
    }

    private static function format(?\DateTimeImmutable $date): string
    {
        return $date === null ? 'unknown' : $date->format('Y-m-d H:i:s');
    }
}
