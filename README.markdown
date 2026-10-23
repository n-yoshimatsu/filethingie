# File Thingie - PHP File Manager
File Thingie is a PHP file manager for managing local files. See more at [http://www.solitude.dk/filethingie/](http://www.solitude.dk/filethingie/)

For bug reports use the github issue tracker.

File Thingie was one of my very first PHP projects and it's going on github so I will feel motivated to writing a more modern version.

## PHP 8.3とPHP 8.5への対応計画

この開発ブランチでは、File Thingie 2.5.7をPHP 8.3とPHP 8.5に対応させます。
実運用先はさくらのレンタルサーバ（FreeBSD／PHP 8.3）です。PHP 8.5も検証対象にします。
段階3の互換性修正を行い、PHP 8.3.36／8.5.12の両方で、ログインと基本的なファイル操作のHTTP試験が通りました。
ブラウザー操作、さくら実機、認証方式と全プラグインの更新・検証は後続段階です。現在は開発中の検証版です。

- [移行計画と対応範囲](docs/PHP85_MIGRATION.md)
- [既存機能と依存関係](docs/FEATURE_INVENTORY.md)
- [PHP 8.3／8.5の検証環境と実行手順](docs/STAGE2_ENVIRONMENT.md)
- [段階2の試験条件](docs/STAGE2_TEST_PLAN.md)
- [段階2の結果と未確認事項](docs/STAGE2_REPORT.md)
- [段階3の修正内容・検証結果・再実行と配置の引継ぎ](docs/STAGE3_REPORT.md)
- [段階3の試験条件](docs/STAGE3_TEST_PLAN.md)
