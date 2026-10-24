# 段階6 準備段階の結果

2026年10月24日。段階6全体は進行中、本番の切替・正式タグは未実施。

## 検証済みの範囲

| ID | 対象 | 結果 | 根拠 |
|---|---|---|---|
| SA-601 | さくら本番の読取り専用棚卸し | PASS | 利用者提供のCLI出力、設定候補・認証配置・337ファイル／約1.15 GBの把握。実効認証や切替の判定対象外 |
| TS-601／602 | 棚卸しツール、PHP 8.3.36／8.5.12 | PASS | 共用解析処理への変更後、各32比較一致。秘密情報非出力・設定非実行・原本不変 |
| TS-603 | 配布候補の再現と対象確認 | PASS | 8比較一致。同じ固定元からのZIP再現、46ファイルの段階5ハッシュ一致、ライセンス包含、設定・認証・データ・受入入力の非同梱 |
| TS-604／605 | 保全コピーと隔離復元、PHP 8.3.36／8.5.12 | PASS | 各34比較一致。日本語・空・12 MiBファイル、隠しファイル、内部／外部／子フォルダー参照の認証関連。内容・通常権限・更新時刻の復元一致、原本不変、改変・逸脱・リンク・動的設定・未完了コピーの拒否 |
| SA-602 | さくら本番の保全コピー | PASS | 利用者提供PHP 8.3.36出力。543ファイル・1,156,565,643バイトと外部1ファイルの保全、原本前後・コピーハッシュ一致、設定非実行・本番変更なし |
| SA-603 | 本番保全コピーの隔離復元 | PASS | 利用者提供PHP 8.3.36出力。SA-602のコピーからの543ファイルと追加1ファイルの新規非公開復元、内容・通常権限・更新時刻の一致、本番変更なし |
| TS-606／607 | 既存表示差分の抽出 | PASS | PHP 8.3.36／8.5.12で各22比較一致。タイトル・色・CRLF、パス置換、認証語による出力保留、設定非実行、原本不変、改変・リンク拒否、後片付け |
| SA-604 | 既存表示差分の確認 | PASS | 利用者提供の差分レビュー、表示の引継ぎ・案内訂正・ファイル名検査の維持方針の確定 |
| TS-608／609 | Basic認証だけのリハーサル配置 | PASS | PHP 8.3／8.5で各23比較、複製一致・設定非実行・原本不変・未対応認証や改変コピーの停止 |
| TS-610／611 | Basic認証だけの画面・通常操作 | PASS | 両PHP＋Chromeで各46比較、認証・表示・日本語／空ファイルの保存ハッシュ・同名拒否・作成／名前変更／移動／編集／検索／削除・CSRF・保護設定・PHPログ |

ローカル11ケース322比較のPASS、利用者提供証跡によるSA-601〜604のPASS。ローカル試験環境はLinuxコンテナー。さくらFreeBSDでは保全コピーと隔離復元を確認済み、表示差分の取得も確認済み、移行リハーサルは未実施。ツールの合格と本番移行完了の区別。

最初のコピー試験は両PHPで34比較PASS。その後、子フォルダーの.htaccessからの認証ファイル参照を追加保全対象とした同じシナリオの再実行も各34比較PASS。掲載証跡は最終版。失敗を省略したPASS記録ではない経緯。

[最新結果一覧とツールハッシュ](verification/stage6/2026-10-24/preparation-summary.json)。旧棚卸し試験結果は同ディレクトリのsummary.jsonに保持。

SA-602の上流比較は146ファイル中、完全一致22、改行のみ121、変更あり2、欠落1。index.php／css/ft.cssの変更内容はSA-604でレビュー済み、locales/tr.phpは欠落。Basic認証の未解決参照1件も継続記録。[保全コピー証跡](verification/stage6/2026-10-24/sakura-backup.json)。バックアップ識別名は公開証跡から非公開化、原本の添付SHA-256を保持。

SA-603は利用者提供の2026年10月24日12:28:21 UTCの出力。前回保全IDと復元元IDの一致、543ファイルと追加1ファイルの内容・通常権限・更新時刻の一致、本番変更なし。[隔離復元証跡](verification/stage6/2026-10-24/sakura-restore.json)。利用者の「表示やデザインを変更した」の回答を受領、実際の2ファイルの差分内容はSA-604で確認済み。

## 配布候補

アプリ固定コミット`3ab04adb323c1f5fe2d8b1cd144b6cbb3d0a1eb4`、index.phpへのファイル名変更のみ、バイト列の変更なし。サンプル設定とライセンスを含む46ファイル。

