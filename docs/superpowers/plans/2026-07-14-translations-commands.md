# translations:update / translations:status Commands Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `composer translations:update` and `composer translations:status` commands so translations can be refreshed at any time, with a differential check (API `updated` vs local `.po` `PO-Revision-Date`) shared with the existing event hooks.

**Architecture:** The plugin gains the `Capable` interface exposing a `CommandProvider` with two commands. The config-reading + per-package download/logging logic moves out of `TranslationsInstaller` into a `TranslationsDownloader` service used by hooks and commands. `Translatable` splits into decide (`status()`) and act (`download()`), backed by three small new units: `PackageType::poPath()`, `PoHeader`, `LocaleState`/`LocaleStatus`.

**Tech Stack:** PHP 8.2+, composer-plugin-api ^2.0, PHPUnit ^11 (new), phpstan level 8, php-cs-fixer (PER-CS2.0 + header comment).

**Spec:** `docs/superpowers/specs/2026-07-14-translations-update-status-commands-design.md`

## Global Constraints

- PHP floor: `^8.2`; runtime deps stay `composer-plugin-api ^2.0` + `ext-zip` only (PHPUnit is require-dev).
- Every PHP file needs `declare(strict_types=1)` and the WPPack license header (cs-fixer enforces; run `vendor/bin/php-cs-fixer fix` after creating files).
- phpstan level 8 must stay green: `vendor/bin/phpstan analyse --no-progress`.
- Error policy: API/download failures are reported per package and swallowed; `translations:update` always exits 0; `translations:status` exits 1 only for Outdated/Missing.
- Freshness rule (spec): local `.po` missing → Missing; remote `updated` newer → Outdated; unparsable timestamps → treat as stale (download); locale absent from API → Not available.
- Existing hook behaviour must keep working (differential check added, nothing else changes).

---

### Task 1: PHPUnit infrastructure

**Files:**
- Modify: `composer.json` (require-dev, autoload-dev)
- Create: `phpunit.xml.dist`
- Modify: `.php-cs-fixer.php` (add tests dir), `phpstan.neon` (add tests path)

**Interfaces:**
- Produces: `tests/` PSR-4 namespace `WPPack\TranslationsInstaller\Tests\`, runnable via `vendor/bin/phpunit`.

- [ ] **Step 1: Add PHPUnit and autoload-dev**

Run: `composer require --dev phpunit/phpunit:^11.5`

Then add to `composer.json` (after the `autoload` block):

```json
    "autoload-dev": {
        "psr-4": {
            "WPPack\\TranslationsInstaller\\Tests\\": "tests"
        }
    },
```

Run: `composer dump-autoload`

- [ ] **Step 2: Create `phpunit.xml.dist`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         failOnRisky="true"
         failOnWarning="true">
    <testsuites>
        <testsuite name="unit">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 3: Cover `tests/` with cs-fixer and phpstan**

`.php-cs-fixer.php`: change the finder to

```php
$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);
```

`phpstan.neon`:

```neon
parameters:
    level: 8
    paths:
        - src
        - tests
```

- [ ] **Step 4: Smoke-check the harness**

Task 2 provides the first real test; here just verify the empty harness runs:

Run: `mkdir -p tests && vendor/bin/phpunit`
Expected: exits 0 or reports "No tests executed" (either is fine at this point).

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock phpunit.xml.dist .php-cs-fixer.php phpstan.neon
git commit -m "Add PHPUnit test infrastructure"
```

---

### Task 2: `PackageType::poPath()`

**Files:**
- Modify: `src/PackageType.php`
- Test: `tests/PackageTypeTest.php`

**Interfaces:**
- Produces: `PackageType::poPath(string $slug, string $locale): string` — path of the representative `.po`, relative to the languages dir, with a leading `/`.

- [ ] **Step 1: Write the failing test**

`tests/PackageTypeTest.php` (include the standard license header + `declare(strict_types=1)` — same as every new file below, omitted here for brevity but REQUIRED):

```php
<?php

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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter PackageTypeTest`
Expected: FAIL — `Call to undefined method ... poPath()`

- [ ] **Step 3: Implement**

Add to `src/PackageType.php`:

```php
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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter PackageTypeTest`
Expected: PASS (3 tests)

- [ ] **Step 5: QA + commit**

```bash
vendor/bin/php-cs-fixer fix && vendor/bin/phpstan analyse --no-progress
git add src/PackageType.php tests/PackageTypeTest.php
git commit -m "Add PackageType::poPath() for the freshness-check marker file"
```

