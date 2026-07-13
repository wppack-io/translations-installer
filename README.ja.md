# WPPack Translations Installer

[![CI](https://img.shields.io/github/actions/workflow/status/wppack-io/translations-installer/ci.yml?branch=1.x)](https://github.com/wppack-io/translations-installer/actions/workflows/ci.yml)
[![License: GPL-2.0-or-later](https://img.shields.io/badge/License-GPL--2.0--or--later-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4.svg)](https://php.net)

[English README](README.md)

WordPress コア・プラグイン・テーマの翻訳ファイルを、パッケージのインストール
/更新時に wordpress.org から自動ダウンロードする Composer プラグイン。

[bjornjohansen/wplang](https://github.com/bjornjohansen/wplang) を fork して
WPPack ファミリーに取り込んだものです。

Composer v2 専用。PHP 8.2 以上と `ext-zip` が必要です。

## インストール

`composer.json` に設定を追加します(パスはプロジェクトルート =
`composer.json` のあるディレクトリからの相対):

```json
{
    "extra": {
        "wordpress-translations": ["ja"],
        "wordpress-translations-dir": "web/wp-content/languages"
    }
}
```

その後:

```console
$ composer require wppack/translations-installer
```

初回インストール時に Composer がプラグインの許可を求めるので許可します
(`config.allow-plugins` に事前登録しても OK):

```json
{
    "config": {
        "allow-plugins": {
            "wppack/translations-installer": true
        }
    }
}
```

## 使い方

何も実行する必要はありません。`wordpress-core` / `wordpress-plugin` /
`wordpress-theme` タイプのパッケージがインストール・更新されるたびに、
対応する言語パックを wordpress.org 翻訳 API から取得し、設定した
ディレクトリに展開します — コアはルート直下、プラグインとテーマは
`plugins/`・`themes/` 配下で、WordPress 自身の `wp-content/languages`
と同じレイアウトです。

コアパッケージは `roots/wordpress` 専用ではありません。`"type": "wordpress-core"`
を宣言しているパッケージ(`roots/wordpress`、`johnpbloch/wordpress-core` など)
であれば何でも対象になります。コアの翻訳は wordpress.org にバージョンのみで
問い合わせるため、パッケージ名は影響しません。

設定ロケールの翻訳が存在しないパッケージはメモを出力してスキップされ、
翻訳が原因でインストール自体が失敗することはありません。

## クレジット

このパッケージは Angry Creative によって開始され、Bjørn Johansen により
書き直され、Mirai による互換性対応を取り込み、Composer v2 対応へと
更新されてきたものです。WPPack 版では PHP 8.2+ 向けにコードベースを
モダナイズしています。

## ライセンス

GPL-2.0-or-later
