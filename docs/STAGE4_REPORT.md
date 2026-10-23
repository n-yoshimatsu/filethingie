# 段階4 実施結果

実施日：2026年10月24日。対象：`php85-support`、段階3コミット`cfbce1549547f1b21990da7648b003ce5b523ede`からの変更。

## 結果

最終版の20ケース、全件PASS。PHP 8.3.36／8.5.12、Apache 2.4.69、Linux、ローカルChrome 156.0.8078.47での確認。HTTPケース580リクエスト、ブラウザーを含む必須比較1,674件。対象経路のPHPエラー・警告・非推奨通知なし。ChromeのJavaScript例外・必要資産のHTTPエラーなし。

段階4の実装・ローカル検証・機能別対応表・移行手順の整備完了。本番配備、FreeBSD受入、対応表で無効化した機能の動作保証は対象外。

| ケース（8.3／8.5） | 確認内容 | 最終結果 |
|---|---|---|
| LINT-201／202 | PHP40本の構文・診断通知 | PASS／PASS |
| TS-201／202 | 未認証初期画面 | PASS／PASS |
| TS-401／402 | 基本操作、新CSRF・POSTログアウト仕様 | PASS／PASS |
| TS-403／404 | 境界値・不正入力・元ファイル保全 | PASS／PASS |
| TS-405／406 | ユーザー・グループ設定・操作権限 | PASS／PASS |
| TS-407／408 | ハッシュ認証、移行用平文、無効ハッシュ、旧Cookie拒否、セッションID更新、CSRF、管理権限、ハッシュ生成CLI | PASS／PASS |
| TS-409／410 | DB依存順・ログ表示／フィルター／CSV／削除・ZIP生成／展開・不正ZIP拒否 | PASS／PASS |
| TS-411／412 | ZIP拡張なし、公開領域内DBパス、旧5プラグイン有効化時の無効化通知と基本機能継続 | PASS／PASS |
| TS-413／414 | Chromeによるアップロード、長い日本語・引用符名、編集、検索、ログ並替え、ログアウト | PASS／PASS |
| TS-415／416 | CRLFの通常／AJAX編集、元名照合、リテラル%22、不正メタデータ拒否 | PASS／PASS |

[試験条件](STAGE4_TEST_PLAN.md)、[最終結果と履歴の一覧](verification/stage4/2026-10-24/summary.json)、[全証跡アーカイブ](verification/stage4/2026-10-24/stage4-evidence.tar.gz)、[内容のSHA-256一覧](verification/stage4/2026-10-24/archive-manifest.json)。

アーカイブSHA-256：`bc68715c1c66bdce06867488e0a1db6c65f77f7659f5f34381a20870644a10c2`。

## 変更内容

- 認証：`PASSWORD_HASH`／`password_hash`対応、段階的な平文設定移行、セッションID更新・期限・認証設定変更による失効、旧MD5 Cookie廃止、POSTとCSRF。
- プラグイン：PDO SQLiteによるdb／log、非公開DB配置の確認、管理操作を主ユーザーへ限定、日付フィルター・CSVの修正。ZipArchiveによるZIP生成・新規フォルダーへの安全な展開。
- 未対応機能の明示：config、fileinfo、tinymce、editorck、fckeditorの旧実装をローダーで無効化。元ファイル・データを削除する移行なし。標準テキスト編集・手動設定への移行手順。
- ブラウザー：ローカルjQuery 4.0.0、旧jQuery／TableSorter配布物の削除、DOM値としてのファイル名設定、長い実ファイル名の保持、アップロード元名照合、改行形式の保持、スペース変換チェックの修正。
- 日本語：上流の日本語翻訳ファイルに含まれていた文字化け・制御文字の修復、画面での確認。

[設定・認証・プラグインの移行手順](STAGE4_MIGRATION.md)、[依存とライセンス](DEPENDENCIES.md)。

## 試験中の不一致と修正

| 発見 | 証跡 | 修正・再確認 |
|---|---|---|
| 引用符付きアップロード名の`%22`化 | `browser1/php83`・`browser1/php85`、TS-413／414のFAIL | ブラウザーの元名フィールドとmultipart名の照合、危険名・不一致の拒否。最終TS-413／414・415／416で内容と名前の一致 |
| 通常保存時のLFからCRLFへの変化 | `browser2/php83`・`browser2/php85`、TS-413／414のFAIL | 編集フォームに元の改行形式を記録、通常／AJAX保存に反映。LF・CRLFそれぞれの確認 |
| 省略名によるファイル操作、文字化け、管理リンクの白背景上の白文字 | ソース確認およびブラウザー画像の確認 | 実名属性、日本語翻訳、ヘッダー配置・ログ表幅の修正。最終版画像と操作結果での確認 |