---

### Task 3: `PoHeader::revisionDate()`

**Files:**
- Create: `src/PoHeader.php`
- Test: `tests/PoHeaderTest.php`

**Interfaces:**
- Produces: `PoHeader::revisionDate(string $poFile): ?\DateTimeImmutable` — UTC-normalized revision date, `null` when the file is missing/unreadable or the header is absent/unparsable.

- [ ] **Step 1: Write the failing test**

`tests/PoHeaderTest.php`:

```php
<?php

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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter PoHeaderTest`
Expected: FAIL — class `PoHeader` not found

- [ ] **Step 3: Implement `src/PoHeader.php`**

```php
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
```

Note: `new \DateTimeImmutable('not a date ...')` throws — that's what the catch is for. The UTC timezone argument is ignored when the string carries its own offset (`+0000`), which is exactly right.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter PoHeaderTest`
Expected: PASS (4 tests)

- [ ] **Step 5: QA + commit**

```bash
vendor/bin/php-cs-fixer fix && vendor/bin/phpstan analyse --no-progress
git add src/PoHeader.php tests/PoHeaderTest.php
git commit -m "Add PoHeader to read PO-Revision-Date from language files"
```

---

### Task 4: `LocaleState` + `LocaleStatus`

**Files:**
- Create: `src/LocaleState.php`, `src/LocaleStatus.php`
- Test: `tests/LocaleStateTest.php`

**Interfaces:**
- Produces:
  - `enum LocaleState { UpToDate, Outdated, Missing, NotAvailable }` with `static determine(?\DateTimeImmutable $local, ?\DateTimeImmutable $remote): self` (only for locales the API offers).
  - `LocaleStatus` readonly DTO: `__construct(string $locale, LocaleState $state, ?string $packageUrl, ?\DateTimeImmutable $remoteUpdated, ?\DateTimeImmutable $localRevision)` + `needsDownload(): bool`.

- [ ] **Step 1: Write the failing test**

`tests/LocaleStateTest.php`:

```php
<?php

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
        $status = fn (LocaleState $state) => new LocaleStatus('ja', $state, null, null, null);

        $this->assertTrue($status(LocaleState::Missing)->needsDownload());
        $this->assertTrue($status(LocaleState::Outdated)->needsDownload());
        $this->assertFalse($status(LocaleState::UpToDate)->needsDownload());
        $this->assertFalse($status(LocaleState::NotAvailable)->needsDownload());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter LocaleStateTest`
Expected: FAIL — class `LocaleState` not found

- [ ] **Step 3: Implement**

`src/LocaleState.php`:

```php
<?php

/* (license header) */

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
```

`src/LocaleStatus.php`:

```php
<?php

/* (license header) */

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
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter LocaleStateTest`
Expected: PASS (5 tests)

- [ ] **Step 5: QA + commit**

```bash
vendor/bin/php-cs-fixer fix && vendor/bin/phpstan analyse --no-progress
git add src/LocaleState.php src/LocaleStatus.php tests/LocaleStateTest.php
git commit -m "Add LocaleState/LocaleStatus freshness decision"
```

---

### Task 5: Rework `Translatable` into status() / download()

**Files:**
- Modify: `src/Translatable.php`
- Test: `tests/TranslatableTest.php`

**Interfaces:**
- Consumes: `PackageType::poPath()`, `PoHeader::revisionDate()`, `LocaleState::determine()`, `LocaleStatus`.
- Produces:
  - `Translatable::status(): list<LocaleStatus>` — one entry per configured locale, ordered as configured; throws `\RuntimeException` on unexpected API responses (HttpDownloader throws its own on network errors).
  - `Translatable::download(LocaleStatus $status): void` — downloads and unpacks one locale's pack.
  - `fetch()` is REMOVED (its callers are refactored in Task 6).

- [ ] **Step 1: Write the failing test**

Uses a PHPUnit mock of `Composer\Util\HttpDownloader` (not final) returning a real `Composer\Util\Http\Response`. If mocking ever fails because the class became final, downgrade this task to phpstan-only verification and rely on the CI smoke test — but as of composer/composer ^2.5 it is mockable.

`tests/TranslatableTest.php`:

```php
<?php

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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter TranslatableTest`
Expected: FAIL — `Call to undefined method ... status()`

- [ ] **Step 3: Rework `src/Translatable.php`**

Replace `fetch()` and `availableTranslations()` with:

