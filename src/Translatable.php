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

use Composer\Util\HttpDownloader;

/**
 * One translatable WordPress package: queries the wordpress.org translations
 * API for the wanted locales and unpacks the language ZIPs into the standard
 * languages directory layout.
 */
final class Translatable
{
    /**
     * @param string       $slug           the wordpress.org slug (e.g. 'query-monitor'); ignored for core
     * @param string       $version        passed verbatim to the API `version` query parameter
     * @param list<string> $languages      locales to download (e.g. ja, fr_FR)
     * @param string       $wpLanguagesDir full path to the languages target directory
     */
    public function __construct(
        private readonly PackageType $type,
        private readonly string $slug,
        private readonly string $version,
        private readonly array $languages,
        private readonly string $wpLanguagesDir,
        private readonly HttpDownloader $httpDownloader,
    ) {}

    /**
     * Freshness of every configured locale: queries the API once and
     * compares each locale's `updated` timestamp with the PO-Revision-Date
     * of the locally installed `.po` file.
     *
     * @return list<LocaleStatus> ordered as the locales were configured
     */
    public function status(): array
    {
        $available = $this->availableTranslations();

        $statuses = [];
        foreach ($this->languages as $locale) {
            if (!isset($available[$locale])) {
                $statuses[] = new LocaleStatus($locale, LocaleState::NotAvailable, null, null, null);

                continue;
            }

            $local = PoHeader::revisionDate($this->wpLanguagesDir . $this->type->poPath($this->slug, $locale));
            $remote = $available[$locale]['updated'];

            $statuses[] = new LocaleStatus(
                $locale,
                LocaleState::determine($local, $remote),
                $available[$locale]['package'],
                $remote,
                $local,
            );
        }

        return $statuses;
    }

    /** Download the language-pack ZIP for one locale and unpack it. */
    public function download(LocaleStatus $status): void
    {
        if ($status->packageUrl === null) {
            throw new \LogicException('No language pack is available for ' . $status->locale);
        }

        $this->installTranslation($status->packageUrl, $this->destPath());
    }

    /**
     * Language packs offered by the API for the wanted locales, keyed by
     * locale.
     *
     * @return array<string, array{package: string, updated: ?\DateTimeImmutable}>
     */
    private function availableTranslations(): array
    {
        $url = $this->type->apiUrl($this->slug, $this->version);
        $response = json_decode((string) $this->httpDownloader->get($url)->getBody());

        if (!is_object($response) || !isset($response->translations) || !is_array($response->translations)) {
            throw new \RuntimeException('Unexpected response from the wordpress.org translations API');
        }

        $translations = [];
        foreach ($response->translations as $translation) {
            if (in_array($translation->language, $this->languages, true)) {
                // The API offers one pack per locale for a given version;
                // keep the first should it ever send duplicates.
                $translations[(string) $translation->language] ??= [
                    'package' => (string) $translation->package,
                    'updated' => self::parseUpdated($translation->updated ?? null),
                ];
            }
        }

        return $translations;
    }

    /** The API sends `updated` as a GMT timestamp like "2026-05-17 09:14:31". */
    private static function parseUpdated(mixed $updated): ?\DateTimeImmutable
    {
        if (!is_string($updated) || $updated === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($updated, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }

    /** The destination directory for this package type, created if missing. */
    private function destPath(): string
    {
        $destPath = $this->wpLanguagesDir . $this->type->subDirectory();

        if (!is_dir($destPath) && !mkdir($destPath, 0775, true)) {
            throw new \RuntimeException('Failed to create directory at: ' . $destPath);
        }

        return $destPath;
    }

    /** Download a language-pack ZIP and unpack it into the destination directory. */
    private function installTranslation(string $packageUrl, string $destPath): void
    {
        $tmpZipFileName = tempnam(sys_get_temp_dir(), 'wppack-translations-');
        if ($tmpZipFileName === false) {
            throw new \RuntimeException('Failed to create a temporary file for the language pack');
        }

        try {
            $this->httpDownloader->copy($packageUrl, $tmpZipFileName);
            $this->unpackTranslation($tmpZipFileName, $destPath);
        } finally {
            @unlink($tmpZipFileName);
        }
    }

    private function unpackTranslation(string $tmpZipFileName, string $destPath): void
    {
        $zip = new \ZipArchive();

        if ($zip->open($tmpZipFileName) !== true) {
            throw new \RuntimeException('Unable to open the downloaded language pack ZIP');
        }

        if (!$zip->extractTo($destPath)) {
            throw new \RuntimeException('Failed to extract the language pack into ' . $destPath);
        }

        $zip->close();
    }
}
