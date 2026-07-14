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

use PHPUnit\Framework\TestCase;
use WPPack\TranslationsInstaller\PoHeader;

final class PoHeaderTest extends TestCase
{
    /** @var list<string> */
    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            @unlink($file);
        }
    }

    private function poFile(string $content): string
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'wppack-test-');
        file_put_contents($file, $content);
        $this->tmpFiles[] = $file;

        return $file;
    }

    public function testReadsRevisionDateFromHeader(): void
    {
        $file = $this->poFile(<<<'PO'
            # Translation of Plugins - Query Monitor in Japanese
            msgid ""
            msgstr ""
            "PO-Revision-Date: 2026-05-17 09:14:31+0000\n"
            "MIME-Version: 1.0\n"
            PO);

        $date = PoHeader::revisionDate($file);

        $this->assertNotNull($date);
        $this->assertSame('2026-05-17 09:14:31', $date->format('Y-m-d H:i:s'));
    }

    public function testMissingFileReturnsNull(): void
    {
        $this->assertNull(PoHeader::revisionDate('/nonexistent/path/ja.po'));
    }

    public function testFileWithoutHeaderReturnsNull(): void
    {
        $this->assertNull(PoHeader::revisionDate($this->poFile("msgid \"\"\nmsgstr \"\"\n")));
    }

    public function testUnparsableDateReturnsNull(): void
    {
        $this->assertNull(PoHeader::revisionDate($this->poFile('"PO-Revision-Date: not a date at all zzz\n"')));
    }
}