```php
    /**
     * Freshness of every configured locale: queries the API once and
     * compares each locale's `updated` timestamp with the PO-Revision-Date
     * of the locally installed `.po` file.
     *
     * @return list<LocaleStatus> ordered as the locales were configured
     */
    public function status(): array
    {
        $available = $this->availableTranslations();

        $statuses = [];
        foreach ($this->languages as $locale) {
            if (!isset($available[$locale])) {
                $statuses[] = new LocaleStatus($locale, LocaleState::NotAvailable, null, null, null);

                continue;
            }

            $local = PoHeader::revisionDate($this->wpLanguagesDir . $this->type->poPath($this->slug, $locale));
            $remote = $available[$locale]['updated'];

            $statuses[] = new LocaleStatus(
                $locale,
                LocaleState::determine($local, $remote),
                $available[$locale]['package'],
                $remote,
                $local,
            );
        }

        return $statuses;
    }

    /** Download the language-pack ZIP for one locale and unpack it. */
    public function download(LocaleStatus $status): void
    {
        if ($status->packageUrl === null) {
            throw new \LogicException('No language pack is available for ' . $status->locale);
        }

        $this->installTranslation($status->packageUrl, $this->destPath());
    }

    /**
     * Language packs offered by the API for the wanted locales, keyed by
     * locale.
     *
     * @return array<string, array{package: string, updated: ?\DateTimeImmutable}>
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
                $translations[(string) $translation->language] ??= [
                    'package' => (string) $translation->package,
                    'updated' => self::parseUpdated($translation->updated ?? null),
                ];
            }
        }

        return $translations;
    }

    /** The API sends `updated` as a GMT timestamp like "2026-05-17 09:14:31". */
    private static function parseUpdated(mixed $updated): ?\DateTimeImmutable
    {
        if (!is_string($updated) || $updated === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($updated, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }
```

Keep `destPath()`, `installTranslation()`, `unpackTranslation()` unchanged. Delete `fetch()`.

NOTE: `src/TranslationsInstaller.php` still calls `fetch()` at this point — it breaks phpstan until Task 6. That's why Tasks 5 and 6 are committed together only after phpstan passes: DO NOT run the phpstan gate in this task; the commit happens at the end of Task 6. (Unit tests still pass independently.)

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter TranslatableTest`
Expected: PASS (3 tests)

Do NOT commit yet — Task 6 restores a consistent tree first.

---

### Task 6: `TranslationsDownloader` service + hook refactor

**Files:**
- Create: `src/TranslationsDownloader.php`
- Modify: `src/TranslationsInstaller.php`

**Interfaces:**
- Consumes: `Translatable::status()/download()`, `LocaleStatus::needsDownload()`, `LocaleState`.
- Produces:
  - `TranslationsDownloader::__construct(Composer $composer, IOInterface $io)` — throws `\RuntimeException` when the `extra` config is missing (same messages as before).
  - `TranslationsDownloader::translatableFor(PackageInterface $package): ?Translatable` — null unless the package type is a `PackageType`.
  - `TranslationsDownloader::update(PackageInterface $package): void` — differential download + logging; never throws.

- [ ] **Step 1: Create `src/TranslationsDownloader.php`**

```php
<?php

/* (license header) */

declare(strict_types=1);

namespace WPPack\TranslationsInstaller;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Util\HttpDownloader;

/**
 * Downloads translations for WordPress packages. Shared by the event hooks
 * (per-package on install/update) and the translations:* commands.
 *
 * Configured through `extra` in the root composer.json:
 *
 *     "extra": {
 *         "wordpress-translations": ["ja", "fr_FR"],
 *         "wordpress-translations-dir": "web/wp-content/languages"
 *     }
 */
final class TranslationsDownloader
{
    /** @var list<string> Locales to download (e.g. ja, fr_FR). */
    private array $languages;

    /** Full path to the languages target directory. */
    private string $wpLanguagesDir;

    private HttpDownloader $httpDownloader;

    public function __construct(Composer $composer, private readonly IOInterface $io)
    {
        $this->httpDownloader = $composer->getLoop()->getHttpDownloader();

        $extra = $composer->getPackage()->getExtra();

        $languages = $extra['wordpress-translations'] ?? null;
        if (!is_array($languages) || $languages === []) {
            throw new \RuntimeException("The 'wordpress-translations' extra key is missing or empty — add it to the root composer.json (see README)");
        }
        $this->languages = array_values(array_map(strval(...), $languages));

        $targetDir = $extra['wordpress-translations-dir'] ?? null;
        if (!is_string($targetDir) || $targetDir === '') {
            throw new \RuntimeException("The 'wordpress-translations-dir' extra key is missing or empty — add it to the root composer.json (see README)");
        }
        $vendorDir = (string) $composer->getConfig()->get('vendor-dir');
        $this->wpLanguagesDir = dirname($vendorDir) . '/' . $targetDir;
    }