途中結果を含む40回の記録、うち初回・2回目のブラウザーFAIL計4回の保持。失敗した試行での後続未実施範囲をPASSへ読み替えない扱い。失敗時のブラウザーハーネスもハッシュ一致を確認して保存。初期の途中版のアプリ全体はアーカイブへの同梱なし、観測結果と当時のソースハッシュによる対応付け。

段階3の元ケース・証跡の保持。段階4の回帰は、画面発行CSRFトークンとPOSTログアウトを使用する新ケース。ファイル内容・権限・サイズ境界の期待結果は段階3と同一。依存欠落ケース実行後のTS-415／416追加によるハーネスハッシュ差分あり、依存欠落ケース本体とアプリの変更なし。

## 再実行

本番データを含まないローカルfixtureでの手順。HTTP・ブラウザーケースの実行前の各ID・名称表示が必要な運用。証跡先は毎回新規ディレクトリ。

```bash
docker compose up -d --build --force-recreate
python3 tools/stage2.py lint php83 --output artifacts/check4/lint-php83
python3 tools/stage2.py startup php83 --output artifacts/check4/startup-php83
python3 tools/stage4.py basic php83 --output artifacts/check4/basic-php83
python3 tools/stage4.py negative php83 --output artifacts/check4/negative-php83
python3 tools/stage4.py groups php83 --output artifacts/check4/groups-php83
python3 tools/stage4.py auth php83 --output artifacts/check4/auth-php83
python3 tools/stage4.py plugins php83 --output artifacts/check4/plugins-php83
python3 tools/stage4.py content php83 --output artifacts/check4/content-php83
npm ci --prefix tools/browser --ignore-scripts
node tools/browser/stage4.cjs php83 artifacts/check4/browser-php83
```

同じ順序で`php85`も実行する手順。Chromeパスは既定`/usr/bin/google-chrome`、変更時は`FT_CHROME`で指定。基本・プラグイン・ブラウザーケースの同一コンテナへの再実行前に`--force-recreate`で初期データへ戻す必要あり。他サービス・実データへの適用なし。

任意依存欠落の確認：

```bash
docker compose -f compose.yaml -f dev/compose.stage4-degraded.yaml up -d --build --force-recreate
python3 tools/stage4.py degraded php83 --output artifacts/check4/degraded-php83
python3 tools/stage4.py degraded php85 --output artifacts/check4/degraded-php85
docker compose down
```

通常構成への復帰は追加Composeファイルを指定しない`docker compose up -d --build --force-recreate`。通常構成のZIP拡張は8.3側1.22.3、8.5側1.22.8。欠落構成はZipArchiveなし。段階2当時のZIP拡張なし構成とは異なる環境。PHP基底イメージのdigestは段階2と同一、libzipビルド依存を追加したDockerイメージ識別情報は各結果に記録。

## 画面証跡

[PHP 8.3ファイル画面](verification/stage4/2026-10-24/php83-files.png)、[PHP 8.3ログ画面](verification/stage4/2026-10-24/php83-logs.png)、[PHP 8.5ファイル画面](verification/stage4/2026-10-24/php85-files.png)、[PHP 8.5ログ画面](verification/stage4/2026-10-24/php85-logs.png)。

## 段階5への引継ぎ

- さくら・スタンダード／FreeBSD／PHP 8.3上の配置・受入はNOT RUN。Web側PHP詳細版・SAPI・拡張・権限・HTTPSの未確認。
- セッション期限の実時間経過、認証設定の実機変更、HTTPSでのSecure Cookie、他ブラウザーの総合試験はNOT RUN。
- 本番の既存config・ユーザー・グループ・DBは未取得。実際の有効プラグイン、SQLiteデータ移行、公開領域外DBの配置可能性の確認が必要な状態。
- PHP 8.3／8.5のCI、URL取得の接続先・容量・上書き制限、同時操作・大量データ・サーバー固有制限は後続確認。
- config／fileinfo／旧外部エディター3種、編集ロックは未対応。今回の試験で検証した範囲を全機能・本番利用の保証へ拡張しない扱い。
