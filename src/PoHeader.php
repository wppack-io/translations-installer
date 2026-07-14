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
 * Reads the PO-Revision-Date header from a gettext `.po` file. The header
 * always sits at the top of the file, so only the first few KB are read.
 */
final class PoHeader
{
    private const HEADER_BYTES = 8192;

    public static function revisionDate(string $poFile): ?\DateTimeImmutable
    {
        if (!is_file($poFile)) {
            return null;
        }

        $head = @file_get_contents($poFile, false, null, 0, self::HEADER_BYTES);
        if ($head === false) {
            return null;
        }

        if (preg_match('/"PO-Revision-Date: *(.+?)(?:\\\\n)?"/', $head, $matches) !== 1) {
            return null;
        }

        try {
            return new \DateTimeImmutable($matches[1], new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }
}