    /** Null unless the package is a WordPress core/plugin/theme. */
    public function translatableFor(PackageInterface $package): ?Translatable
    {
        $type = PackageType::tryFrom($package->getType());
        if ($type === null) {
            return null;
        }

        // The slug on wordpress.org is the package name without the vendor.
        $slug = explode('/', $package->getName(), 2)[1] ?? $package->getName();

        return new Translatable($type, $slug, $package->getVersion(), $this->languages, $this->wpLanguagesDir, $this->httpDownloader);
    }

    /**
     * Install missing or outdated translations for one package. No-op unless
     * the package is a WordPress core/plugin/theme. API and download failures
     * are reported and swallowed, so the surrounding Composer run never
     * fails because of translations.
     */
    public function update(PackageInterface $package): void
    {
        $translatable = $this->translatableFor($package);
        if ($translatable === null) {
            return;
        }

        $installed = [];
        $available = false;
        try {
            foreach ($translatable->status() as $status) {
                if ($status->state !== LocaleState::NotAvailable) {
                    $available = true;
                }
                if ($status->needsDownload()) {
                    $translatable->download($status);
                    $installed[] = $status->locale;
                }
            }
        } catch (\Exception $e) {
            $this->io->writeError(sprintf(
                '  - Skipped translations for <info>%s</info>: <error>%s</error>',
                $package->getName(),
                $e->getMessage(),
            ));

            return;
        }

        if (!$available) {
            $this->io->writeError(sprintf(
                '  - No translations of <info>%s</info> for the configured locales',
                $package->getName(),
            ));

            return;
        }

        if ($installed === []) {
            $this->io->writeError(sprintf(
                '  - Translations for <info>%s</info> are up to date',
                $package->getName(),
            ));

            return;
        }

        foreach ($installed as $locale) {
            $this->io->writeError(sprintf(
                '  - Installed <comment>%s</comment> translations for <info>%s</info>',
                $locale,
                $package->getName(),
            ));
        }
    }
}
```

- [ ] **Step 2: Slim down `src/TranslationsInstaller.php`**

Full new body (keep the license header):

```php
declare(strict_types=1);

namespace WPPack\TranslationsInstaller;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

/**
 * Composer plugin that downloads translations from wordpress.org for every
 * WordPress package (core, plugins, themes) as it is installed or updated.
 * See TranslationsDownloader for the configuration reference.
 */
final class TranslationsInstaller implements EventSubscriberInterface, PluginInterface
{
    private TranslationsDownloader $downloader;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->downloader = new TranslationsDownloader($composer, $io);
    }

    /** @return array<string, string> */
    public static function getSubscribedEvents(): array
    {
        return [
            'post-package-install' => 'onPostPackageInstall',
            'post-package-update' => 'onPostPackageUpdate',
        ];
    }

    public function onPostPackageInstall(PackageEvent $event): void
    {
        $operation = $event->getOperation();
        if ($operation instanceof InstallOperation) {
            $this->downloader->update($operation->getPackage());
        }
    }

    public function onPostPackageUpdate(PackageEvent $event): void
    {
        $operation = $event->getOperation();
        if ($operation instanceof UpdateOperation) {
            $this->downloader->update($operation->getTargetPackage());
        }
    }

    public function deactivate(Composer $composer, IOInterface $io): void {}

    public function uninstall(Composer $composer, IOInterface $io): void {}
}
```

(`Capable` is added in Task 7.)

- [ ] **Step 3: Full QA gate**

Run: `vendor/bin/phpunit && vendor/bin/php-cs-fixer fix && vendor/bin/phpstan analyse --no-progress`
Expected: all tests pass, phpstan clean.

- [ ] **Step 4: Commit (Tasks 5+6 together — tree is consistent again)**

```bash
git add src/Translatable.php src/TranslationsDownloader.php src/TranslationsInstaller.php tests/TranslatableTest.php
git commit -m "Split freshness check from download and extract TranslationsDownloader service"
```

---

### Task 7: `translations:update` command + capability wiring

**Files:**
- Create: `src/CommandProvider.php`, `src/TranslationsUpdateCommand.php`
- Modify: `src/TranslationsInstaller.php`

**Interfaces:**
- Consumes: `TranslationsDownloader::update()`.
- Produces: `composer translations:update`; `CommandProvider` (Task 8 adds the second command to it).

- [ ] **Step 1: Create `src/CommandProvider.php`**

```php
<?php

