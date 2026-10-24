# さくら・FreeBSD受入手順

対象：スタンダード／FreeBSD／PHP 8.3。利用者によるターミナル・ブラウザー操作での実施。既存運用ディレクトリとデータへの上書きなし。段階5時点での開発検証版。

## SA-501 環境情報

利用者から受領したCLI出力（2026年10月24日）：

```text
FreeBSD 13.0-RELEASE-p15
PHP 8.3.36 / SAPI cli
session, json, PDO, pdo_sqlite, zip, openssl, curl: true
upload_max_filesize=5M / post_max_size=8M / memory_limit=128M
```

CLIとWeb情報の利用者提供出力による確認済み。Web側はPHP 8.3.36、cgi-fcgi、FreeBSD 13.0-RELEASE-p15、HTTPS=on。session／json／PDO／pdo_sqlite／zip／openssl／curlはすべて有効。Web側upload_max_filesizeとpost_max_sizeは1024M、memory_limitは128M、max_execution_timeは30秒。display_errorsとlog_errorsは無効、opcache.enableとopcache.validate_timestampsは有効。CLIとWebで異なる容量上限の記録。診断ページ削除の実施結果は未受領。最初の複数行コマンドの改行消失による`uname`引数エラー、`&&`連結後の正常取得。

Web確認用の`tools/sakura-probe.php`はCLI専用。既存の公開ディレクトリと対応HTTPS URLを引数として、乱数付きの新規ディレクトリとPHPファイルを排他的に作成する機能。30分の表示期限、選択した実行環境情報だけのJSON出力、認証情報・Cookie・環境変数・phpinfoの出力なし。既存Basic認証の配下ならブラウザーでの既存認証が必要な構成。

```bash
php tools/sakura-probe.php '/実際の公開ディレクトリ' 'https://実際のホスト/設置先/'
```

表示されたURLをブラウザーで開き、JSONの保存・提示。確認後は表示された`rm`コマンドによる作成ファイル1本と確認用ディレクトリの削除。PHPソースがそのまま表示される場合は処理系未設定、即時削除後の設定確認。401の場合は既存Basic認証を済ませたブラウザーでの確認。CLIからPHPコードをシェルへ直接貼付する操作との区別。

## SA-502 分離配置と基本受入

環境情報と検証用ディレクトリの確定後、固定コミットの配布ファイルを別ディレクトリへ配置する手順。段階6の本番更新とは別の操作。

1. 既存`config.php`・管理ファイル・DBの場所とバックアップ方法の確認。検証用には新規ファイルと新規ユーザーの使用、実際の設定やパスワードの公開リポジトリへの追加なし。
2. 配置対象：`ft2.php`、`config.sample.php`、`hooks.php`、`css/`、`js/`、`locales/`、`plugins/`、`LICENSE`。`dev/`、`artifacts/`、`.git/`、公開ダミー資格情報入り`dev/config.test.php`の配備なし。
3. 検証用`config.php`の新規作成。USERNAMEとハッシュ生成ツールによるPASSWORD_HASH、ALLOW_LEGACY_PASSWORD=false、LANG=ja、LOGIN=true、HIDEFILEPATHS=true、HTTPS=true、PERMISSION=0644、DIRPERMISSION=0755、URL_HOSTS空文字列の設定。ハッシュ生成時の標準入力、チャットへのパスワード送信なし。
4. 管理対象DIRとSQLiteは公開領域外の検証専用ディレクトリの使用。Web側の実行ユーザーによる読み書き可能性の確認。共有サーバーでの0777の一律指定なし。代替の公開領域内DIRを使う場合は、Webサーバーによる直接アクセス拒否と実URLでの403／404確認が必要な条件。
5. HTTPSでの未認証画面、誤パスワードの拒否、正パスワードでの一覧、HttpOnly／Secure／SameSite=Lax Cookieの確認。
6. `acceptance-日本語.txt`と空の`acceptance-empty.txt`を端末からアップロード。ダウンロードしたファイルのSHA-256と元ファイルの一致。テキストの内容・改行維持。
7. 新規フォルダー・空ファイル作成、通常／AJAX編集、複製、名前変更、移動、日本語検索、削除。各操作後の一覧とダウンロード内容の確認。同名衝突時の既存内容保全。
8. ログアウト後の再読み込みでログイン画面、ログインなしの管理ファイル取得拒否、PHPエラーログの新規警告・Fatal・Deprecatedなしの確認。
9. 操作日時、Web PHP詳細版・SAPI、ブラウザー版、ケースごとの入力・期待結果・実結果の記録。失敗時の画面・ログ該当箇所の保存、資格情報とセッション値の除外。

## SA-503 任意機能と制限

