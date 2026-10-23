# File Thingieの既存機能と依存関係

基準コミット`a7511ad57e65b64d603e9cb68a20359ad0a3a4e6`の静的調査に基づく一覧です。
PHPファイルは40本で、本体・設定サンプル・フック資料が3本、プラグインが10本、翻訳が27本です。
「実装あり」はソースの存在を表し、PHP 8.3／8.5での動作確認済みを意味しません。
段階3で確認した本体・search／editのHTTP操作とユーザー・グループ設定は、[検証結果](STAGE3_REPORT.md)に別途記録しています。本一覧の全機能を検証済みと扱いません。

段階4では認証・db／log／zip・ブラウザー依存を更新し、一部の旧プラグインを明示的に無効化しています。現在の対応状態は[段階4の対応表](STAGE4_MIGRATION.md)、検証範囲は[段階4結果](STAGE4_REPORT.md)を参照してください。以下の表は上流基準の調査記録です。

## 本体の機能

| 機能 | 主な実装 | 現在の前提と移行時の確認 |
|---|---|---|
| ログイン・ログアウト | `ft_check_login()`、`ft_check_cookie()`、`ft_do_action()` | セッション、主ユーザー・追加ユーザー、remember me。認証方式の移行が必要 |
| ユーザー・グループ設定 | `ft_settings_load()`、`config.sample.php` | グループ別のルート・設定上書き。サンプル中の追加ユーザー・グループはコメント化 |
| 一覧・並び替え・日時・サイズ表示 | `ft_get_filelist()`、`ft_make_body()` | 読取り権限、除外設定、文字コード、空ディレクトリ |
| アップロード | `ft_do_action()`の`upload` | 拡張子・ファイル名制限、最大サイズ、総容量、書込み権限 |
| ダウンロード | `ft_make_body()`、`method=getfile`処理 | 直接リンクとPHP経由の設定差。認証とWebサーバー側の公開範囲を確認 |
| ファイル・ディレクトリ作成 | `ft_do_action()`の`createdir` | `CREATE`、書込み権限、既存名との衝突 |
| URLからファイル作成 | `ft_do_action()`の`type=url` | URLストリーム、接続先制限、タイムアウト、容量、上書きの扱い |
| 名前変更・移動・複製・削除 | `ft_do_action()` | `FILEACTIONS`、パス境界、同名衝突、空／非空ディレクトリの削除設定 |
| ZIP展開 | `ft_do_action()`の`unzip` | 現行は`exec()`と外部`unzip`コマンドに依存。ZIP作成プラグインとは別機能 |
| chmod・シンボリックリンク | `ft_do_action()`、画面側のadvanced actions | OS依存と権限、`ADVANCEDACTIONS`、サーバー側の制限確認 |
| 表示・アクセス・容量制限 | `ft_check_*()`、`ft_get_dirsize()` | blacklist／whitelist、ユーザールート、再帰走査、リンクの扱い |
| 翻訳 | `t()`、`locales/*.php` | 日本語を含む27翻訳。英語の原文と置換変数、HTML／JavaScript出力 |
| プラグイン拡張 | `ft_plugins_load()`、`ft_invoke_hook()`、`hooks.php` | 読込み順、設定値の正規化、フックの戻り値、外部依存 |

## 同梱プラグイン

利用者から、元ソース以外のプラグインは使用しないと確認済みです。同梱プラグインの有効化状況は未確認です。
サンプル設定で有効なのは`search`と`edit`です。`tinymce`の設定例はコメント化され、その他は有効化されていません。サンプルを実運用の有効化状態とはみなしません。

