# 段階6 本番切替の準備と実施計画

2026年10月25日更新。SA-605／606のリハーサル受入とSA-607の実機非公開準備が完了。利用者回答による書込み経路はFile Thingieの画面のみ。停止・本番配置・再開・ソース復元のツールはローカル検証済み、実機の停止・切替は未実施。

## 維持するものと変更対象

| 対象 | 切替時の計画 |
|---|---|
| 公開URLとアプリのディレクトリ | 現在の場所の継続。ディレクトリ全体の移動なし |
| 保存データと直接リンク | 現在のfiles配下の継続。リハーサルの古いコピーを本番へ戻す操作なし |
| Basic認証 | 元のrealm・AuthUserFile・Require valid-userの維持。認証ファイルの書換え・アプリ内部ログインの追加なし |
| 更新ソース | 受入済み46ファイルの同一バイト列。アプリ実装への追加変更なし |
| 公開config.php | 非公開の設定を参照する短いPHPファイルへ置換する計画。公開ファイル0600、参照先の親0700・設定0600 |
| 表示 | 受入済みの表示設定と同名拒否案内の維持。faviconの既存配置の継続 |
| アプリ直下.htaccess | Basic認証を維持し、設定・旧診断・管理ファイルのHTTP取得を拒否する計画。users.sh等のCLI配置の維持 |
| files/.htaccess | 受入済みと同じ直接取得保護の追加。保存データの内容の変更なし |
| PHP設定 | 既存php.iniの維持。非公開ログへのerror_reporting／log_errors設定の追加。サーバーのPHP版・モード切替なし |

配置予定は46ソース＋config.php＋2箇所の.htaccessの計49ファイル。既存・新規の内訳は実機準備結果による確定。全体ディレクトリへのZIP上書き展開は手順の対象外。

## 実装済み：SA-607 非公開資材準備

```text
php sakura-cutover-prepare.php HOME REHEARSAL_ID
```

対象公開ルートは既知のHOME/www。既存の非公開リハーサル記録から元アプリの場所を取得し、リハーサル内部照合の再実行。元アプリのソース・設定・表示・faviconにレビュー後の変更があれば停止。管理データへの追加・更新は現在の状態として棚卸し、同じ場所で維持する方針。保存先の制御ファイル・配置先のリンク・動的設定は個別レビューの対象。

出力先は新規のHOME/.ft-stage6-cutover-*のみ。new/に49ファイルの候補、before/に変更予定の既存ファイル、config.php・php-error.log、plan.jsonとそのSHA-256を持つCOMPLETE.json、summary.json。全ディレクトリ0700、全ファイル0600。公開先での予定権限はplanに別記録。将来の停止用index.phpの候補も非公開での保存のみ。

準備前後の変更予定ファイル・保存データ・favicon・認証ファイルの一致確認。config.phpの実行なし。Web上の変更・利用停止・切替なし。before/は変更対象だけの事前保全であり、書込み停止中の最終バックアップの代用ではない扱い。

PREPAREDはこの資材準備だけの完了状態。準備フォルダーは後続での非公開設定置場の候補を兼ねるため、切替後の一括削除対象にしない方針。利用開始前の実機配置確認を後続工程へ分離。

## 停止・最終保全・配置・再開の順序

1. 準備結果に基づく対象の確定、停止時間帯と進行中のアップロード・編集がないことの確認。第三者への通知は利用者側の実施。外部の自動通知なし。
2. 元index.phpの内容・権限・更新時刻と準備記録の一致確認、停止前ソースの非公開保全。Basic認証を残したまま、入口をHTTP 503のメンテナンス表示へ切替。Webでの実際の503確認。待ち時間の経過だけを既存処理の完了証拠にしない扱い。
3. 書込み停止中のアプリ・設定・Basic認証関連・保存データの新規最終バックアップ。コピー前後とコピー先のハッシュ・権限・更新時刻の照合。停止用indexと停止前indexの記録を区別し、戻す対象の対応を記録。
4. 入口の停止状態を維持し、非公開資材から対象ファイルだけを配置する手順。保存データ本体への書換えなし。途中失敗時は停止状態を維持し、進捗記録から対象を特定。複数ファイル全体の原子的更新を保証する説明なし。
5. 配布46ファイル、設定参照、Basic認証、保護ファイル、保存データ、権限の再照合後の入口再開。本番URLでBasic認証・表示・新規専用フォルダー内の基本操作と保存内容、PHPログの確認。
6. 合否・切替時刻・最終バックアップ・配置した版の記録。正式配布と、検証用環境を含む後片付けの対象確定。

