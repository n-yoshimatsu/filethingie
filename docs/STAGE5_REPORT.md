# 段階5 実施結果（受入継続中）

記録日：2026年10月24日。対象：`php85-support`。段階4コミット`f2a67b64b79f786d3766090f1e977e74a8a9c01f`からの変更。

## 結果と残る確認

PHP 8.3.36／8.5.12のGitHub Actionsで32ケースすべてPASS。HTTPハーネスの800リクエスト、ブラウザーを含む必須比較2,360件。対象経路のPHPエラー・警告・非推奨通知なし。Chrome／FirefoxのJavaScript例外・必要資産のHTTPエラーなし。ローカルの最新短時間ケース32件と分離配置パッケージ2件もPASS。

最終CI対象は`3ab04adb323c1f5fe2d8b1cd144b6cbb3d0a1eb4`。[GitHub Actions実行結果](https://github.com/n-yoshimatsu/filethingie/actions/runs/40041215267)と、取得済み両ジョブの証跡による確認。その後の`96ffd8456545f06049ba67d17dd2e860268f2074`は受入用資料・パッケージの追加、アプリ本体の変更なし。

さくら実機は、利用者提供出力によるFreeBSD 13.0-RELEASE-p15／PHP 8.3.36／cgi-fcgi／HTTPS・必要拡張の確認済み。旧環境診断URLとの混同と端末への試料保存で一時停止後、CLI検索で検証版配置先を確認、同一入力ZIPを提供。Windows上のChromeでのアップロード・ダウンロードについて、利用者提供の2ファイルのSHA-256が配布元と完全一致、転送部分はPASS。作成・編集・任意機能・権限等の実機受入は継続中。実時間アイドル期限2ケースはPASS、8時間の絶対期限2ケースは実行中であり、段階5全体の完了判定は未実施。

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
| TS-515／516 | 実時間絶対期限 | 実行中・判定未実施、5分ごとのGET・8時間10秒 |
| TS-517／518 | 分離配置パッケージ・index.php起動 | ローカル PASS／PASS |
| SA-501 | さくら環境情報 | 利用者提供のCLI・Web出力によるPASS |
| SA-502 | さくら基本受入 | 実施中・全体判定未実施。日本語・空ファイルの転送内容は利用者提供SHA-256一致によるPASS。作成・編集等の残りの操作は結果待ち |
| SA-503 | さくら任意機能・権限 | NOT RUN |

長時間4ケースは2026年10月24日08:40:44 JST開始。30分／8時間の仕様値・時計・セッション値の変更なし。アイドル期限は同日09:10:54 JST終了、無操作1,810.006秒／1,810.018秒、中間HTTP要求0件、両PHPのログイン画面復帰・PHP診断通知なし。開始直後の認証成功や途中GETのみを期限失効のPASSへ代用しない扱い。絶対期限2ケースは継続中。

[アイドル期限結果](verification/stage5/2026-10-24/idle-summary.json)、[証跡アーカイブ](verification/stage5/2026-10-24/idle-evidence.tar.gz)、[SHA-256](verification/stage5/2026-10-24/idle-SHA256SUMS)、[内部ファイルのハッシュ](verification/stage5/2026-10-24/idle-manifest.json)。短時間86試行のアーカイブとは別の、完了した2試行の追加証跡。テスト開始時と現在のアプリ全ファイルのハッシュ一致確認済み。

## 変更内容

- HTTPS：実際のHTTPSリクエストを維持するリダイレクト。信頼したローカル証明書でのSecure／HttpOnly／SameSite Cookie、パスワードハッシュ変更後のセッション失効確認。
- アップロード：新規ファイルの排他的作成、コピー量・書込み終了の確認、不完全な保存の削除。同名同時転送の1件成功・他3件拒否と成功内容の完全一致。
- URL取得：既定無効、明示したホストだけのHTTPS443、公開IPv4へのDNS確認と接続先固定、TLS証明書検証。リダイレクト・認証情報・既存ファイル上書きの拒否、サイズ・容量・接続／転送時間の制限。
- CI：PHP 8.3／8.5の独立ジョブ、通常回帰・Chrome・Firefox・URL・依存欠落構成の順次実行、失敗を含む証跡の保存。PHP fixtureと実行側のAsia/Tokyo統一。
- さくら受入：期限付きのWeb環境診断、ソースハッシュを照合する分離配置パッケージ。`ft2.php`と同一バイト列の`index.php`、生成ハッシュ認証、公開外の専用データ・SQLite・PHPログ、既存設定・ファイルへの上書きなし。

[試験条件](STAGE5_TEST_PLAN.md)、[設定・機能の移行](STAGE4_MIGRATION.md)、[さくら受入手順](SAKURA_ACCEPTANCE.md)。

## 実行環境と証跡

Linux／Apache 2.4.69、PHP 8.3.36／8.5.12。CIのChrome 155.0.8059.39、ローカルChrome 156.0.8078.47、Firefox 142.0.1、Playwright Core 1.56.1。各ケースへのイメージ識別情報・ソース／ハーネスSHA-256・実入力・結果の記録。FreeBSDの利用者報告とLinuxでの直接実行結果の区別。

- [実行履歴・合否一覧](verification/stage5/2026-10-24/summary.json)
- [最終CIの読み取り用証跡](verification/stage5/2026-10-24/ci-final/)、[CI実行・成果物情報](verification/stage5/2026-10-24/ci-runs.json)
- [全短時間試験アーカイブ](verification/stage5/2026-10-24/stage5-evidence.tar.gz)、[内部ファイルのSHA-256](verification/stage5/2026-10-24/archive-manifest.json)
- [PHP 8.3 Chrome画面](verification/stage5/2026-10-24/php83-browser.png)、[PHP 8.5 Chrome画面](verification/stage5/2026-10-24/php85-browser.png)、[PHP 8.3 Firefox画面](verification/stage5/2026-10-24/php83-firefox.png)、[PHP 8.5 Firefox画面](verification/stage5/2026-10-24/php85-firefox.png)
- [利用者提供のさくら環境情報と受入状況](verification/stage5/2026-10-24/sakura-environment.json)
- [さくらの転送内容照合結果](verification/stage5/2026-10-24/sakura-transfer.json)

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

## 残る制約と引継ぎ

- SA-502／503の実機操作、実時間絶対期限2ケースの完了結果、診断ページ削除の確認待ち。
- 既存設定・ユーザー・グループ・SQLite・保存先の移行、本番切替・バックアップ／復元・配布タグは段階6の対象。検証版の空一覧は既存データ消失を示さない状態。
- config／fileinfo／旧外部エディター3種・編集ロックは未対応。Safari、大量データ、FreeBSD負荷試験、全操作の同時更新は未検証。
- URL取得は公開IPv4のみ、リダイレクト不可。DNS解決を含む厳密な処理時間上限と、複数リクエスト全体での容量割当は保証外。悪意ある外部DNSを用いた実ネットワーク試験は未実施。
- 分離配置のUIケースでのみ自己署名証明書の検証無効化。TLS検証の根拠は別のTS-501／502。Linuxでの配置成功をFreeBSD受入の代用にしない扱い。
