# Design: `translations:update` / `translations:status` commands

Date: 2026-07-14
Status: Approved

## Problem

The plugin only downloads translations from the `post-package-install` /
`post-package-update` events, so translations are refreshed only when a
package itself changes. wordpress.org language packs are frequently updated
*after* a package release, and there is currently no way to pick those up
without forcing a package reinstall.

## Goal

Add two Composer commands, runnable at any time:

- `composer translations:update` — download translations for every installed
  WordPress package (core / plugin / theme), skipping locales that are already
  up to date.
- `composer translations:status` — read-only report of the translation state
  per package × locale; suitable for CI checks.

## Non-goals

- No change to when the event hooks fire.
- No state/manifest file. Freshness is derived from the installed files
  themselves.
- No new configuration keys. Both commands reuse `wordpress-translations` and
  `wordpress-translations-dir` from the root `composer.json` `extra`.

## Freshness check (shared by hooks and both commands)

The wordpress.org translations API response already contains an `updated`
timestamp per locale (no extra requests needed). Locally, every installed
language pack ships a `.po` file whose `PO-Revision-Date:` header carries the
same timestamp — the mechanism WordPress core itself uses
(`wp_get_installed_translations()`).

Representative local `.po` file per package type:

| Type   | Path relative to languages dir      |
|--------|-------------------------------------|
| Core   | `{locale}.po`                       |
| Plugin | `plugins/{slug}-{locale}.po`        |
| Theme  | `themes/{slug}-{locale}.po`         |

Decision rule per locale:

- Local `.po` missing → **Missing** (download).
- API `updated` newer than local `PO-Revision-Date` → **Outdated** (download).
- Otherwise → **Up to date** (skip).
- Either timestamp unparsable → treat as stale (download). Fail safe.
- Locale absent from the API response → **Not available** (nothing to do).

The header is read from the first few KB of the file with a regex; no full PO
parser.

This differential check also applies to the existing event hooks. On a fresh
install nothing exists locally, so behaviour is unchanged; on updates it only
avoids re-downloading packs that are already current.

## Architecture

### New: command wiring (Composer plugin capability)

- `TranslationsInstaller` additionally implements `Composer\Plugin\Capable`;
  `getCapabilities()` maps `Composer\Plugin\Capability\CommandProvider` to a
  new `CommandProvider` class.
- `CommandProvider` returns `TranslationsUpdateCommand` and
  `TranslationsStatusCommand` (both extend `Composer\Command\BaseCommand`).

### Refactor: shared service

Extract the logic currently inside `TranslationsInstaller` (reading the
`extra` config, per-package download, result logging) into a service class,
e.g. `TranslationsDownloader`, constructed from `Composer` + `IOInterface`.
It separates *deciding* (per-locale status) from *acting* (download/unpack) so
that:

- the event hooks call decide + act,
- `translations:update` calls decide + act over all installed packages,
- `translations:status` calls decide only.

`Translatable` keeps the API/unzip mechanics; it gains access to the per-locale
`updated` timestamp and the local representative `.po` path (via a new method
on `PackageType`).

### Package scan (both commands)

Iterate `getRepositoryManager()->getLocalRepository()->getPackages()` and
process only packages whose type matches `PackageType`. The wordpress.org slug
is the package name without the vendor prefix (existing convention).

## Command behaviour

### `translations:update`

- For each package × configured locale: download when Missing/Outdated, skip
  when Up to date.
- Output mirrors the existing hook style:

```
> composer translations:update
  - Updated ja translations for wpackagist-plugin/query-monitor
  - Translations for roots/wordpress are up to date
  - No translations of wpackagist-plugin/some-plugin for the configured locales
```

- Error handling follows the current policy: API/download failures are
  reported per package and skipped; the command itself always exits 0.

### `translations:status`

- Read-only; performs API requests but never downloads packs.
- Prints one line per package × locale with its state: `Up to date`,
  `Outdated` (local → remote timestamps shown), `Missing`, `Not available`.
- Exit code: 1 if anything is Outdated or Missing (usable as a CI freshness
  check), 0 when everything is current. API failures for a package are
  reported and do not fail the command by themselves.

```
> composer translations:status
wpackagist-plugin/query-monitor
  - ja     Up to date (2026-05-01 10:00:00)
  - fr_FR  Outdated   (local 2026-01-15 → remote 2026-06-20)
roots/wordpress
  - ja     Missing
1 translation(s) can be updated. Run `composer translations:update`.
```

## Testing

- Add PHPUnit to `require-dev` (first test infrastructure in this repo).
- Unit tests for the pure parts: `PO-Revision-Date` header parsing, timestamp
  comparison / status decision, representative `.po` path per package type.
- End-to-end behaviour (commands against the live API) verified manually in a
  sample project.
