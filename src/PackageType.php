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

/**
 * The WordPress package types that have language packs on wordpress.org,
 * backed by their Composer package type. All type-specific knowledge (API
 * endpoint, destination subdirectory) lives here.
 */
enum PackageType: string
{
    case Core = 'wordpress-core';
    case Plugin = 'wordpress-plugin';
    case Theme = 'wordpress-theme';

    /**
     * The wordpress.org translations API endpoint for one package.
     *
     * @param string $slug    the wordpress.org slug; core is queried by version only
     * @param string $version passed verbatim as the `version` query parameter
     */
    public function apiUrl(string $slug, string $version): string
    {
        return match ($this) {
            self::Core => sprintf('https://api.wordpress.org/translations/core/1.0/?version=%s', $version),
            self::Plugin => sprintf('https://api.wordpress.org/translations/plugins/1.0/?slug=%s&version=%s', $slug, $version),
            self::Theme => sprintf('https://api.wordpress.org/translations/themes/1.0/?slug=%s&version=%s', $slug, $version),
        };
    }

    /**
     * The representative `.po` file used for the freshness check, relative
     * to the languages directory. Language packs always ship a `.po`; its
     * PO-Revision-Date header matches the API `updated` timestamp.
     */
    public function poPath(string $slug, string $locale): string
    {
        return match ($this) {
            self::Core => sprintf('/%s.po', $locale),
            self::Plugin => sprintf('/plugins/%s-%s.po', $slug, $locale),
            self::Theme => sprintf('/themes/%s-%s.po', $slug, $locale),
        };
    }

    /**
     * Where the language packs unpack, relative to the languages directory:
     * core at its root, plugins and themes one level down — the same layout
     * WordPress itself uses under wp-content/languages.
     */
    public function subDirectory(): string
    {
        return match ($this) {
            self::Core => '',
            self::Plugin => '/plugins',
            self::Theme => '/themes',
        };
    }
}
