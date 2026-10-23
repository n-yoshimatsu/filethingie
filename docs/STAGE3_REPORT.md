# 段階3・PHP互換性修正と基本操作の検証結果

実施日：2026年10月24日、日本時間07:38〜07:42。UTC証跡では2026年10月23日22:38〜22:42。

**本体の互換性修正と、Linux上のPHP 8.3.36／8.5.12での段階3試験を完了。最新結果は10ケースすべてPASS。** ブラウザー描画・JavaScript、さくらのFreeBSD実機、全プラグイン、認証方式刷新は未検証・後続段階の対象。

## 変更内容

| 対象 | 問題と変更 |
|---|---|
| `ft2.php`の容量換算 | 波括弧の文字列アクセスと単位付き文字列の演算を廃止、`ini_parse_quantity()`によるバイト換算。アップロード上限表示の正常化 |
| 入力処理 | `get_magic_quotes_gpc()`依存の解消、引用符・バックスラッシュの保持、任意項目の欠落処理、配列・NUL・必須項目欠落のHTTP 400による拒否 |
| ファイル名・リンク | 不正な正規表現の修正、UTF-8・空白・引用符・記号名の受入、名前の二重URLデコード廃止、HTMLとURLの個別エスケープ、ダウンロードのUTF-8名、マルチバイト名の省略表示 |
| パス・基本操作 | 管理ルート内の実パス確認、検証済み入力のダウンロードへの使用、ディレクトリ指定の維持、名前変更・複製のFILEACTIONS判定修正、移動先の同名ファイル保護 |
| アップロード | クライアントのMAX_FILE_SIZEに依存しないMAXSIZE確認、総容量計算への当該要求の転送済み量の加算、各アップロード欄のエラーコードの個別処理 |
| ユーザー・プラグイン設定 | グループ未指定ユーザーの処理、false指定プラグインの無効化、true指定の正規化、`hook_info`の既定設定と明示設定の統合 |
| `plugins/edit.plugin.php` | 未送信のチェックボックス・submitの処理、編集対象名のHTMLエスケープ、ファイルブラックリストの確認、保存後のリダイレクト値の符号化 |
| `plugins/search.plugin.php` | 未送信値の扱い、検索欄・検索リンクの符号化、ディレクトリ判定、再帰時のシンボリックリンク追跡の回避 |
| `plugins/config.plugin.php` | 必須引数より前のデフォルト引数の解消。既存の2引数呼出し順の維持。設定編集機能全体は段階4の対象 |

