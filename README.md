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

## Credits

This package was started by Angry Creative, has been rewritten by Bjørn
Johansen, integrates compatibility changes made by Mirai and was updated to
support Composer v2. The WPPack edition modernizes the codebase for
PHP 8.2+.

## License

GPL-2.0-or-later
