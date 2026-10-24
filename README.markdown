# File Thingie - PHP File Manager
File Thingie is a PHP file manager for managing local files. See more at [http://www.solitude.dk/filethingie/](http://www.solitude.dk/filethingie/)

For bug reports use the github issue tracker.

File Thingie was one of my very first PHP projects and it's going on github so I will feel motivated to writing a more modern version.

## PHP 8.3とPHP 8.5への対応計画

この開発ブランチでは、File Thingie 2.5.7をPHP 8.3とPHP 8.5に対応させます。
実運用先はさくらのレンタルサーバ（FreeBSD／PHP 8.3）です。PHP 8.5も検証対象にします。
段階4までの更新を行い、PHP 8.3.36／8.5.12とローカルChromeの最終20ケースがPASSです。
認証・CSRF、DB／ログ／ZIP、jQuery、日本語表示を更新しました。
未完成の設定画面・ファイル説明・旧外部エディター3種は無効化し、移行方法を明示しています。
段階5でPHP 8.3／8.5のCIを追加し、32ケースがPASSです。HTTPS・URL取得・同時アップロード・Firefoxも検証しました。
さくら実機のCLIとWeb環境はPHP 8.3.36／FreeBSDで確認済みです。分離配置での受入と長時間セッション試験は継続中です。現在は開発中の検証版です。

- [移行計画と対応範囲](docs/PHP85_MIGRATION.md)
- [既存機能と依存関係](docs/FEATURE_INVENTORY.md)
- [PHP 8.3／8.5の検証環境と実行手順](docs/STAGE2_ENVIRONMENT.md)
- [段階2の試験条件](docs/STAGE2_TEST_PLAN.md)
- [段階2の結果と未確認事項](docs/STAGE2_REPORT.md)
- [段階3の修正内容・検証結果・再実行と配置の引継ぎ](docs/STAGE3_REPORT.md)
- [段階3の試験条件](docs/STAGE3_TEST_PLAN.md)

- [段階4の変更と検証結果](docs/STAGE4_REPORT.md)
- [設定・認証・同梱プラグインの移行手順と対応表](docs/STAGE4_MIGRATION.md)
- [段階4の試験条件](docs/STAGE4_TEST_PLAN.md)
- [ブラウザー・PHP依存とライセンス](docs/DEPENDENCIES.md)

- [段階5の試験条件](docs/STAGE5_TEST_PLAN.md)
- [段階5のCI結果・証跡・継続中の確認](docs/STAGE5_REPORT.md)
- [さくら・FreeBSD受入手順](docs/SAKURA_ACCEPTANCE.md)