停止以降のツールはPHP 8.3／8.5の使い捨て環境で検証済み。実機の停止時間帯と進行中の操作の終了確認後に、以下の各段階を個別に実施する手順。非公開準備の成功だけを本番切替の成功とする扱いなし。

## 問題発生時の扱い

入口の停止状態を維持し、アプリ・設定の変更箇所を事前保全と照合する方針。最終バックアップは保管し、切替後に新規保存されたデータへの自動上書きなし。復旧時に保存データを戻す必要が生じた場合は、停止後の最新データも別に保全したうえで差分と対象を確定する工程。

旧ソースはPHP 8.3で不動作との利用者報告あり。旧ソースを戻しただけではサービス復旧の保証なし。旧index.phpを機械的に公開再開する操作、PHP 5.6への自動切戻し、リハーサルの古い保存データの本番転用は計画に含めない扱い。停止解除は動作する構成の確認後。

## 準備ツールの検証と再生成

PHP 8.3／8.5の既存の使い捨てfixtureを対象とした各20比較の自己検証。非公開先への限定、原本全体の不変、49資材の内容、Basic参照、権限、現在データの追加保持、ソース変更・設定副作用・制御ファイル・リンクの拒否。実機の停止・切替や、新たに追加する旧診断ファイル保護のWeb挙動は、この自己検証の判定対象外。

```text
python3 tools/build-stage6-cutover-prepare.py NEW_ZIP
php tools/stage6-cutover-prepare-check.php LOCAL_KIT_DIRECTORY
```

自己検証スクリプトは/tmp/ft-rehearsal-localのローカル人工fixtureだけを対象とする制限。実機への同スクリプトの配置・実行なし。キット元はSHA-256を固定した受入済みリハーサルZIP、再生成した46アプリファイルのハッシュ一致。対象実機SA-607は利用者提供の2026年10月24日15:21:10 UTCのPREPARED出力によりPASS。49対象（既存45・新規4）、管理337ファイル・1,154,034,640バイト、元ファイル・認証の不変、停止・切替なしの確認。


## 固定版の切替ツール

[切替キット](verification/stage6/2026-10-25/sakura-cutover.zip)、[SHA-256](verification/stage6/2026-10-25/SHA256SUMS)、[検証記録](verification/stage6/2026-10-25/cutover-summary.json)。ZIPを新規の非公開フォルダーへ展開したうえでの利用。受入済みの46アプリファイルは既存リハーサルと同一バイト列、実機でPREPAREDとなった非公開計画への適用。公開領域へのZIP全体の展開なし。

CLIの引数は操作名・ホーム・準備IDの3つ。以下の`KIT`と`PREPARATION_ID`は各実機の固定値への置換箇所。CLIとWebが同一利用者として0600の設定を読める環境、公開先と非公開資材が同じファイルシステムの環境が前提。引数省略時の自動推測なし。

| 操作 | コマンド | 正常終了後の状態と確認 |
|---|---|---|
| 状態確認 | `php KIT/sakura-cutover.php status "$HOME" PREPARATION_ID` | `PREPARED`等の現在状態。Web状態とは別の観測 |
| 停止 | `php KIT/sakura-cutover.php stop "$HOME" PREPARATION_ID --writers-idle` | `STOPPED`、入口のみ503。フラグは実際のアップロード・編集等の終了を確認した後の指定 |
| 最終保全 | `php KIT/sakura-cutover.php backup "$HOME" PREPARATION_ID --web-503-confirmed` | `BACKED_UP`、新規の停止時バックアップ。フラグは認証後GET／空POSTの実HTTP 503を観測した後の指定 |
| 配置 | `php KIT/sakura-cutover.php apply "$HOME" PREPARATION_ID` | `APPLIED`、入口以外の48対象を配置、停止入口の維持 |
| 再開 | `php KIT/sakura-cutover.php resume "$HOME" PREPARATION_ID` | `LIVE`、照合後に入口を最後に配置。以後の本番Web受入が必要 |
| 再開後照合 | `php KIT/sakura-cutover.php verify "$HOME" PREPARATION_ID` | 全49ファイル・認証・favicon・保存データ・ログ・権限・入口の7項目 |
| 再停止 | `php KIT/sakura-cutover.php hold "$HOME" PREPARATION_ID` | `HELD`、既知の入口を503へ置換。利用者の処理中断への配慮が必要 |
| ソース復元 | `php KIT/sakura-cutover.php rollback "$HOME" PREPARATION_ID` | `RESTORED_STOPPED`、48対象の元内容・権限・更新時刻の復元、新規対象の除去、503の継続 |

