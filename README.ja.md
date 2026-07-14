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

## コマンド

wordpress.org の言語パックはパッケージのリリース*後*に更新されることが
多いため、任意のタイミングで実行できるコマンドを2つ用意しています:

```console
$ composer translations:update
```

インストール済みの全 WordPress パッケージの翻訳をダウンロードします。
ローカルに存在しない、または wordpress.org 側が新しいロケールだけを取得し、
最新のものはスキップします。失敗はパッケージ単位で報告され、コマンド自体は
失敗しません。

```console
$ composer translations:status
```

パッケージ × ロケールごとの状態(`Up to date` / `Outdated`(ローカルと
リモートの日時を併記)/ `Missing` / `Not available`)を表示する読み取り
専用コマンドです。更新できる翻訳が1つでもあれば exit code 1 で終了する
ため、CI での鮮度チェックにも使えます:

```console
$ composer translations:status
wpackagist-plugin/query-monitor
  - ja     Up to date (2026-07-03 14:02:39)
  - fr_FR  Outdated   (local 2026-01-15 10:00:00 → remote 2026-06-20 09:14:31)
All translations are up to date.
```

### 更新判定のしくみ

wordpress.org API は言語パックごとに `updated` 日時を返し、インストール
済みの `.po` ファイルは同じ日時を `PO-Revision-Date` ヘッダーに持って
います。プラグインはこの2つを比較します — WordPress コア自身が言語パック
の更新チェックに使っているのと同じ仕組みです。状態ファイルはなく、
インストール済みファイルそのものが記録になるため、`.po` を削除すれば
そのロケールは単に再ダウンロードされます。ダウンロード時はパック一式
(`.po`・`.mo`・`.l10n.php`)がまとめて展開されます。

同じ判定はインストール/更新フックでも動作するため、変更のない翻訳が
フックで再ダウンロードされることもありません。

## クレジット

このパッケージは Angry Creative によって開始され、Bjørn Johansen により
書き直され、Mirai による互換性対応を取り込み、Composer v2 対応へと
更新されてきたものです。WPPack 版では PHP 8.2+ 向けにコードベースを
モダナイズしています。

## ライセンス

GPL-2.0-or-later
