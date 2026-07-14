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
use WPPack\TranslationsInstaller\LocaleState;
use WPPack\TranslationsInstaller\LocaleStatus;

final class LocaleStateTest extends TestCase
{
    public function testLocalMissingIsMissing(): void
    {
        $this->assertSame(LocaleState::Missing, LocaleState::determine(null, new \DateTimeImmutable('2026-01-01')));
    }

    public function testUnparsableRemoteIsOutdatedFailSafe(): void
    {
        $this->assertSame(LocaleState::Outdated, LocaleState::determine(new \DateTimeImmutable('2026-01-01'), null));
    }

    public function testNewerRemoteIsOutdated(): void
    {
        $this->assertSame(
            LocaleState::Outdated,
            LocaleState::determine(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-06-01')),
        );
    }

    public function testEqualOrOlderRemoteIsUpToDate(): void
    {
        $date = new \DateTimeImmutable('2026-06-01 10:00:00');
        $this->assertSame(LocaleState::UpToDate, LocaleState::determine($date, $date));
        $this->assertSame(LocaleState::UpToDate, LocaleState::determine($date, new \DateTimeImmutable('2026-01-01')));
    }

    public function testNeedsDownload(): void
    {
        $status = fn(LocaleState $state) => new LocaleStatus('ja', $state, null, null, null);

        $this->assertTrue($status(LocaleState::Missing)->needsDownload());
        $this->assertTrue($status(LocaleState::Outdated)->needsDownload());
        $this->assertFalse($status(LocaleState::UpToDate)->needsDownload());
        $this->assertFalse($status(LocaleState::NotAvailable)->needsDownload());
    }
}
