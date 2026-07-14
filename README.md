# WPPack Translations Installer

[![CI](https://img.shields.io/github/actions/workflow/status/wppack-io/translations-installer/ci.yml?branch=1.x)](https://github.com/wppack-io/translations-installer/actions/workflows/ci.yml)
[![License: GPL-2.0-or-later](https://img.shields.io/badge/License-GPL--2.0--or--later-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4.svg)](https://php.net)

[日本語版 README](README.ja.md)

Composer plugin that downloads translation files for WordPress core, plugins
and themes from wordpress.org, as the packages are installed or updated.

A fork of [bjornjohansen/wplang](https://github.com/bjornjohansen/wplang),
brought into the WPPack package family.

Supports Composer v2 only. Requires PHP 8.2+ and `ext-zip`.

## Installation

Add the configuration to `composer.json` (paths are relative to the project
root, i.e. the directory holding `composer.json`):

```json
{
    "extra": {
        "wordpress-translations": ["ja", "fr_FR"],
        "wordpress-translations-dir": "web/wp-content/languages"
    }
}
```

Then:

```console
$ composer require wppack/translations-installer
```

Composer asks to trust the plugin on first install; allow it (or pre-seed
`config.allow-plugins`):

```json
{
    "config": {
        "allow-plugins": {
            "wppack/translations-installer": true
        }
    }
}
```

## Usage

Nothing to run: whenever a package of type `wordpress-core`,
`wordpress-plugin` or `wordpress-theme` is installed or updated, the matching
language packs are fetched from the wordpress.org translations API and
unpacked into the configured directory — core at its root, plugins and themes
under `plugins/` and `themes/`, the same layout WordPress itself uses under
`wp-content/languages`.

The core package is not tied to `roots/wordpress`: any package that declares
`"type": "wordpress-core"` (e.g. `roots/wordpress`, `johnpbloch/wordpress-core`)
is picked up. Core translations are looked up on wordpress.org by version
only, so the package name does not matter.

Packages that have no translations for the configured locales print a note
and are skipped; the install itself never fails because of translations.

## Commands

Language packs on wordpress.org are often updated *after* a package release,
so the plugin also ships two commands that can be run at any time:

```console
$ composer translations:update
```

Downloads translations for every installed WordPress package. Only locales
that are missing locally or outdated on wordpress.org are fetched; current
ones are skipped. Failures are reported per package and never fail the
command.

```console
$ composer translations:status
```

Read-only report of every package × locale: `Up to date`, `Outdated` (local
and remote timestamps shown), `Missing` or `Not available`. Exits with code 1
when anything can be updated, so it doubles as a CI freshness check:

```console
$ composer translations:status
wpackagist-plugin/query-monitor
  - ja     Up to date (2026-07-03 14:02:39)
  - fr_FR  Outdated   (local 2026-01-15 10:00:00 → remote 2026-06-20 09:14:31)
All translations are up to date.
```

### How freshness is decided

The wordpress.org API reports an `updated` timestamp per language pack; the
installed `.po` file carries the same timestamp in its `PO-Revision-Date`
header. The plugin compares the two — the same mechanism WordPress core uses
for its own language pack updates. There is no state file: the installed
files are the record, so deleting a `.po` simply causes that locale to be
re-downloaded. Downloads always unpack the full pack (`.po`, `.mo` and
`.l10n.php`).

The same check runs in the install/update hooks, so unchanged translations
are not re-downloaded there either.

## Credits

This package was started by Angry Creative, has been rewritten by Bjørn
Johansen, integrates compatibility changes made by Mirai and was updated to
support Composer v2. The WPPack edition modernizes the codebase for
PHP 8.2+.

## License

GPL-2.0-or-later
