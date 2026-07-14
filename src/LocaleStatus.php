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
 * The freshness check result for one locale of one package.
 */
final class LocaleStatus
{
    public function __construct(
        public readonly string $locale,
        public readonly LocaleState $state,
        public readonly ?string $packageUrl,
        public readonly ?\DateTimeImmutable $remoteUpdated,
        public readonly ?\DateTimeImmutable $localRevision,
    ) {}

    public function needsDownload(): bool
    {
        return $this->state === LocaleState::Outdated || $this->state === LocaleState::Missing;
    }
}
