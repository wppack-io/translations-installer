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

namespace WPPack\TranslationsInstaller\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WPPack\TranslationsInstaller\PackageType;

final class PackageTypeTest extends TestCase
{
    /** @return iterable<string, array{PackageType, string, string, string}> */
    public static function poPathProvider(): iterable
    {
        yield 'core' => [PackageType::Core, 'wordpress', 'ja', '/ja.po'];
        yield 'plugin' => [PackageType::Plugin, 'query-monitor', 'ja', '/plugins/query-monitor-ja.po'];
        yield 'theme' => [PackageType::Theme, 'twentytwentyfour', 'fr_FR', '/themes/twentytwentyfour-fr_FR.po'];
    }

    #[DataProvider('poPathProvider')]
    public function testPoPath(PackageType $type, string $slug, string $locale, string $expected): void
    {
        $this->assertSame($expected, $type->poPath($slug, $locale));
    }
}