PHPの根拠：[PHP 8.0の非互換変更](https://www.php.net/manual/en/migration80.incompatible.php)、[ini_parse_quantity](https://www.php.net/manual/en/function.ini-parse-quantity.php)、[PHP 8.5の非推奨事項](https://www.php.net/manual/en/migration85.deprecated.php)。対応対象はPHP 8.3／8.5。PHP 5.6での互換性維持なし。

## 最新のケース結果

試験条件：[STAGE3_TEST_PLAN.md](STAGE3_TEST_PLAN.md)。判定単位はシナリオ。PHP拡張の存在確認やHTTP 200だけを、操作成功の代用にしない判定。

| ID | 内容 | PHP 8.3.36 | PHP 8.5.12 | 観測 |
|---|---|---|---|---|
| LINT-201／202 | 全PHPファイル構文確認 | PASS | PASS | 各40本、終了コード0、診断通知なし |
| TS-201／202 | 未認証初期画面 | PASS | PASS | HTTP 200、ログインフォーム、ログの診断通知なし |
| TS-301／302 | 基本操作と内容保全 | PASS | PASS | 各83要求・247比較。ログイン、一覧と並替え順、作成、転送、通常／AJAX編集、検索、名前変更・複製・移動・削除、ログアウト |
| TS-303／304 | 境界値と不正入力 | PASS | PASS | 各55要求・131比較。不正入力、禁止拡張子、同名保護、読取り専用、容量上限、複数アップロードの個別処理 |
| TS-305／306 | 追加ユーザーとグループ設定 | PASS | PASS | 各19要求・56比較。限定ルート、書込み禁止、プラグイン指定、グループ未指定ユーザー |

機能シナリオ6件の合計314 HTTP要求・868比較。環境確認要求も含む件数。対象経路のPHPエラー・警告・非推奨通知0件。

転送・保存の内容判定：UTF-8、引用符、バックスラッシュ、HTML記号、空白・タブ・改行、空ファイルの長さとSHA-256一致。生成されたダウンロードリンクからの取得と、編集画面のhidden値・テキスト内容の一致。操作後のLocationヘッダーと実際の遷移先の確認。

容量の観測：MAX_FILE_SIZE省略時も1,999,999／2,000,000バイトの受入、2,000,001バイトの不作成。PHP側2M上限超過の2,097,153バイトと別の正常ファイルを同じ要求で送信し、前者の拒否・後者の内容一致。

## 試行履歴と証跡

- run1：構文・初期画面の4件PASS。TS-301はPythonのCookie解析例外でBLOCKED。旧形式のCookie名`ft_user_/ft2.php`の受信時に停止、アプリのFAILとは別の記録
- run2：Cookie名をそのまま扱う受信処理へ修正後、6機能ケースPASS。アプリ・入力・期待結果の変更なし
- run3：同じ基本操作の並替え順とLocationヘッダーの観測を追加し、TS-301／302を再実行してPASS。アプリ変更なし。最新判定にはrun3を採用
- 全13試行の履歴保持。ケースとしての最新10件PASSと、履歴上のBLOCKED 1件の分離。未到達だったrun1の後続操作を合格として補完しない扱い

一覧・判定：[summary.json](verification/stage3/2026-10-24/summary.json)。各試行の`result.json`、`container.log`、`source-sha256.json`は同ディレクトリ配下。HTTP要求・応答・比較結果・ハーネス版を含む全証跡：[evidence.tar.gz](verification/stage3/2026-10-24/evidence.tar.gz)。

証跡アーカイブのSHA-256：`f236bc36e3bedd76f9403568d4acc91c9131cb93c65e708eb9c377121433da2a`。展開時は新規ディレクトリの利用。元ソースとの差分はアーカイブ内`source.patch`、基点は`d347456c2753fb02196af814fc9ca96357a0cad0`。公開用アーカイブではSet-Cookie値のみ伏字化。元のローカル証跡を保持。

## 再実行手順

リポジトリのルートでの実行。各試行前のイメージ再構築・コンテナ再作成。`artifacts/stage3/recheck`は未使用の出力先。既存結果への上書き拒否。実行前のケースID・名称の進捗表示。

```sh
docker compose up -d --build --force-recreate
python3 tools/stage2.py lint php83 --output artifacts/stage3/recheck/LINT-201
python3 tools/stage2.py lint php85 --output artifacts/stage3/recheck/LINT-202
python3 tools/stage2.py startup php83 --output artifacts/stage3/recheck/TS-201
python3 tools/stage2.py startup php85 --output artifacts/stage3/recheck/TS-202
python3 tools/stage3.py basic php83 --output artifacts/stage3/recheck/TS-301
python3 tools/stage3.py basic php85 --output artifacts/stage3/recheck/TS-302
docker compose up -d --force-recreate
python3 tools/stage3.py negative php83 --output artifacts/stage3/recheck/TS-303
python3 tools/stage3.py negative php85 --output artifacts/stage3/recheck/TS-304
docker compose up -d --force-recreate
python3 tools/stage3.py groups php83 --output artifacts/stage3/recheck/TS-305
python3 tools/stage3.py groups php85 --output artifacts/stage3/recheck/TS-306
docker compose down
```

段階2ツールの`application_matches_upstream=false`は互換性修正による想定結果。コンテナと現在の作業ツリーの一致は別の`container_matches_checkout`による確認。

## 配置に関する引継ぎ

アプリの変更ファイルは`ft2.php`、`plugins/edit.plugin.php`、`plugins/search.plugin.php`、`plugins/config.plugin.php`の4本。検証用コピーでは4本を一組として更新し、既存`config.php`・管理データの保持。`dev/`・`tools/`・`docs/`・Composeファイルの本番配置不要。試験用の公開ダミー認証情報の流用不可。

配置前の既存アプリ・設定・データのバックアップ、およびHTTP経由でのWeb PHP版・SAPIの確認。CLIのPHP 8.3.36だけでWeb側の版を確定しない扱い。復元時は変更4本を旧版へ戻す操作と、必要なPHP設定の復元の対応付け。

さくらへの転送・PHP設定変更・本番データ操作は今回の実施対象外。今回の成果は互換性修正の検証版であり、正式リリース・本番受入完了とは別の状態。

## 残る範囲

- さくらFreeBSD実機のWeb PHP詳細版・SAPI・拡張・設定値・エラーログ、HTTP／ブラウザーでの受入検証
- JavaScript、古いjQuery・TableSorter、外部エディター連携、HTTPS・remember me、LOGIN無効構成、直接URL公開構成の総合検証
- 認証情報の平文保存・MD5 Cookie、CSRF、操作権限・競合・シンボリックリンクを含む包括的な見直し。FILEACTIONS等をパス文字列で設定した構成、総容量LIMITの境界値試験
- db／log／config／fileinfoの実行互換性、CSVのescape引数、未実装の設定保存・編集ロック・ファイル説明。今回のconfig変更は構文確認までの範囲
- ZIP作成・展開、URL取得、chmod・symlink操作、PHP 8.3／8.5のCIと配布手順

ファイル名の受入条件変更：先頭・末尾のピリオド、前後空白、区切り文字、制御文字、不正UTF-8の拒否。既存ファイルにも適用する操作時検証。Linux上の日本語名成功と、さくらのファイル名に関する制約を別に扱う引継ぎ。
