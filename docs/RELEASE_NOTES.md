# File Thingie PHP 8.3／8.5版 — 2026-10-25

File Thingie 2.5.7をPHP 8.3／8.5へ対応させたフォークの配布版です。配布識別名は`php83-85-20261025`です。元のVERSION定数は2.5.7のまま維持しています。

PHP 8で使えないAPIの置換、認証・CSRF・セッションの更新、PDO SQLite・ZipArchive対応、jQuery 4.0.0、日本語名と空ファイルの処理、表示設定の追加を含みます。同名アップロードは既存内容を保持して拒否します。

アプリは受入済みの46ファイルと同一です。配布ZIPには導入・更新・復元の手順とmanifest、ライセンス、パスワードハッシュ生成ツールを添付します。設定・認証情報・保存データ・テスト用アカウントは含めません。

## 検証範囲

- PHP 8.3.36／8.5.12のローカル試験・GitHub Actions CI。
- Chrome／Firefox、HTTPS、URL取得、DB／ログ／ZIP、日本語・空ファイル、同名競合。アプリログインの30分無操作期限・8時間絶対期限は実時間試験。
- さくら・FreeBSD・PHP 8.3.36／cgi-fcgiでの分離配置受入と、既存Basic認証だけの移行リハーサル。
- 本番の停止時バックアップ、配置、通常UI操作、配備・保存データ・認証・PHPログの照合。個別の証跡は[段階6の結果](https://github.com/n-yoshimatsu/filethingie/blob/php85-support/docs/STAGE6_REPORT.md)を参照してください。
- PHP 8.5のさくら実機、全サーバー構成・全言語・全ブラウザーを検証したものではありません。

## 更新時の注意点

既存フォルダーへのZIP全体の上書き展開は行わず、[導入・更新・復元手順](https://github.com/n-yoshimatsu/filethingie/blob/php85-support/docs/RELEASE_GUIDE.md)に従って、独自設定・保存データの保全と別配置の確認を行ってください。

旧remember me、未完成のconfig／fileinfo、TinyMCE／CKEditor／FCKeditorの旧連携は無効です。SQLite 2の自動変換・編集の同時ロックは対象外です。Basic認証だけの利用方式は継続できますが、認証のない公開先でLOGIN=falseを使用しないでください。

本体とjQueryのライセンスを保持しています。配布ZIPのSHA-256は添付のSHA256SUMS、各ファイルはmanifest.jsonで照合できます。
