# 段階5 実施結果（完了）

記録日：2026年10月24日。対象：`php85-support`。段階4コミット`f2a67b64b79f786d3766090f1e977e74a8a9c01f`からの変更。

## 結果と完了判定

PHP 8.3.36／8.5.12のGitHub Actionsで32ケースすべてPASS。HTTPハーネスの800リクエスト、ブラウザーを含む必須比較2,360件。対象経路のPHPエラー・警告・非推奨通知なし。Chrome／FirefoxのJavaScript例外・必要資産のHTTPエラーなし。ローカルの最新短時間ケース32件、分離配置パッケージ2件、実時間セッション4件もPASS。計画上の重複しない41ケースは、通常CI32・分離配置2・実時間4・さくら3の構成。段階5の完了判定、[集計根拠](verification/stage5/2026-10-24/completion.json)。

証跡を保存したCI対象は`3ab04adb323c1f5fe2d8b1cd144b6cbb3d0a1eb4`。[GitHub Actions実行結果](https://github.com/n-yoshimatsu/filethingie/actions/runs/40041215267)と、取得済み両ジョブの証跡による確認。その後の`96ffd8456545f06049ba67d17dd2e860268f2074`は受入用資料・パッケージの追加、アプリ本体の変更なし。追加ツール公開後のコミット`a4a4b5a3361775524c7ebed190c42e3fd0940fef`でも[CI両ジョブSUCCESS](https://github.com/n-yoshimatsu/filethingie/actions/runs/40046708301)、[取得した状態記録](verification/stage5/2026-10-24/ci-followup.json)。

さくら実機は、利用者提供出力によるFreeBSD 13.0-RELEASE-p15／PHP 8.3.36／cgi-fcgi／HTTPS・必要拡張の確認済み。旧環境診断URLとの混同と端末への試料保存で一時停止後、CLI検索で検証版配置先を確認、同一入力ZIPを提供。Windows上のChromeでのアップロード・ダウンロードについて、利用者提供の2ファイルのSHA-256が配布元と完全一致、転送部分はPASS。続いてUbuntu Chromeからの実機HTTPS操作を直接実行、基本操作62比較・ログ／CSV／ZIP64比較・直接URL保護6比較の計132比較すべてPASS。その後の利用者提供CLI出力で内部16比較もPASS、SA-502／503の総合判定はPASS。実時間アイドル期限2ケース・8時間絶対期限2ケースもPASS、診断ページ削除は利用者回答による確認済み。段階5の予定範囲の検証完了。本番切替は段階6の対象。

| ケース（8.3／8.5） | 確認内容 | 最新結果・証跡 |
|---|---|---|
| LINT-201／202 | PHP40本の構文・診断通知 | CI PASS／PASS |
| TS-201／202 | 未認証初期画面 | CI PASS／PASS |
| TS-401／402 | 基本操作 | CI PASS／PASS |
| TS-403／404 | 境界値・不正入力 | CI PASS／PASS |
| TS-405／406 | ユーザー・グループ権限 | CI PASS／PASS |
| TS-407／408 | 認証・CSRF・管理権限 | CI PASS／PASS |
| TS-409／410 | DB・ログ・ZIP | CI PASS／PASS |
| TS-411／412 | 任意依存欠落 | CI PASS／PASS |
| TS-413／414 | Chromeブラウザー互換 | CI PASS／PASS |
| TS-415／416 | 改行・アップロード元名 | CI PASS／PASS |
| TS-501／502 | HTTPS・資格情報変更 | CI PASS／PASS |
| TS-503／504 | URL取得の既定無効化 | CI PASS／PASS |
| TS-505／506 | URL取得・禁止先・上書き拒否 | CI PASS／PASS |
| TS-507／508 | URL取得容量制限 | CI PASS／PASS |
| TS-509／510 | 同名同時アップロード | CI PASS／PASS、4セッション・5回繰返し |
| TS-511／512 | Firefoxブラウザー互換 | CI PASS／PASS |
| TS-513／514 | 実時間アイドル期限 | ローカル PASS／PASS、30分10秒の無操作後のログイン画面復帰 |
| TS-515／516 | 実時間絶対期限 | ローカル PASS／PASS、8時間10秒後のログイン画面復帰、各295比較・99要求 |
| TS-517／518 | 分離配置パッケージ・index.php起動 | ローカル PASS／PASS |
| SA-501 | さくら環境情報 | 利用者提供のCLI・Web出力によるPASS |
| SA-502 | さくら基本受入 | PASS。Windows転送2ハッシュ一致、基本操作・認証62比較、操作後PHPログの追加行0件 |
| SA-503 | さくら任意機能・権限 | PASS。ログ／CSV／ZIP64比較・直接URL保護6比較、利用者提供CLI出力による非公開配置・権限・PHPログの確認 |

長時間4ケースは2026年10月24日08:40:44 JST開始。30分／8時間の仕様値・時計・セッション値の変更なし。アイドル期限は同日09:10:54 JST終了、無操作1,810.006秒／1,810.018秒、中間HTTP要求0件、両PHPのログイン画面復帰・PHP診断通知なし。開始直後の認証成功や途中GETのみを期限失効のPASSへ代用しない扱い。絶対期限2ケースは同日16:40:54 JST終了、経過28,810.004701秒／28,810.006726秒。維持GET94回、両PHPで期限経過後のログイン画面復帰・PHP診断通知なし。

[アイドル期限結果](verification/stage5/2026-10-24/idle-summary.json)、[証跡アーカイブ](verification/stage5/2026-10-24/idle-evidence.tar.gz)、[SHA-256](verification/stage5/2026-10-24/idle-SHA256SUMS)、[内部ファイルのハッシュ](verification/stage5/2026-10-24/idle-manifest.json)。短時間86試行のアーカイブとは別の、完了した2試行の追加証跡。テスト開始時と現在のアプリ全ファイルのハッシュ一致確認済み。

[絶対期限結果](verification/stage5/2026-10-24/absolute-summary.json)、[証跡アーカイブ](verification/stage5/2026-10-24/absolute-evidence.tar.gz)、[SHA-256](verification/stage5/2026-10-24/absolute-SHA256SUMS)、[内部ファイルのハッシュ](verification/stage5/2026-10-24/absolute-manifest.json)。2ケース合計590比較・198 HTTP要求、全応答の受信時SHA-256照合済み。アーカイブは191,995 bytes、SHA-256 `844557ebf67cf46a5881e756a1218901ee5ea7ed3d747f4e0d063a9c86b48ee8`、保存後の全内部ファイル再照合済み。

絶対期限の維持GETは約5分間隔で94回、最終認証確認は開始約7時間50分後。最後のGETから失効確認まではアクセスログの秒精度で609秒、30分無操作条件への未到達。最終待機区間を含む実際の間隔と、無操作期限による失効ではない判定根拠の記録。期限定数・時計・セッション値の変更なし。試験開始時と現在のアプリ45ファイルは同一SHA-256。開始時の試験計画も保存・照合済み、後日の差分はTS-517／518の追記のみ。ローカルLinuxの実時間結果であり、FreeBSD上での8時間実測とは異なる検証範囲。

## 変更内容

- HTTPS：実際のHTTPSリクエストを維持するリダイレクト。信頼したローカル証明書でのSecure／HttpOnly／SameSite Cookie、パスワードハッシュ変更後のセッション失効確認。
- アップロード：新規ファイルの排他的作成、コピー量・書込み終了の確認、不完全な保存の削除。同名同時転送の1件成功・他3件拒否と成功内容の完全一致。
- URL取得：既定無効、明示したホストだけのHTTPS443、公開IPv4へのDNS確認と接続先固定、TLS証明書検証。リダイレクト・認証情報・既存ファイル上書きの拒否、サイズ・容量・接続／転送時間の制限。
- CI：PHP 8.3／8.5の独立ジョブ、通常回帰・Chrome・Firefox・URL・依存欠落構成の順次実行、失敗を含む証跡の保存。PHP fixtureと実行側のAsia/Tokyo統一。
- さくら受入：期限付きのWeb環境診断、ソースハッシュを照合する分離配置パッケージ。`ft2.php`と同一バイト列の`index.php`、生成ハッシュ認証、公開外の専用データ・SQLite・PHPログ、既存設定・ファイルへの上書きなし。

[試験条件](STAGE5_TEST_PLAN.md)、[設定・機能の移行](STAGE4_MIGRATION.md)、[さくら受入手順](SAKURA_ACCEPTANCE.md)。

## 実行環境と証跡

Linux／Apache 2.4.69、PHP 8.3.36／8.5.12。CIのChrome 155.0.8059.39、ローカルChrome 156.0.8078.47、Firefox 142.0.1、Playwright Core 1.56.1。各ケースへのイメージ識別情報・ソース／ハーネスSHA-256・実入力・結果の記録。FreeBSDの環境情報は利用者報告、実機Web操作はUbuntu Chromeからの直接実行。Linux上のローカルPHP回帰との区別。

- [実行履歴・合否一覧](verification/stage5/2026-10-24/summary.json)
- [最終CIの読み取り用証跡](verification/stage5/2026-10-24/ci-final/)、[CI実行・成果物情報](verification/stage5/2026-10-24/ci-runs.json)
- [全短時間試験アーカイブ](verification/stage5/2026-10-24/stage5-evidence.tar.gz)、[内部ファイルのSHA-256](verification/stage5/2026-10-24/archive-manifest.json)
- [PHP 8.3 Chrome画面](verification/stage5/2026-10-24/php83-browser.png)、[PHP 8.5 Chrome画面](verification/stage5/2026-10-24/php85-browser.png)、[PHP 8.3 Firefox画面](verification/stage5/2026-10-24/php83-firefox.png)、[PHP 8.5 Firefox画面](verification/stage5/2026-10-24/php85-firefox.png)
- [利用者提供のさくら環境情報と受入状況](verification/stage5/2026-10-24/sakura-environment.json)
- [さくらの転送内容照合結果](verification/stage5/2026-10-24/sakura-transfer.json)
- [さくら総合受入判定](verification/stage5/2026-10-24/sakura-acceptance.json)、[利用者提供のサーバー内部確認結果](verification/stage5/2026-10-24/sakura-server.json)
- [さくらの自動ブラウザー実行記録](verification/stage5/2026-10-24/sakura-ui/summary.json)、[基本操作](verification/stage5/2026-10-24/sakura-ui/basic.json)、[ログ・CSV・ZIP](verification/stage5/2026-10-24/sakura-ui/plugins.json)、[直接URL保護](verification/stage5/2026-10-24/sakura-ui/protection.json)

アーカイブSHA-256：`a4a7082e4331ff2f59074e6577201761d9de98ac9d006b304b1d445369b0b515`。再展開による全内部ファイルのハッシュ一致確認済み。86試行分の記録（81 PASS・3 FAIL・2 BLOCKED）の保存。最新合格ケース数とは異なる再試行込みの集計。

アーカイブには初回失敗・再実行・CI結果・対象アプリ・ハーネス・配備用setupの収録。Cookie／CSRFトークンの公開用伏字化。HTTP結果のハッシュは受信時の元バイト列、伏字化後の本文ハッシュとは異なる場合あり。初期ローカル試行の本体末尾空白差分と判定器も、当時のハッシュとの一致確認後に収録。実機の資格情報・設定・既存データの収録なし。

最終CIの元ZIPはGitHubが示すSHA-256との照合済み。PHP 8.3側`8ccd7dbc88892161925652c2109cbac7fb7952f841e7c8f791381701f1a67540`、PHP 8.5側`d49d4c67253d78778742238b199c393b3ff258dc52cf2024b4c7380beffbb053`。期限付きCI成果物から取り出した結果のリポジトリ内保存。

## 途中の不一致と修正

| 発見・旧判定 | 原因・対応 | 再確認 |
|---|---|---|
| ローカル初回 LINT-201／202 BLOCKED | Apacheの既定設定と追加TLS設定でのListen 443重複。追加側の重複指定削除。起動前に阻害された後続ケースは未実施 | ローカル・最終CIの構文／初期画面PASS |
| ローカル TS-503／504 FAIL | エラー判定器の二重引用符限定。実際の一重引用符のHTMLエラー表示を検出不能。HTMLParserによるclass属性判定へ修正、入力・期待結果の変更なし | 同じURL無効化条件での再実行PASS、元FAILの保持 |
| 初回CI PHP 8.5 TS-410 FAIL | 実行側UTCとPHP fixtureのAsia/Tokyoの暦日差。日付範囲入力とログ日時の不一致。CIのTZをfixtureへ統一、アプリ・日付フィルター条件の変更なし | 最終CIのTS-409／410 PASS |

[初回CI](https://github.com/n-yoshimatsu/filethingie/actions/runs/40040895072)のPHP 8.5は15 PASS・1 FAIL。PHP 8.3側はブラウザー依存の準備中に後続runによる取消、機能ケースはNOT RUN。run全体のcancelled表示とPHP 8.5の実際のFAILの区別。

## 再実行

Docker Compose・Python 3・Node.js・Chromeがある、実データを含まない検証端末での実行。Firefox依存の追加は[CI定義](../.github/workflows/php.yml)参照。実行前の各ケースID・名称表示、毎回新しい証跡ディレクトリの指定。

```bash
npm ci --prefix tools/browser --ignore-scripts
cd tools/browser
npx --no-install playwright-core install firefox
cd ../..
TZ=Asia/Tokyo python3 tools/ci.py php83 --output artifacts/stage5-rerun/php83
TZ=Asia/Tokyo python3 tools/ci.py php85 --output artifacts/stage5-rerun/php85
```

ハーネス内での使い捨てコンテナ再作成を伴う手順。長時間ケース実行中の同一Composeプロジェクトへの適用不可。`FT_CHROME`によるChromeパスの指定が可能な構成。構成依存の順序と期待結果は[試験条件](STAGE5_TEST_PLAN.md)のとおり。

## さくら自動操作の追加結果

2026年10月24日09:40〜09:43 JSTのChrome 156.0.8078.47による実HTTPS操作。通常のBasic認証とアプリログイン、専用の新規フォルダー内での作成・保存・移動・転送・削除。セッション注入・内部値変更・TLS検証無効化なし。

基本操作62比較、ログ等64比較、直接URL保護6比較のPASS。試験データのUI削除とログアウト、本人限定の一時資格情報ファイル・親ディレクトリの削除済み。元の管理ファイルと先行する手動試験ファイルへの変更なし。記録されたJavaScript例外・対象サーバーエラー0件、後続の利用者提供CLI出力によるPHPログの追加行0件確認済み。

公開証跡はホスト名・サーバー保存先の伏字化済みJSON、当時のハーネスとSHA-256。画面画像・CSV原文はローカルの非公開証跡。CSV比較のPASSは伏字化前の原データによる判定。読取り専用の内部確認ツールのPHP 8.3／8.5自己検証10件は、さくら実機のPASS件数とは別集計。

2026年10月24日09:54:05 JSTの利用者提供CLI出力16比較のPASS。ソース48ファイル一致、非公開親・保存先0700、設定・SQLite・PHPログ0600、SQLiteヘッダー正常、PHPログのセットアップ行以外0行。Web操作132比較との合計148比較によるSA-502／503のPASS。条件緩和や再実行ではなく、未評価だったサーバー内部の証跡追加による総合判定。

## 残る制約と引継ぎ

- 段階5の未完了ケースなし。さくら実機SA-501〜503はPASS、Web・内部確認の計148比較すべてPASS。診断ページ削除は利用者回答による確認済み。
- 既存設定・ユーザー・グループ・SQLite・保存先の移行、本番切替・バックアップ／復元・配布タグは段階6の対象。検証版の空一覧は既存データ消失を示さない状態。
- config／fileinfo／旧外部エディター3種・編集ロックは未対応。Safari、大量データ、FreeBSD負荷試験、全操作の同時更新は未検証。
- URL取得は公開IPv4のみ、リダイレクト不可。DNS解決を含む厳密な処理時間上限と、複数リクエスト全体での容量割当は保証外。悪意ある外部DNSを用いた実ネットワーク試験は未実施。
- 分離配置のUIケースでのみ自己署名証明書の検証無効化。TLS検証の根拠は別のTS-501／502。Linuxでの配置成功をFreeBSD受入の代用にしない扱い。