/* (license header) */

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
```

- [ ] **Step 2: Create `src/TranslationsUpdateCommand.php`**

```php
<?php

/* (license header) */

declare(strict_types=1);

namespace WPPack\TranslationsInstaller;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Installs missing and outdated translations for every installed WordPress
 * package. Failures are reported per package and never fail the command,
 * matching the behaviour of the install/update hooks.
 */
final class TranslationsUpdateCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('translations:update')
            ->setDescription('Download missing and outdated wordpress.org translations for all installed WordPress packages');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $composer = $this->requireComposer();
        $downloader = new TranslationsDownloader($composer, $this->getIO());

        foreach ($composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages() as $package) {
            $downloader->update($package);
        }

        return 0;
    }
}
```

- [ ] **Step 3: Wire the capability into the plugin**

In `src/TranslationsInstaller.php`:

```php
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Composer\Plugin\Capable;
```

```php
final class TranslationsInstaller implements Capable, EventSubscriberInterface, PluginInterface
```

```php
    /** @return array<string, string> */
    public function getCapabilities(): array
    {
        return [CommandProviderCapability::class => CommandProvider::class];
    }
```

- [ ] **Step 4: QA gate + manual sanity check**

Run: `vendor/bin/phpunit && vendor/bin/php-cs-fixer fix && vendor/bin/phpstan analyse --no-progress`

Manual check that the command registers (uses this repo's own dev install of composer):
Run: `vendor/bin/composer list --raw 2>/dev/null | grep translations || true`
(The command only appears in a project that has the plugin installed and allowed — full verification happens in Task 9's smoke test. If nothing is printed here, that is expected and fine.)

- [ ] **Step 5: Commit**

```bash
git add src/CommandProvider.php src/TranslationsUpdateCommand.php src/TranslationsInstaller.php
git commit -m "Add translations:update command"
```

---

### Task 8: `translations:status` command

**Files:**
- Create: `src/TranslationsStatusCommand.php`
- Modify: `src/CommandProvider.php`

**Interfaces:**
- Consumes: `TranslationsDownloader::translatableFor()`, `Translatable::status()`, `LocaleState`, `LocaleStatus`.
- Produces: `composer translations:status`, exit 1 when anything is Outdated/Missing.

- [ ] **Step 1: Create `src/TranslationsStatusCommand.php`**

```php
<?php

/* (license header) */

declare(strict_types=1);

namespace WPPack\TranslationsInstaller;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Read-only report of the translation state for every installed WordPress
 * package and configured locale. Exits 1 when anything is missing or
 * outdated, so it doubles as a CI freshness check.
 */
final class TranslationsStatusCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('translations:status')
            ->setDescription('Show the freshness of wordpress.org translations for all installed WordPress packages');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $composer = $this->requireComposer();
        $io = $this->getIO();
        $downloader = new TranslationsDownloader($composer, $io);

        $needsUpdate = 0;
        foreach ($composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages() as $package) {
            $translatable = $downloader->translatableFor($package);
            if ($translatable === null) {
                continue;
            }

            try {
                $statuses = $translatable->status();
            } catch (\Exception $e) {
                $io->writeError(sprintf('<info>%s</info>: <error>%s</error>', $package->getName(), $e->getMessage()));

                continue;
            }

            $io->write(sprintf('<info>%s</info>', $package->getName()));
            foreach ($statuses as $status) {
                $io->write('  - ' . str_pad($status->locale, 7) . $this->describe($status));
                if ($status->needsDownload()) {
                    ++$needsUpdate;
                }
            }
        }

        if ($needsUpdate > 0) {
            $io->write(sprintf('%d translation(s) can be updated. Run `composer translations:update`.', $needsUpdate));

            return 1;
        }

        $io->write('All translations are up to date.');

        return 0;
    }

    private function describe(LocaleStatus $status): string
    {
        return match ($status->state) {
            LocaleState::UpToDate => sprintf('Up to date (%s)', self::format($status->localRevision)),
            LocaleState::Outdated => sprintf('<comment>Outdated</comment>   (local %s → remote %s)', self::format($status->localRevision), self::format($status->remoteUpdated)),
            LocaleState::Missing => '<comment>Missing</comment>',
            LocaleState::NotAvailable => 'Not available',
        };
    }

    private static function format(?\DateTimeImmutable $date): string
    {
        return $date === null ? 'unknown' : $date->format('Y-m-d H:i:s');
    }
}
```

- [ ] **Step 2: Register it in `src/CommandProvider.php`**

```php
        return [
            new TranslationsStatusCommand(),
            new TranslationsUpdateCommand(),
        ];
