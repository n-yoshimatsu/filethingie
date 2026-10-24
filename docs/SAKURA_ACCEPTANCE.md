# さくら・FreeBSD受入手順

対象：スタンダード／FreeBSD／PHP 8.3。利用者によるターミナル・Windows Chrome操作と、実行依頼後のUbuntu Chrome自動操作による実施。既存運用ディレクトリとデータへの上書きなし。段階5時点での開発検証版。

## SA-501 環境情報

利用者から受領したCLI出力（2026年10月24日）：

```text
FreeBSD 13.0-RELEASE-p15
PHP 8.3.36 / SAPI cli
session, json, PDO, pdo_sqlite, zip, openssl, curl: true
upload_max_filesize=5M / post_max_size=8M / memory_limit=128M
```

CLIとWeb情報の利用者提供出力による確認済み。Web側はPHP 8.3.36、cgi-fcgi、FreeBSD 13.0-RELEASE-p15、HTTPS=on。session／json／PDO／pdo_sqlite／zip／openssl／curlはすべて有効。Web側upload_max_filesizeとpost_max_sizeは1024M、memory_limitは128M、max_execution_timeは30秒。display_errorsとlog_errorsは無効、opcache.enableとopcache.validate_timestampsは有効。CLIとWebで異なる容量上限の記録。診断ページ削除は利用者回答による確認済み。最初の複数行コマンドの改行消失による`uname`引数エラー、`&&`連結後の正常取得。

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
| SA-502 分離配置・基本受入 | PASS。Windows転送2ファイルのSHA-256一致、Ubuntu Chromeの基本操作・認証62比較、利用者提供の操作後PHPログ追加行0件。Windows側の詳細版は未確認 |
| SA-503 任意機能・権限 | PASS。ログ・CSV・ZIP64比較、直接URL保護6比較、利用者提供CLI出力による非公開配置・権限・PHPログの確認 |
| 既存本番データ移行・本番切替 | 対象外、段階6の前提確認待ち |

[総合受入記録](verification/stage5/2026-10-24/sakura-acceptance.json)によるSA-501〜503のPASS。Web自動操作132比較とサーバー内部16比較の計148比較、Windows転送・環境情報・ツール自己検証とは別集計。段階5全体は8時間絶対期限TS-515／516の結果待ち。診断ページの後片付けは利用者回答による削除済み確認。

[転送の照合記録](verification/stage5/2026-10-24/sakura-transfer.json)。実際の入力名は、後述の分離配備パッケージに含む`日本語.txt`と`empty.txt`。手順6の一般例にある`acceptance-`接頭辞とは異なる実施名の記録。配布元マニフェストと利用者のPowerShell出力に基づく内容比較、端末フルパスの非収録。

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

### Windows側での転送後の内容確認

Chromeのダウンロード先がWindows内の場合、Windows側のPowerShellでの計算。WSLからSSH接続したUbuntu・さくら上のファイルとの取り違え防止。`Windowsキー＋R`から`powershell`を起動後、次の1行の実行。選択画面でアプリからダウンロードした2ファイルをCtrlキー併用で選択。ファイルへの書込みなし。

```powershell
Add-Type -AssemblyName System.Windows.Forms; $ftDialog = New-Object System.Windows.Forms.OpenFileDialog; $ftDialog.Multiselect = $true; if ($ftDialog.ShowDialog() -eq 'OK') { Get-FileHash -Algorithm SHA256 -LiteralPath $ftDialog.FileNames | Format-List Path,Hash }; $ftDialog.Dispose()
```

比較対象は[配布元マニフェスト](verification/stage5/2026-10-24/preview/manifest.json)の入力ファイルSHA-256。`日本語.txt`は`6bb6a2b9c3419a359d34f0b4aea0deae66b360bd4bf7255e4c844dbeb0e9f83e`、`empty.txt`は`e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855`。ハッシュ文字列の大文字・小文字の区別なし。報告対象はファイル名とHash値のみ、Windows利用者名を含むフルパスの提示不要。

