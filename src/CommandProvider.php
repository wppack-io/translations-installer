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

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;

/** Provides the translations:* commands. */
final class CommandProvider implements CommandProviderCapability
{
    public function getCommands(): array
    {
        return [
            new TranslationsUpdateCommand(),
        ];
    }
}
