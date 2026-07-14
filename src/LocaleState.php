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
 * Freshness of one locale of one package, comparing the local
 * PO-Revision-Date with the `updated` timestamp from the wordpress.org API.
 */
enum LocaleState
{
    case UpToDate;
    case Outdated;
    case Missing;
    case NotAvailable;

    /**
     * Decide the state for a locale the API does offer. Unparsable
     * timestamps count as stale so a broken file is repaired by
     * re-downloading rather than silently kept.
     */
    public static function determine(?\DateTimeImmutable $local, ?\DateTimeImmutable $remote): self
    {
        if ($local === null) {
            return self::Missing;
        }

        if ($remote === null || $remote > $local) {
            return self::Outdated;
        }

        return self::UpToDate;
    }
}
