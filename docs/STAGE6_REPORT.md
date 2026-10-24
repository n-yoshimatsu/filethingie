# 段階6 本番移行・受入・配布準備の結果

2026年10月25日更新。本番切替SA-608と本番受入SA-609はPASS、通常利用の再開案内済み。正式タグ・GitHub Releaseは準備中。以下の準備段階の未実施記載は各時点の経緯、最新結果は末尾の本番受入節。

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

ローカル15ケース484比較のPASS、利用者提供証跡によるSA-601〜605のPASS。ローカル試験環境はLinuxコンテナー。さくらFreeBSDでは保全コピーと隔離復元を確認済み、表示差分の取得も確認済み、移行リハーサルは初回配置でFAIL、修正版の配置・337ファイル照合でPASS。SA-606はChrome84比較・利用者提供のサーバー内部19比較の計103比較PASS。ツールの合格と本番移行完了の区別。

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

正式タグとGitHub Releaseの公開。本番の最終停止バックアップ・切替・再確認は完了。現在の公開URL・Basic認証・既存保存データ・表示変更の維持確認済み。[表示差分の確認手順](STAGE6_DISPLAY.md)。

認証の未解決参照は取得した事実として記録、現在の認証不良とする断定なし。旧PHPアプリを戻してもPHP 8.3での稼働回復とは別問題のため、旧ソースの保全とサービス復旧の区別。

## Basic認証リハーサル資材と検証経緯

[修正版配置キット](verification/stage6/2026-10-24/sakura-rehearsal-relative-dir.zip)、[SHA-256](verification/stage6/2026-10-24/sakura-rehearsal-relative-dir-SHA256SUMS)、[配置範囲](STAGE6_REHEARSAL.md)。段階5固定ソースを基準に4ファイルの表示対応を追加したキット。従来の配布候補ZIPは固定証跡として保持、更新版リハーサルキットとは別資材。さくらのWeb受入前、PREPAREDを稼働合格と扱わない状態。

TS-610初回は試験用configの必須項目不足によるHTTP 500。完全なサンプル設定を基にした同じBasic-only条件へfixtureを修正。2回目はDevTools経由のテキストレスポンスがChromeの文字コード解釈後のUTF-8になり、元バイト列と不一致。実保存操作の代用として誤った観測方法だったため、ChromeのAlt＋クリックによる通常ダウンロードと保存ファイルのSHA-256へ訂正。条件や同一性判定の緩和なし。[途中の失敗記録](verification/stage6/2026-10-24/rehearsal-trials)。文字コード未指定の直接テキスト表示はサーバー設定・ブラウザー依存、保存内容の一致と表示時の符号化の区別。

最終画面の確認で保護用.htaccessの一覧表示を検出、コピー先のFILEBLACKLISTへ追加。両PHPの配置・ブラウザーケースを再実行、各23＋46比較PASS。正常なCSRF付きでも同ファイルの削除要求400・ファイル保持を確認。既存バックアップ処理の共用化に伴うTS-604／605の各34比較再実行もPASS。

公開証跡の画面は人工fixture、実データ・サイト固有の表示文字列・本番認証情報の非掲載。初期のPHP構文チェックで検出した文字列連結の誤りは、機能試験開始前に修正済み。

表示設定なしの既存Chrome試験TS-413／414も両PHPで各18比較PASS。[PHP 8.3証跡](verification/stage6/2026-10-24/default-browser-php83.json)、[PHP 8.5証跡](verification/stage6/2026-10-24/default-browser-php85.json)。同じソースからのキット再生成によるZIP一致、同梱PHP全ファイルの両版での構文確認も完了。