根拠：[Get-FileHash](https://learn.microsoft.com/en-us/powershell/module/microsoft.powershell.utility/get-filehash?view=powershell-5.1)、[OpenFileDialog](https://learn.microsoft.com/en-us/dotnet/api/system.windows.forms.openfiledialog?view=windowsdesktop-9.0)。手順の整備と、利用者PCでの実行結果の受領との区別。

### 作成と2種類の編集保存

SA-502手順7の利用者操作用の具体化。新規の検証データのみの操作、既存本番データへの適用なし。

1. 最初の一覧の「フォルダー作成」で「フォルダー」を選択、`受入テスト`の作成。一覧への追加確認。
2. 同じ欄の「ファイル」で`操作テスト.txt`の作成。行の左端の◇から「編集」・「編集する」を選択、初期内容が空であることの確認。
3. 1行目に`保存確認1`を入力して「保存」の実行。完了表示後の「キャンセル」による一覧への復帰、編集画面を再度開いた際の内容残存確認。AJAX保存の持続確認。
4. 2行目に`保存確認2`を追加して「保存して終了」の実行。再編集時の2行残存と一覧への復帰。通常保存の持続確認。

後続の複製・改名・移動・検索・削除は別の操作記録。作成・保存だけでこれらをPASSにしない扱い。

### 自動ブラウザー操作への切替

利用者からの実行依頼に応じた、Ubuntu上のChromeによる実機HTTPS画面の自動操作。Windows Chromeでの転送・ハッシュ照合は先行する別の証跡として保持。現在の認証済みブラウザー・端末セッションへ直接接続できないため、一時資格情報の準備後の実行。

`tools/sakura-credentials.py`による対話入力。Basic認証と検証版stage5のパスワードの非表示入力、0700の専用一時ディレクトリ内の0600ファイルへの保存、2時間の使用期限、チャット・リポジトリへの資格情報追加なし。全ブラウザー確認終了後の一時資格情報ファイル・親ディレクトリ削除済み。

```text
python3 tools/sakura-credentials.py HTTPSの検証版URL
node tools/browser/sakura.cjs basic 資格情報ファイル 新規証跡ディレクトリ
node tools/browser/sakura.cjs plugins 資格情報ファイル 別の新規証跡ディレクトリ
node tools/browser/sakura.cjs protection 資格情報ファイル さらに別の新規証跡ディレクトリ
```

- SA-502：通常フォームのログイン、誤パスワード拒否、Cookie属性、作成、通常／AJAX保存、複製、同名衝突、改名、移動、日本語検索、取得内容、削除、ログアウトと未認証取得拒否。
- SA-503：実アップロード／ダウンロードのログ、実際に記録された日付による範囲抽出、画面とCSVの一致、フォルダーZIP取得、日本語・空ファイルの内容、展開、既存展開先拒否。
- SA-503直接URL保護：Basic認証を通した設定PHPの200・空応答、公開配下のSQLite・PHPログ・配備記録に対する403／404。URL上の非露出と、サーバー内の実配置・権限との別々の確認。
- 乱数名の新規検証フォルダー内の操作。成功時のUIからの削除、失敗時の証跡・検証ファイルの保持。元の管理データ・手動試験ファイルの操作なし。
- HTTPS証明書検証の維持、対象プレビューと同じorigin・パス配下への接続制限、認証Cookie・内部状態の注入なし。非公開PHPログ・SQLite配置・権限の確認は別途、UI部分のPASSと受入全体の完了との区別。

2026年10月24日09:40〜09:43 JSTの実機実行。Chrome 156.0.8078.47、基本操作62比較・ログ等64比較・URL保護6比較の計132比較すべてPASS。[実行記録](verification/stage5/2026-10-24/sakura-ui/summary.json)。作成した自動テスト用ファイル・フォルダーのUIからの削除済み。既存本番設定・データ・先行手動試験ファイルの変更なし。画面画像・CSV原文はローカルの非公開証跡、公開JSON内のホスト・非公開パスは伏字化。

環境診断の`ft-php-check-...`と検証版の`ft-stage5-.../index.php`は別のURL。前者への`acceptance-input/`追加では入力ファイルの取得不可。利用者の取得エラーは前者のURLでの報告、アプリのダウンロード機能のFAILを示す証跡とは異なる記録。

### サーバー内部の読取り専用確認

`tools/sakura-verify.php`による、固定した分離配備パッケージに限定したCLI確認。引数は検証版公開フォルダー、非公開配置の親、公開ルートの順序。

```text
php tools/sakura-verify.php 検証版公開フォルダー 非公開配置の親 公開ルート
```

配備48ファイルのSHA-256、公開外のデータ配置、設定0600・非公開親0700・ログ0600、設定内の保存先とSQLiteヘッダー、PHPログのセットアップ確認行以外の行数の確認。設定PHPの実行、DB書込み、ファイル内容変更なし。設定本文・パスワード・ログ本文・フルパスの出力なし。ログに追加行がある場合はFAIL、空のログと確認行のみの場合は診断通知なしの判定。

PHP 8.3／8.5の人工fixtureで、正常状態・ログ追加行・設定権限不一致・ソース不一致・検証用以外のディレクトリ拒否の計10自己検証のPASS。[自己検証記録](verification/stage5/2026-10-24/sakura-ui/verifier-selftest.json)。このツールの自己検証と、さくら実機での実行結果との区別。2026年10月24日09:54:05 JSTの利用者提供実機出力は16比較すべてPASS。

[サーバー内部の実機出力](verification/stage5/2026-10-24/sakura-server.json)による配備48ファイルのSHA-256一致、非公開親・保存先0700、設定・SQLite・ログ0600の確認。PHPログはセットアップ確認行以外0行、確認行だけのSHA-256とも一致。実機Web操作終了後のログ検査。利用者によるCLI実行結果の受領であり、実行者の直接SSH実測とは異なる証跡源。
