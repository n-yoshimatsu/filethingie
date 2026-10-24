# File Thingie - PHP File Manager
File Thingie is a PHP file manager for managing local files. See more at [http://www.solitude.dk/filethingie/](http://www.solitude.dk/filethingie/)

For bug reports use the github issue tracker.

File Thingie was one of my very first PHP projects and it's going on github so I will feel motivated to writing a more modern version.

## PHP 8.3とPHP 8.5への対応

このブランチは、File Thingie 2.5.7をPHP 8.3とPHP 8.5へ対応させたフォークです。
さくらのレンタルサーバ（FreeBSD／PHP 8.3.36）への本番移行・受入が完了しました。PHP 8.5.12はローカルとCIで検証しています。
段階4までの更新を行い、PHP 8.3.36／8.5.12とローカルChromeの最終20ケースがPASSです。
認証・CSRF、DB／ログ／ZIP、jQuery、日本語表示を更新しました。
未完成の設定画面・ファイル説明・旧外部エディター3種は無効化し、移行方法を明示しています。
段階5でPHP 8.3／8.5のCIを追加し、32ケースがPASSです。HTTPS・URL取得・同時アップロード・Firefoxも検証しました。
さくら実機のPHP 8.3.36／FreeBSDで、分離配置の受入SA-501〜503がPASS。Web操作132比較と利用者提供の内部確認16比較の合計148比較が一致。分離配置での確認結果、本番移行の結果は段階6に記載。
30分の無操作期限・8時間の絶対期限も両PHPで実時間試験がPASS。段階5の予定41ケースは合格、CI・総合検証・さくら受入の完了。既存設定・保存データの本番移行も段階6で完了。

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
- [段階5の完了結果・CI・証跡](docs/STAGE5_REPORT.md)
- [さくら・FreeBSD受入手順](docs/SAKURA_ACCEPTANCE.md)

段階6の本番切替・受入が完了。既存のBasic認証、表示、保存先とURLを維持し、Chrome87項目と内部照合7項目の計94項目がPASSです。保存337ファイルと停止時バックアップの一致、PHPログ0バイトを最終照合時に確認しました。正式配布のタグ・GitHub Releaseは準備中です。

- [本番移行・受入の結果](docs/STAGE6_REPORT.md)
- [導入・更新・復元手順](docs/RELEASE_GUIDE.md)
- [配布版の変更点と検証範囲](docs/RELEASE_NOTES.md)
- [本番受入版の配布ZIP](docs/releases/2026-10-25/filethingie-php83-85-20261025.zip)・[SHA-256](docs/releases/2026-10-25/SHA256SUMS)
- [移行全体の手順と状況](docs/STAGE6_MIGRATION.md)