更新版コミットff788bd02effa0bb4673056759b23a429a4133a8の[総合CI run 40098383165](https://github.com/n-yoshimatsu/filethingie/actions/runs/40098383165)もPHP 8.3／8.5の両ジョブsuccess。[完了記録](verification/stage6/2026-10-24/rehearsal-ci.json)。本番切替やさくらでのリハーサル実施を意味する結果ではない範囲。

## SA-605の初回失敗と相対保存先への対応

初回実機配置の判定はFAIL。ツール出力はINCOMPLETE、非公開の準備先作成後・公開前の停止。[受領結果と診断](verification/stage6/2026-10-24/sakura-rehearsal-incomplete.json)。LOGIN=false・HIDEFILEPATHS=false、DIR=./filesと保全コピー内filesへの解決一致を利用者提供の追加出力で確認。移行ツールがfilesとの文字列一致を要求したことが原因。既存設定の誤りではない事象。

修正はCLI移行ツールの受入条件と不一致時の設定名表示のみ。DIRのfilesと./filesを受入れ、元の表記の保持。LOGIN／HIDEFILEPATHSの厳密なfalse条件、動的設定・管理外パスの拒否、複製データの照合の維持。アプリ46ファイル・manifestは前回のCI合格版ff788bdとバイト単位で同一。旧キットは失敗記録の固定資材として保持。

TS-612／613は各35比較PASS、./filesでの複製先への解決・設定保持・他の保存先と認証条件変更の拒否。TS-614／615は同設定でのChrome操作各46比較PASS。元のfilesケースTS-608／609も各23比較PASS。今回6ケース208比較、ZIP再生成の一致。[資材と検証の対応](verification/stage6/2026-10-24/relative-dir-identity.json)。既存ケースの結果は履歴として保持、ローカル成功をSA-605のPASSへ転記しない扱い。この時点では修正版の実機再配置・SA-606のWeb確認・本番切替が未実施。後続結果は次節。

## SA-605の実機再実行

利用者提供の2026年10月24日13:45:33 UTCの出力によるPASS。修正版167ec018の配置完了PREPARED、管理337ファイルの内容・権限・更新時刻の一致。設定のセットアップ中の非実行、既存ファイルの変更なし、既存Basic認証・追加アプリログインなし・表示移植・同名案内訂正の記録。[再実行証跡](verification/stage6/2026-10-24/sakura-rehearsal-prepared.json)。初回FAILの証跡保持。配置時点ではSA-606のWeb受入と本番切替が未実施。後続のWeb結果は次節。

## SA-606の実機Chrome操作

既存Basic認証だけによる実機HTTPSのChrome操作、最終試行84比較PASS。[Web結果](verification/stage6/2026-10-24/sakura-rehearsal-browser.json)。追加アプリログインなし、タイトル・見出し・案内・配色・幅・メニュー・faviconの引継ぎ。新規専用フォルダー内での日本語／空ファイルのアップロードと実保存SHA-256、同名拒否・元内容維持、名前変更・編集保存・検索・移動・削除。未認証401、設定・一覧直接取得403、専用フォルダー内へのCSRFなし要求403。試験フォルダー・ファイルのUI削除完了、本人限定一時認証情報の削除完了。

初回は編集画面のPOST描画でURLにdirがないことによる試験側の操作先制限の停止。フォーム内の実送信先の確認へ修正。2回目は試験側の相対移動先の指定誤りで停止。正しい相対値へ修正、画面遷移時のHTTP 200確認を追加。判定基準・製品コードの変更なし。[途中試行・診断・後片付け](verification/stage6/2026-10-24/sakura-rehearsal-trials/diagnosis.json)。2回目の「移動元からの除去」は一覧存在確認不足のため、成功根拠から除外。最終試行では移動先の表示・保存ハッシュ・削除までの確認。

Web操作終了時点では、サーバー内の配備ファイル・複製337ファイル・設定・権限・PHPログが未照合。Web部分のPASSと、当時のSA-606全体INCONCLUSIVEの区別。後続のCLI照合結果は次節。本番切替なし。[読取り専用照合キット](verification/stage6/2026-10-24/sakura-rehearsal-verifier.zip)、[SHA-256](verification/stage6/2026-10-24/sakura-rehearsal-verifier-SHA256SUMS)。CLIツールはPHP 8.3／8.5のローカル自己検証で各11比較PASS。正常系19項目の一致、内容・権限・残存ファイル・ログ・リンクの異常検出、検証中の不変性。実機証跡とは別の記録。

修正版167ec018の[CI run 40099368310](https://github.com/n-yoshimatsu/filethingie/actions/runs/40099368310)もPHP 8.3／8.5の両ジョブsuccess。[完了記録](verification/stage6/2026-10-24/relative-dir-ci.json)。

## SA-606のサーバー内照合と統合判定

利用者提供の2026年10月24日14:09:34 UTC（23:09:34 JST）のCLI出力による19項目PASS。[内部照合証跡](verification/stage6/2026-10-24/sakura-rehearsal-server.json)。配備46ファイル、複製337ファイルの内容・通常権限・更新時刻、設定・表示抽出・Basic認証・保護・favicon・非公開権限の一致。試験データ残存0、PHPログ0バイト、公開先への旧診断・管理ファイル混入0。管理ルート自身の更新時刻だけは保護設定・試験フォルダー作成削除に伴う変更として比較対象外。

[SA-606統合判定](verification/stage6/2026-10-24/sakura-rehearsal-acceptance.json)はPASS。Chrome84比較と内部19比較の計103比較。初回CLIのWEB_ROOT引数欠落は開始前のINCOMPLETE、正しい引数で再実行済み。[初回呼出しの記録](verification/stage6/2026-10-24/sakura-rehearsal-trials/server-incomplete.json)。既存の途中試行記録の保持。

更新版ad58fec5の[CI run 40100894214](https://github.com/n-yoshimatsu/filethingie/actions/runs/40100894214)も両PHPでsuccess。[CI証跡](verification/stage6/2026-10-24/acceptance-ci.json)。さくら実機でのPHP 8.5試験や本番切替を意味しない結果。

## 本番切替前の資材準備

利用者回答により保存データの書込み経路はFile Thingie画面のみ。新規非公開フォルダーへアプリ46ファイルと設定・保護ファイルの計49ファイルを準備するツールの作成。現在のURL、files保存先、Basic認証ファイルの維持方針。[具体的な配置・停止・戻し方の計画](STAGE6_CUTOVER.md)。

準備ツールはPHP 8.3／8.5の使い捨てfixtureで各20比較PASS。原本・検証版の全エントリー不変、既存データの追加を含む最新状態の取得、設定非実行、全準備先0700／0600、レビュー後のソース変更・副作用設定・未レビュー保護設定・リンク先への配置の拒否。[PHP 8.3自己検証](verification/stage6/2026-10-24/cutover-prepare-selfcheck-php83.json)、[PHP 8.5自己検証](verification/stage6/2026-10-24/cutover-prepare-selfcheck-php85.json)。準備版時点では実機SA-607が未実施、停止・配置・再開は別ツールの対象。後続結果は次節。


## SA-607の実機準備と切替ツールの検証

利用者提供の2026年10月24日15:21:10 UTC（25日00:21:10 JST）のPREPARED出力によるSA-607 PASS。受入済みリハーサルの再照合PASS、49対象（既存45・新規4）の非公開資材準備。保存337ファイル・1,154,034,640バイトと元ファイル・Basic認証の不変。設定非実行、停止・本番変更・最終停止バックアップ・切替なし。[受領記録](verification/stage6/2026-10-25/sakura-cutover-prepared.json)。

切替ツールのローカルTS-616／617は各36比較PASS、TS-618／619は各107比較PASS、合計286比較。停止HTTP 503・認証継続・最終バックアップ・3ファイル配置後の中断と再実行・入口を最後に再開する手順・Chromeの通常操作・配置データと空ログの照合。復元時に切替後追加データを保持、旧ソース復元後の503維持。[固定版キット・証跡一覧](verification/stage6/2026-10-25/cutover-summary.json)、[具体的な操作と復旧手順](STAGE6_CUTOVER.md)。

初回TS-616／617は再開HTTP 200の条件でFAIL、後続は未実施。CLIとWebの人工環境アカウント差による0600設定の読取り不可。CLIをWebと同じ利用者とする前提の修正、製品コード・判定基準変更なし。同一条件の再試験で全項目PASS。[初回証跡と診断](verification/stage6/2026-10-25/trials/diagnosis.json)。

準備版f2a68bd2の総合CIは両PHPでsuccess。[CI記録](verification/stage6/2026-10-25/cutover-prepare-ci.json)。新しい切替ツールの機能確認は上記ローカル試験による別証跡。実機SA-608の停止・最終保全・切替、SA-609の本番Web受入・内部照合、正式配布は未実施。

## SA-608の本番切替

利用者による開始・進行中操作終了の確認後、固定版e02ca328の切替キットを使用。停止は2026年10月25日00:55:35 JST、最終バックアップ完了00:59:29、配置完了01:01:11、入口再開01:03:06。準備ID・最終バックアップIDの各出力間一致。

停止直後と配置後の両時点で、未認証401・認証後GET／空POST 503、本文・Cache-Control・Retry-AfterをHTTPSで照合。受入済み46ソースと設定・保護を含む49対象の配置。保存データ・Basic認証ファイルへのツール書込みなし。停止時バックアップはオンライン保全コピーと別の新規記録。[SA-608統合証跡](verification/stage6/2026-10-25/sakura-production-acceptance.json)。

## SA-609の本番Web受入と最終内部照合

実機HTTPS・Chromeの通常操作87項目PASS。Basic認証・追加ログインなし・表示引継ぎ、日本語／空ファイルのアップロードと実保存ハッシュ、同名拒否、作成・名前変更・編集・検索・移動・削除・CSRF拒否、設定・旧診断／管理ファイルの保護。新規専用フォルダー内だけでの変更とUIからの全試験データ削除。[Web証跡](verification/stage6/2026-10-25/sakura-production-browser.json)。

利用者提供の2026年10月24日16:15:45 UTC（25日01:15:45 JST）の最終CLI出力で7項目PASS。配置49ファイルの不一致0、保存337ファイルと停止時バックアップの一致、Basic認証ファイル・faviconの不変、PHPログ0バイト、非公開設定0600、メンテナンス入口の解除。[内部証跡](verification/stage6/2026-10-25/sakura-production-verify.json)。SA-609はWeb87＋内部7＝94項目PASS、SA-608も必要なCLI・HTTP証跡を合わせてPASS。

初回2回のverify呼出しはUsage表示による検査開始前のINCOMPLETE。引数診断ではCLI・register_argc_argv=1・verify/testの受領を確認。実際の初回コマンドとの差は未特定、利用者の入力誤りやPHP設定不備とする断定なし。同じ固定版のftc_executeへverify・HOME・準備IDを明示して最終照合を実施。対象・ロック・7項目・合否条件の変更なし。[初回](verification/stage6/2026-10-25/sakura-production-verify-incomplete.json)、[2回目](verification/stage6/2026-10-25/sakura-production-verify-incomplete-2.json)、[引数診断](verification/stage6/2026-10-25/sakura-production-argument-diagnostic.json)、[ローカルでの呼出し等価性確認](verification/stage6/2026-10-25/cutover-cli-dispatch.json)。追加診断を実機Web再試験として数えない集計。

最終照合後に通常利用の再開案内。これらは照合時点の結果、以降の通常アップロード・編集をバックアップ不一致とする継続監視ではない範囲。固定版e02ca328の[総合CI](https://github.com/n-yoshimatsu/filethingie/actions/runs/40108828217)もPHP 8.3／8.5の両ジョブsuccess。

## 後片付けと保管対象

- 本番試験フォルダー・試験データ：通常UIによる削除と最終データ一致の確認済み。
- 作業端末の一時Basic認証情報：ファイルと空の親ディレクトリの削除済み。[後片付け記録](verification/stage6/2026-10-25/sakura-production-cleanup.json)。
- 本番参照中の非公開準備フォルダー：config.php・PHPログ・切替計画の継続保管。削除不可の現用配置。
- 停止時バックアップ、従来の保全コピー・隔離復元：復旧と検証のための継続保管。
- 段階5プレビュー・段階6リハーサル、サーバー上の取得用キット：今回の削除対象外。将来の片付け時に現用設定・バックアップと区別し、個別の配置を再確認する対象。最初のPHP診断ページは以前の利用者回答で削除済み。
- 実環境の画面・固有表示・実パス・認証情報・配備ID：公開証跡への非掲載。公開記録には合否と件数、非機密の照合情報だけを記載。

## TS-620の正式配布物照合

配布識別名php83-85-20261025、ソース固定元e02ca328c9668f0e4ce1374751f25647fdaa57f6。受入済みアプリ46ファイルとバイト単位で一致。導入・更新・復元手順、本体・jQueryのライセンス、パスワードハッシュ生成ツール、manifestの同梱。実設定・認証・保存データ・試験用アカウントの非同梱。

同じ入力からのZIP再現、受入版一致、ライセンス、非混入、全ファイルmanifest、手順、SHA-256、既存出力への上書き拒否の8項目PASS。[照合証跡](verification/stage6/2026-10-25/release-package.json)、[配布ZIP](releases/2026-10-25/filethingie-php83-85-20261025.zip)、[SHA-256](releases/2026-10-25/SHA256SUMS)、[リリースノート](RELEASE_NOTES.md)、[導入・更新・復元手順](RELEASE_GUIDE.md)。本番の再配置は不要。

正式タグ・GitHub Releaseは未公開。準備済み資材と公開操作の区別。[公開準備状況](verification/stage6/2026-10-25/release-readiness.json)。GitHubプラグインにRelease作成機能なし、CLI認証は無効のため再認証待ち。

再現コマンド（新規出力先の指定）：

```bash
python3 tools/release-package-check.py artifacts/stage6/reproduce-release
```