`COMPLETED`はそのCLI操作の終了、サービス受入の合格とは別の状態。エラーの`INCOMPLETE`では入口と状態を確認し、未照合の再開なし。配置途中の`APPLYING`は同じ固定版の`apply`で継続可能、完了対象の再書換えなし。未知のファイル変更時は上書きせず停止。`HELD`からの再開も配置全体・停止時データ・空ログの再照合が必要。`RESTORED_STOPPED`から旧版を自動公開する操作なし。

全操作に排他ロック、個々の置換に同一ファイルシステムのrename、非公開先に進行記録。複数ファイルの一括原子性・OS電源断時の完全な永続化保証なし。最終バックアップ内のindex.phpは503用、停止前のindex.phpは準備先のbefore/index.phpに内容・権限・更新時刻を別保管。ソース復元で保存データを停止時コピーへ巻き戻す処理なし。

停止はindex.phpへの新規操作を抑止する手順。Basic認証を通した保存ファイルの読取り自体は継続可能。停止前から進行中のリクエストの強制終了や、自動的な処理完了判定なし。利用者による操作終了確認と実HTTP停止観測が前提。再開からWeb受入・内部照合の完了まで、通常の新規書込みを待つ運用。再開後のデータ変更はverifyで不一致として検出、データ破損との自動断定なし。

## Web確認と受入

実行担当のUbuntuで`python3 tools/sakura-credentials.py --production https://EXAMPLE/APPLICATION/index.php`を対話実行する準備。Basic認証だけの入力、本人限定一時ファイル・2時間の有効期限、試験後の削除。チャットや公開証跡への認証情報の貼付けなし。

```text
node tools/browser/sakura-cutover-maintenance.cjs PRIVATE_CREDENTIALS ready NEW_RESULT.json
node tools/browser/sakura-cutover-maintenance.cjs PRIVATE_CREDENTIALS maintenance NEW_RESULT.json
node tools/browser/sakura-cutover.cjs PRIVATE_CREDENTIALS REVIEWED_PRESENTATION NEW_OUTPUT_DIRECTORY
```

`ready`は未認証favicon 401／認証後200による認証準備、`maintenance`は入口の未認証401・認証後GET／空POST 503と本文・Cache-Control・Retry-Afterの照合。SA-608は上記Web停止観測、停止時バックアップ、配置、再開の記録が必要。Web503だけによる全体合格なし。

SA-609はSA-606と同じ通常UI経路での表示・日本語／空ファイル転送・保存ハッシュ・同名拒否・作成／名前変更／編集／検索／移動／削除・CSRFの確認。新規専用試験フォルダー内だけの変更、旧診断／管理ファイル3件の403も追加。ブラウザー87項目と、試験データのUI削除後のCLI7項目の対応。実データファイル名・表示文字列・画面・認証情報の公開なし。

## ローカル検証

TS-616／617はPHP 8.3.36／8.5.12で各36比較PASS。初回はCLIをroot、Webをwww-dataとした人工環境の権限差で再開HTTP 500。CLIとWebの同一利用者条件へ修正後、同一シナリオの再実行。製品コード・判定基準の変更なし、初回FAILと後続未実施範囲の保持。

TS-618／619は各107比較PASS（認証準備2・停止11・Chrome87・後続CLI7）。一時停止からの再開、旧診断ファイル保護、全試験データ削除、PHPログ0バイト。4ケースの最終合計286比較。ローカルの合格とさくら実機のSA-608／609未実施の区別。

```text
python3 tools/build-stage6-cutover.py NEW_ZIP
# 展開先をartifacts/stage6/cutover-operations-kitとした既存の人工fixture
# ft-stage6-php83／85、Apache、/tmp/ft-rehearsal-local/state.jsonが前提
# PHP側はさくら同様、Webと同じ利用者での実行

docker exec --user www-data ft-stage6-php83 php /workspace/tools/stage6-cutover-test.php /workspace/artifacts/stage6/cutover-operations-kit
python3 tools/stage6-cutover-browser-test.py 83 NEW_OUTPUT_DIRECTORY
```

PHP 8.5はコンテナー名末尾と引数を85へ置換。試験スクリプトは人工環境限定、実機への配置・実行なし。自己署名TLSを許すローカル指定は127.0.0.1の固定ポート・人工認証情報・固定パスのみに限定、実機HTTPSの検証省略なし。