- [アプリZIP](verification/stage6/2026-10-24/filethingie-php83-85-candidate.zip)
- [保全・復元用CLIキット](verification/stage6/2026-10-24/sakura-backup-kit.zip)
- [SHA-256](verification/stage6/2026-10-24/SHA256SUMS)
- [アプリmanifest](verification/stage6/2026-10-24/candidate-manifest.json)

正式リリース前の候補。本番への直接上書き展開用ではない状態。保全キットには本番設定や認証情報の同梱なし。

## 再実行

リポジトリ直下、既存のPHP検証用Dockerイメージを使う手順。出力先は未作成の名前を指定。

```bash
python3 tools/stage6-package-test.py artifacts/stage6/reproduce-package
```

TS-604／605のfixtureは公開ソース比較用manifestのみ人工データへ差替えた隔離キット。実際のツールのPHPコードは配布キットと同一、元のPHPコードや判定処理の差替えなし。

```bash
mkdir -p artifacts/stage6/reproduce-kit
unzip -q artifacts/stage6/reproduce-package/first/sakura-backup-kit.zip -d artifacts/stage6/reproduce-kit
cp docs/verification/stage6/2026-10-24/backup-fixture-manifest.json artifacts/stage6/reproduce-kit/upstream-manifest.json
docker compose run --rm --no-deps -T -v "$PWD:/workspace:ro" --entrypoint php php83 /workspace/tools/stage6-backup-test.php /workspace/artifacts/stage6/reproduce-kit
docker compose run --rm --no-deps -T -v "$PWD:/workspace:ro" --entrypoint php php85 /workspace/tools/stage6-backup-test.php /workspace/artifacts/stage6/reproduce-kit
```

## 残る作業

さくらで複製データを使うBasic認証だけのリハーサルSA-605／606、最終停止バックアップ・切替・再確認、正式リリース。現在の公開URL・Basic認証・既存保存データ・表示変更の維持方針。[表示差分の確認手順](STAGE6_DISPLAY.md)。

認証の未解決参照は取得した事実として記録、現在の認証不良とする断定なし。旧PHPアプリを戻してもPHP 8.3での稼働回復とは別問題のため、旧ソースの保全とサービス復旧の区別。

## Basic認証リハーサル資材と検証経緯

[配置キット](verification/stage6/2026-10-24/sakura-rehearsal.zip)、[SHA-256](verification/stage6/2026-10-24/sakura-rehearsal-SHA256SUMS)、[配置範囲](STAGE6_REHEARSAL.md)。段階5固定ソースを基準に4ファイルの表示対応を追加したキット。従来の配布候補ZIPは固定証跡として保持、更新版リハーサルキットとは別資材。さくらのWeb受入前、PREPAREDを稼働合格と扱わない状態。

TS-610初回は試験用configの必須項目不足によるHTTP 500。完全なサンプル設定を基にした同じBasic-only条件へfixtureを修正。2回目はDevTools経由のテキストレスポンスがChromeの文字コード解釈後のUTF-8になり、元バイト列と不一致。実保存操作の代用として誤った観測方法だったため、ChromeのAlt＋クリックによる通常ダウンロードと保存ファイルのSHA-256へ訂正。条件や同一性判定の緩和なし。[途中の失敗記録](verification/stage6/2026-10-24/rehearsal-trials)。文字コード未指定の直接テキスト表示はサーバー設定・ブラウザー依存、保存内容の一致と表示時の符号化の区別。

最終画面の確認で保護用.htaccessの一覧表示を検出、コピー先のFILEBLACKLISTへ追加。両PHPの配置・ブラウザーケースを再実行、各23＋46比較PASS。正常なCSRF付きでも同ファイルの削除要求400・ファイル保持を確認。既存バックアップ処理の共用化に伴うTS-604／605の各34比較再実行もPASS。

公開証跡の画面は人工fixture、実データ・サイト固有の表示文字列・本番認証情報の非掲載。初期のPHP構文チェックで検出した文字列連結の誤りは、機能試験開始前に修正済み。

表示設定なしの既存Chrome試験TS-413／414も両PHPで各18比較PASS。[PHP 8.3証跡](verification/stage6/2026-10-24/default-browser-php83.json)、[PHP 8.5証跡](verification/stage6/2026-10-24/default-browser-php85.json)。同じソースからのキット再生成によるZIP一致、同梱PHP全ファイルの両版での構文確認も完了。
