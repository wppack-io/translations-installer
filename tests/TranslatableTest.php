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

use Composer\Util\Http\Response;
use Composer\Util\HttpDownloader;
use PHPUnit\Framework\TestCase;
use WPPack\TranslationsInstaller\LocaleState;
use WPPack\TranslationsInstaller\PackageType;
use WPPack\TranslationsInstaller\Translatable;

final class TranslatableTest extends TestCase
{
    private string $languagesDir;

    protected function setUp(): void
    {
        $this->languagesDir = sys_get_temp_dir() . '/wppack-test-' . bin2hex(random_bytes(4));
        mkdir($this->languagesDir . '/plugins', 0775, true);
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->languagesDir . '/plugins/*') ?: []);
        @rmdir($this->languagesDir . '/plugins');
        @rmdir($this->languagesDir);
    }

    /** @param list<string> $languages */
    private function translatable(string $apiBody, array $languages): Translatable
    {
        $downloader = $this->createMock(HttpDownloader::class);
        $downloader->method('get')->willReturn(new Response(['url' => 'test'], 200, [], $apiBody));

        return new Translatable(PackageType::Plugin, 'query-monitor', '3.17.0', $languages, $this->languagesDir, $downloader);
    }

    public function testStatusCoversEveryConfiguredLocale(): void
    {
        file_put_contents(
            $this->languagesDir . '/plugins/query-monitor-ja.po',
            "msgid \"\"\nmsgstr \"\"\n\"PO-Revision-Date: 2026-05-17 09:14:31+0000\\n\"\n",
        );

        $apiBody = json_encode(['translations' => [
            ['language' => 'ja', 'updated' => '2026-05-17 09:14:31', 'package' => 'https://example.test/ja.zip'],
            ['language' => 'fr_FR', 'updated' => '2026-06-20 00:00:00', 'package' => 'https://example.test/fr.zip'],
        ]]);

        $statuses = $this->translatable((string) $apiBody, ['ja', 'fr_FR', 'de_DE'])->status();

        $this->assertCount(3, $statuses);
        $this->assertSame(LocaleState::UpToDate, $statuses[0]->state);
        $this->assertSame(LocaleState::Missing, $statuses[1]->state);
        $this->assertSame('https://example.test/fr.zip', $statuses[1]->packageUrl);
        $this->assertSame(LocaleState::NotAvailable, $statuses[2]->state);
    }

    public function testOutdatedWhenRemoteIsNewer(): void
    {
        file_put_contents(
            $this->languagesDir . '/plugins/query-monitor-ja.po',
            "msgid \"\"\nmsgstr \"\"\n\"PO-Revision-Date: 2026-01-01 00:00:00+0000\\n\"\n",
        );

        $apiBody = json_encode(['translations' => [
            ['language' => 'ja', 'updated' => '2026-06-20 00:00:00', 'package' => 'https://example.test/ja.zip'],
        ]]);

        $statuses = $this->translatable((string) $apiBody, ['ja'])->status();

        $this->assertSame(LocaleState::Outdated, $statuses[0]->state);
        $this->assertSame('2026-01-01 00:00:00', $statuses[0]->localRevision?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-20 00:00:00', $statuses[0]->remoteUpdated?->format('Y-m-d H:i:s'));
    }

    public function testMalformedApiResponseThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->translatable('"just a string"', ['ja'])->status();
    }
}
