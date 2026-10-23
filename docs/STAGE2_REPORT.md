# 段階2・再現環境と修正前の試験結果

実施日：2026年10月24日、日本時間07:19台の基準試験。UTC証跡では2026年10月23日22:19台の記録。

実施範囲：PHP 8.3／8.5のローカル再現環境、試験用設定・データ、未修正ソースの構文確認と初期画面HTTP応答確認。実機環境の確認方法と未確認事項の整理。

**ローカル環境整備・4ケースの実行・記録を完了。アプリケーションの判定は4ケースともFAIL。さくら実機の詳細構成・受入検証は未完了。**

## 対象と環境

| 項目 | 内容 |
|---|---|
| アプリ基準 | `a7511ad57e65b64d603e9cb68a20359ad0a3a4e6`、File Thingie 2.5.7 |
| 試験時の親コミット | `a59a219caf57f972c90404f2f65c88aba1fc7342` |
| ソース同一性 | 両コンテナの既存PHP 40本と作業ツリーのSHA-256一致、上流との差分なし |
| PHP／Web SAPI | 8.3.36、8.5.12／両方`apache2handler` |
| コンテナ | Debian 13、amd64、Apache 2.4.69、公式イメージのdigest固定 |
| ホストツール | Docker 29.9.2、Compose v5.6.1。詳細は`environment.json` |
| 条件・再実行 | [試験条件](STAGE2_TEST_PLAN.md)・[環境手順](STAGE2_ENVIRONMENT.md) |

環境追加物の確認結果：Compose設定の妥当性確認成功、イメージ構築・起動成功、Pythonスクリプトのコンパイル確認成功、追加PHP 3本の構文確認は両PHPで診断なし。試験用6ファイルのハッシュ・モード・アクセス可否の両環境での一致。

## ケース別結果

| ID | 名称 | 判定 | 実結果と証跡 |
|---|---|---|---|
| LINT-201 | PHP 8.3の全PHPファイル構文確認 | FAIL | 40本中、構文エラー1本、構文通過かつ非推奨通知1本、診断なし38本。[詳細](verification/stage2/2026-10-24/LINT-201/lint.json) |
| LINT-202 | PHP 8.5の全PHPファイル構文確認 | FAIL | 同上。[詳細](verification/stage2/2026-10-24/LINT-202/lint.json) |
| TS-201 | PHP 8.3の初期画面HTTP応答確認 | FAIL | 環境確認URLは200、`GET /ft2.php`は500、本文0バイト、ログインフォームなし。ログに663行目のFatal error。[結果](verification/stage2/2026-10-24/TS-201/result.json)・[ログ](verification/stage2/2026-10-24/TS-201/container.log) |
| TS-202 | PHP 8.5の初期画面HTTP応答確認 | FAIL | 環境確認URLは200、`GET /ft2.php`は500、本文0バイト、ログインフォームなし。ログに663行目のParse error。[結果](verification/stage2/2026-10-24/TS-202/result.json)・[ログ](verification/stage2/2026-10-24/TS-202/container.log) |

実施4件、PASS 0件、FAIL 4件、BLOCKED 0件。構文通過39本を機能合格とみなさない集計。HTTP本文のエラー非表示は設定による結果であり、ログ上のエラーとの矛盾なし。環境確認URLをアプリの合格証跡に流用しない判定。

## 再現した問題と修正候補

| 移行計画ID | 根拠 | 段階2の観測 | 段階3以降の対応 |
|---|---|---|---|
| C01 | `ft2.php:663`、`$val{strlen($val)-1}` | 両PHPの構文確認で失敗、HTTPでも同位置で処理停止 | 文字列オフセット構文の更新、容量換算全体の確認、両PHPでの再試験 |
| C03 | `plugins/config.plugin.php:579` | 必須引数`$currentvalues`より前の任意引数`$type`によるDeprecated通知 | 呼出し側と併せた引数宣言の修正、構文確認、プラグイン有効化時の確認 |
| C02 | `ft2.php:1721` | 両ランタイムで`get_magic_quotes_gpc`の不存在を確認。アプリ呼出し経路はC01で未到達 | 削除済み関数への依存解消、入力値保全の確認 |
| C04・C05、U01〜U04、S01〜S03、J01 | [移行計画](PHP85_MIGRATION.md) | 静的調査からの継続課題。今回の機能再現結果なし | 起動修正後、対象経路ごとの試験と確認 |

原本40本の修正なし。C01だけを一時修正した追加試験も未実施。現時点のFAILは更新前の基準結果であり、PHP対応完了の主張なし。

## 制約と未実施範囲

- 両コンテナにPDO SQLite・zlib等の存在、zip／ZipArchiveとPATH上の外部unzipの不在。ZIP展開・作成の動作試験は未実施
- 6ファイルの配置・権限確認までの実施。File Thingie経由の日本語名操作・編集・転送は未実施
- ログイン、一覧、基本ファイル操作、同梱プラグインの実行、認証・認可、ブラウザー描画・JavaScriptはNOT RUN。今回の起動失敗と後続段階の対象範囲による未実施
- さくら側の利用者確認：スタンダード、FreeBSD、選択可能な最新系列PHP 8.3、元ソース以外のプラグインなし
- 利用者提示のCLI情報：当初PHP 5.6.40／`cli`、PHP切替後PHP 8.3.36／`cli`。切替後のFile Thingie不動作も利用者報告
- Web側の詳細版・SAPI・拡張・制限値・エラーログ、FreeBSD詳細版は未確認。ローカルと同じC01が原因である可能性と、実機原因の未確定状態の区別
- FreeBSD実機の機能確認はNOT RUN。Linuxとの差異、WebとCLIの差異、さくらのファイル名・権限制約の継続確認

全証跡：[verification/stage2/2026-10-24](verification/stage2/2026-10-24/)。環境・データ・ハーネス確認：[environment.json](verification/stage2/2026-10-24/environment.json)。