```

- [ ] **Step 3: QA gate**

Run: `vendor/bin/phpunit && vendor/bin/php-cs-fixer fix && vendor/bin/phpstan analyse --no-progress`
Expected: clean.

- [ ] **Step 4: Commit**

```bash
git add src/TranslationsStatusCommand.php src/CommandProvider.php
git commit -m "Add translations:status command"
```

---

### Task 9: CI — run PHPUnit and exercise the commands in the smoke test

**Files:**
- Modify: `.github/workflows/ci.yml`

- [ ] **Step 1: Add PHPUnit to the checks job**

After the phpstan step:

```yaml
      - run: vendor/bin/phpunit
```

- [ ] **Step 2: Exercise the commands in the smoke-test job**

Append these steps after "Assert translations were downloaded":

```yaml
      - name: translations:status reports up to date
        working-directory: project
        run: composer translations:status

      - name: translations:update is a no-op when current
        working-directory: project
        run: composer translations:update

      - name: translations:status detects a stale translation
        working-directory: project
        run: |
          PO_FILE="$(find languages/plugins -name 'query-monitor-ja.po' -print -quit)"
          sed -i 's/"PO-Revision-Date: [^\\]*/"PO-Revision-Date: 2020-01-01 00:00:00+0000/' "$PO_FILE"
          if composer translations:status; then
            echo 'expected exit 1 for a stale translation' >&2; exit 1
          fi

      - name: translations:update refreshes the stale translation
        working-directory: project
        run: |
          composer translations:update
          composer translations:status
```

(`translations:status` after the first install exits 0 because the hook just installed everything — if wordpress.org publishes an update in the seconds between, the run legitimately exits 1; acceptable flake risk for a smoke test.)

- [ ] **Step 3: Local E2E of the same flow**

Reproduce the smoke test locally in the scratchpad dir (path repo pointing at this checkout, requires network):

```bash
SCRATCH=/private/tmp/claude-501/-Users-tsuyoshi-dev-wppack-io-translations-installer/b2b868a4-eebc-46e3-b227-f821b789b4f2/scratchpad
mkdir -p "$SCRATCH/e2e" && cd "$SCRATCH/e2e"
# write the same composer.json as the CI smoke test, url pointing at the plugin checkout
composer install --no-interaction
composer translations:status; echo "status exit: $?"
composer translations:update
```

Expected: install prints "Installed ja translations for wpackagist-plugin/query-monitor"; status lists it Up to date and exits 0; after sed-ing the `.po` date back to 2020, status exits 1 and update re-installs it.

- [ ] **Step 4: Commit**

```bash
git add .github/workflows/ci.yml
git commit -m "CI: run PHPUnit and exercise the translations commands"
```

---

### Task 10: Documentation + remove the work-log directory

**Files:**
- Modify: `README.md`, `README.ja.md`
- Delete: `docs/superpowers/` (spec + this plan — fold the durable content into the READMEs)

- [ ] **Step 1: Document the commands in `README.md`**

Read the existing README first and match its tone/structure. Add a "Commands" section covering:
- `composer translations:update` — refresh translations for all installed packages at any time; only downloads missing/outdated locales.
- `composer translations:status` — read-only report (Up to date / Outdated / Missing / Not available); exits 1 when anything can be updated (CI-friendly).
- How freshness is decided: the API `updated` timestamp vs the local `.po` `PO-Revision-Date` (the same mechanism WordPress core uses); no state file; deleting a `.po` re-downloads that locale.

- [ ] **Step 2: Mirror the section in `README.ja.md`** (same content in Japanese).

- [ ] **Step 3: Remove the work log**

```bash
git rm -r docs/superpowers
rmdir docs 2>/dev/null || true
```

- [ ] **Step 4: Final full QA**

Run: `vendor/bin/phpunit && vendor/bin/php-cs-fixer check --diff && vendor/bin/phpstan analyse --no-progress && composer validate --strict`
Expected: everything green.

- [ ] **Step 5: Commit**

```bash
git add README.md README.ja.md
git commit -m "Document translations:update / translations:status and drop work-log docs"
```