- DB／ログ：公開外の新規DB、主ユーザーのみの管理画面、ログイン・転送ログ、日付抽出、CSV内容の確認。既存SQLiteデータの移行は別途バックアップ・スキーマ確認後の作業。
- ZIP：ZipArchiveのWeb側有効化、フォルダーZIP取得、別の新規フォルダーへの展開、日本語名と内容一致、既存展開先拒否。
- search／edit：既定のテキスト編集・日本語検索の対象。config／fileinfo／tinymce／editorck／fckeditorと編集ロックは未対応。
- URL取得：既定無効。使用時のみ`URL_HOSTS`への正確なホスト名の設定、cURLと公開IPv4・証明書検証の確認。HTTPS443のみ、リダイレクト・上書き・認証情報付きURLの拒否。MAXSIZE、LIMIT、転送15秒・接続5秒の制限。DNS解決時間を含む厳密な処理時間保証なし。
- 同時操作：同名アップロードは新規作成の競合拒否、テキスト同時編集・全操作の排他制御は保証外。共有容量LIMITの全リクエスト横断の厳密な割当保証なし。
- セッション：30分の無操作、8時間の絶対期限。実時間を待つ専用ケースTS-513〜516での確認。短時間試験の代用なし。

## 判定記録

| 項目 | 現在の状態 |
|---|---|
| SA-501 CLI側環境情報 | 取得済み |
| SA-501 Web側環境情報 | 利用者提供出力によるPASS、cgi-fcgi／必要拡張／HTTPSの確認 |
| SA-502 分離配置・基本受入 | 実施中・判定未実施。同一入力ZIPの提供後、アップロード、Chromeでのダウンロードとファイル確認完了との利用者回答。SHA-256による内容比較・残りの基本操作は結果待ち。クライアントOS・ブラウザー詳細版は未確認 |
| SA-503 任意機能・権限 | NOT RUN |
| 既存本番データ移行・本番切替 | 対象外、段階6の前提確認待ち |

実機の必要範囲が未実施の間は段階5全体の受入完了としない扱い。

## 分離配置用パッケージ

[検証用ZIP](verification/stage5/2026-10-24/sakura-preview.zip)、[SHA-256](verification/stage5/2026-10-24/sakura-preview-SHA256SUMS)、[setup.phpの内容](verification/stage5/2026-10-24/preview/setup.php)、[収録ファイル一覧](verification/stage5/2026-10-24/preview/manifest.json)。正式リリースではなく、段階5の受入用fixture。

ソースコミット`3ab04adb323c1f5fe2d8b1cd144b6cbb3d0a1eb4`、CI32ケース合格版と同一のアプリ。`ft2.php`をバイト変更なしで`index.php`へ改名した48ファイル構成。既存のindex.php運用に合わせた入口の確認用。元の利用者設定・ユーザー・DB・ファイルを含めないパッケージ。

ZIPを公開領域外へ展開後、CLIから次の4引数による`setup.php`実行。

```text
php setup.php 公開配置先の親ディレクトリ 非公開配置先の親ディレクトリ 公開ルート 対応HTTPSベースURL
```

生成対象は日時・乱数付きの新規公開フォルダーと新規非公開フォルダーのみ。公開親は公開ルート内、非公開保存先は公開ルート外という配置条件の検査。ソース全ファイルのSHA-256検査、既存ファイルを開かない排他的作成。設定に新規stage5ユーザーと乱数パスワードのハッシュ、LANG=ja、MAXSIZE=2,000,000、LIMIT=20,000,000、HTTPS=true、URL_HOSTS空文字列の指定。search／edit／db／log／zipの有効化、DBテーブル削除の無効化。config.phpは0600、非公開データ親は0700。

パスワードは実行した端末に一度表示する方式。チャット・リポジトリへのパスワードの提示なし。検証用URL、公開／非公開フォルダー、PHPログの位置も同時表示。CGI／FastCGIで実行ユーザーがファイル所有者と一致する構成向け。実機での権限確認はログイン・転送・ログ操作での受入対象。

PHPログは検証用configの読込み後のみ`log_errors=1`、E_ALL、非公開の`php-error.log`へ出力する構成。既存環境のphp.iniの変更なし。設定読込み前の起動エラーまで捕捉する保証なし。`Acceptance setup completed. No application request yet.`はセットアップの確認行、PHPエラーではない行。

入力資料は検証用URLと同じフォルダーの`acceptance-input/日本語.txt`（UTF-8／LF）と`acceptance-input/empty.txt`（0 byte）。端末へ保存後、アプリへアップロードする操作。管理対象DIRは公開外の空のfiles、公開された入力資料とは別の場所。初期一覧の空表示が正常な状態。

テキストのブラウザー保存が困難な場合は、[入力資料ZIP](verification/stage5/2026-10-24/acceptance-input.zip)を保存して解凍する方法。[ZIPのSHA-256](verification/stage5/2026-10-24/acceptance-input-SHA256SUMS)と、元の配備パッケージ内の2ファイルとのハッシュ一致確認済み。ZIP自体ではなく、解凍した`日本語.txt`と`empty.txt`をアプリからアップロードする手順。試験入力の内容・転送経路・判定基準の変更なし。

環境診断の`ft-php-check-...`と検証版の`ft-stage5-.../index.php`は別のURL。前者への`acceptance-input/`追加では入力ファイルの取得不可。利用者の取得エラーは前者のURLでの報告、アプリのダウンロード機能のFAILを示す証跡とは異なる記録。