| 名前 | 機能とソース | 依存・制約 | 初回対応の扱い案 |
|---|---|---|---|
| search | ファイル名検索。`plugins/search.plugin.php` | 再帰走査、除外設定、AJAX | 基本機能として優先 |
| edit | テキスト編集・保存。`plugins/edit.plugin.php` | 対象拡張子、書込み権限、通常POST／AJAX保存 | 基本機能として優先。ロック機能の完成は別途判断 |
| zip | ファイル・フォルダーのZIPダウンロード。`plugins/zip.plugin.php` | 同梱`zipfile`クラス、zlib、メモリー／一時ファイル、設定キー | 使用状況を確認して対応。標準`ZipArchive`への置換を検討 |
| db | SQLite接続とテーブル管理。`plugins/db.plugin.php` | PDO SQLite、DB保存先の書込み権限 | DB利用プラグインとまとめて対応 |
| log | 操作ログ、一覧、削除、CSV出力。`plugins/log.plugin.php` | dbを先に読込み。TableSorter、`viewlogs`設定 | dbとCSV・画面表示をまとめて検証 |
| config | 設定・ユーザー・グループ編集。`plugins/config.plugin.php` | `ft_settings_external_clean()`と`ft_settings_external_save()`の定義が取得ソースに存在しない | 運用側の補完有無と保存仕様の確定が必要 |
| fileinfo | ファイル説明。`plugins/fileinfo.plugin.php` | db依存。旧SQLite API、SQL・変数の不整合、表示・編集のTODOあり | 未完成部分を特定し、復元・完成の範囲を別途決定 |
| tinymce | TinyMCE連携。`plugins/tinymce.plugin.php` | 外部エディター本体は同梱なし。既定パスは`tinymce/jscripts/tiny_mce/tiny_mce.js` | 使用有無、対象エディター版、ライセンスを確認後に決定 |
| editorck | CKEditor連携。`plugins/editorck.plugin.php` | 外部エディター本体は同梱なし。`CKEDITOR`の旧API | 同上 |
| fckeditor | FCKeditor連携。`plugins/fckeditor.plugin.php` | 外部エディター本体は同梱なし。`FCKeditorAPI`を利用 | 同上 |

プラグインの既定設定は各`*_info()`関数にもありますが、宣言の存在だけで有効化時の設定補完が正しいとは判断しません。段階2以降で最小設定と明示設定の両方を確認します。

## ブラウザーと配布物

| 対象 | 確認内容 |
|---|---|
| jQuery | 本体が`js/jquery-1.2.1.pack.js`を読込み |
| 独自JavaScript | `js/filethingie.js`、本体・プラグインが生成するJavaScript |
| TableSorter | `plugins/tablesorter/jquery.tablesorter.js`のヘッダーは2.0.3、要求jQueryは1.2.3。実際にログ画面で読み込むmin版と本体jQueryとの組合せを要確認 |
| ライブラリ付属物 | TableSorterのexamples・tests・buildを同梱。アプリ本体の自動試験とは区別 |
| アプリのテスト・CI | 基準コミットにアプリ用テスト基盤、Composer設定、GitHub Actionsワークフローなし |
| 設定とデータ | `config.php`と`data/*`は既存`.gitignore`の除外対象。`config.php`はリポジトリに存在しない |
| 配布形式 | 本体の`INSTALL`は`EXPANDED`。単一ファイル版の生成・配布手順は未確認 |

## 運用条件と後続確認

運用先はさくらのレンタルサーバ、OSはFreeBSDです。利用者確認では選択可能な最新PHPは8.3で、8.5は表示されていません。元ソース以外のプラグインは使用しません。実運用はPHP 8.3、開発・CIはPHP 8.3と8.5を対象とする方針です。段階2以降で次を確認します。

1. スタンダードプラン、CLIのPHP切替前5.6.40／切替後8.3.36は利用者確認済み。FreeBSD詳細版、Web側のPHP版・実行方式・必要拡張は未確認。ローカルの実測値と確認方法は[段階2環境](STAGE2_ENVIRONMENT.md)を参照。
2. 同梱プラグインの有効化状況、本体への独自改修の有無。エディター連携を有効にする場合は外部本体の種類・版。
3. 既存の設定形式、ユーザー・グループの利用、DBの利用と保存形式。
4. 管理対象ディレクトリ、直接URL公開の要否、権限、ファイル数・サイズの目安。
5. URL取得、ZIP、chmod、シンボリックリンクなどの追加操作の利用有無。

対応方針と段階別の完了条件は[PHP 8.3とPHP 8.5への移行計画](PHP85_MIGRATION.md)を参照してください。
