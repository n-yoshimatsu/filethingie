# ブラウザー・PHP依存

| 依存 | 版・扱い | 出所・許諾 |
|---|---|---|
| jQuery | 4.0.0、`js/jquery-4.0.0.min.js`として同梱 | [公式配布](https://code.jquery.com/jquery-4.0.0.min.js)、[公式リリース説明](https://blog.jquery.com/2026/01/17/jquery-4-0-0/)。MIT、同梱`js/jquery-LICENSE.txt` |
| 旧jQuery／TableSorter | 不使用の旧配布物・デモ・ビルドツールを今回のツリーから削除 | 元の著作権表示を含む旧版はGit履歴に保持 |
| 外部リッチテキストエディター | 旧3連携を無効化、本体の同梱なし | TinyMCE／CKEditor／FCKeditorの現行版導入・ライセンス選択は対象外 |
| PHP | 8.3、8.5 | [password_verify](https://www.php.net/manual/en/function.password-verify.php)、[セッション設定](https://www.php.net/manual/en/session.security.ini.php)、[fputcsv](https://www.php.net/manual/en/function.fputcsv.php) |
| PDO SQLite | DB／log使用時のみ必要 | 既存SQLite 3 DBとPDO。公開領域外の保存先が必要 |
| ZipArchive | ZIP作成・展開時のみ必要 | [getStreamIndex](https://www.php.net/manual/en/ziparchive.getstreamindex.php)。外部unzipコマンドを不使用 |
| Playwright Core | 1.56.1、開発試験専用、lockfileで固定 | [公式ドキュメント](https://playwright.dev/docs/library)。既存ローカルChromeを指定、本番へ配布不要 |

File Thingie本体の原著作権表示と[LICENSE](../LICENSE)を維持。新jQueryのバイト列は公式配布と一致を照合し、検証記録にSHA-256を保存。npm依存は`tools/browser/package-lock.json`で固定。

jQuery 4.0.0公式配布との照合値（SHA-256）：`39a546ea9ad97f8bfaf5d3e0e8f8556adb415e470e59007ada9759dce472adaa`。
