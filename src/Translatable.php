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
     * Download and unpack the language packs for every wanted locale.
     *
     * @return list<string> the locales that were installed; configured
     *                      locales the API does not offer are silently omitted
     */
    public function fetch(): array
    {
        $translations = $this->availableTranslations();
        if ($translations === []) {
            return [];
        }

        $destPath = $this->destPath();
        foreach ($translations as $packageUrl) {
            $this->installTranslation($packageUrl, $destPath);
        }

        return array_keys($translations);
    }

    /**
     * Language-pack ZIP URLs for the wanted locales, keyed by locale.
     *
     * @return array<string, string>
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
                $translations[(string) $translation->language] ??= (string) $translation->package;
            }
        }

        return $translations;
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
