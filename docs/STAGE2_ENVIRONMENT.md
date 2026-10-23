# PHP 8.3／8.5のローカル検証環境

> 段階4でDockerfileへZIP拡張のビルドを追加し、テスト用認証設定・有効プラグインも更新しています。この文書の実測値は段階2当時の記録です。現在の再実行条件は[段階4結果](STAGE4_REPORT.md)を参照してください。


この資料は段階2時点の記録です。未修正状態の再現にはコミット`d347456c2753fb02196af814fc9ca96357a0cad0`を使用してください。現在のブランチには段階3の修正と試験用追加ユーザーが含まれます。修正後の手順・期待結果は[段階3結果](STAGE3_REPORT.md)を参照してください。

公式PHP Apacheイメージを使うDocker Compose環境です。[試験条件](STAGE2_TEST_PLAN.md)と[実施結果](STAGE2_REPORT.md)を併せて参照してください。Linux上の再現環境であり、さくらのFreeBSD環境の完全再現ではありません。

## 起動・停止

Docker Engine、Docker Compose、Python 3、Gitを使用します。ホスト側へのPHP・Composer導入は不要です。リポジトリのルートで実行します。

```sh
docker compose config --quiet
docker compose up -d --build
docker compose ps
```

| サービス | 初期画面URL | 環境確認URL |
|---|---|---|
| php83 | http://127.0.0.1:18383/ft2.php | http://127.0.0.1:18383/__environment.php |
| php85 | http://127.0.0.1:18585/ft2.php | http://127.0.0.1:18585/__environment.php |

公開先は127.0.0.1のみです。ポート変更には環境変数`FT_PHP83_PORT`／`FT_PHP85_PORT`を使用できます。試験ツールは実際のポート割当てを読み取ります。未修正ソースの初期画面はHTTP 500になります。環境確認URLの正常応答だけでは、アプリが動作したとは扱いません。

ソースは構築時にイメージへコピーします。変更後は`docker compose up -d --build`で再構築します。ホストディレクトリのマウント・永続ボリュームはありません。試験データを初期状態に戻すには`docker compose up -d --force-recreate`でコンテナを再作成します。

終了時は次を実行します。このプロジェクトのコンテナとネットワークが削除され、コンテナ内の試験データ変更も失われます。ホスト側の保存済み証跡は残ります。

```sh
docker compose down
```

## 固定した環境と実測値

イメージはタグとmanifest digestで固定しています。更新時はdigest・実測版・試験結果を一緒に更新します。今回の実行アーキテクチャはamd64です。

| 項目 | php83 | php85 |
|---|---|---|
| 公式イメージ | `php:8.3-apache` | `php:8.5-apache` |
| manifest digest | `sha256:1b8b5b0f31cc4552fa3da2f0f02556a8207cbca6e86ec4882ff7b6a644c84a2b` | `sha256:7cd25eb48f9763dfeabe8fda3939897aca2001802fadeafa1b602f26c735ef96` |
| PHP | 8.3.36 | 8.5.12 |
| コンテナOS | Debian GNU/Linux 13（trixie） | 同左 |
| Apache | 2.4.69 | 2.4.69 |
| Web／CLI SAPI | `apache2handler`／`cli` | 同左 |
| 確認した拡張 | session、json、mbstring、fileinfo、PDO、pdo_sqlite、sqlite3、zlib、openssl、curl | 同左 |
| zip拡張／ZipArchive | なし | なし |
| 外部unzip | PATH上に存在せず | 同左 |
| `exec()` | 関数として存在 | 同左 |
| `get_magic_quotes_gpc()`／`sqlite_query()` | 関数として存在せず | 同左 |

起動確認のための追加拡張・外部コマンドの導入は行っていません。ZIP機能の検証時には、実運用環境と採用する実装に合わせた準備が必要です。関数や拡張の存在確認は、その機能の動作確認を意味しません。

`dev/php.ini`では`E_ALL`、ログ出力有効、画面へのエラー表示無効、UTF-8、Asia/Tokyo、アップロード上限2M、POST上限8M、メモリー上限128Mを設定しています。Webの実行時間上限は30秒、CLIの実測は0秒です。これらは試験条件であり、さくらの設定値を転記したものではありません。

参照：[Docker公式PHPイメージ](https://hub.docker.com/_/php)、[PHP CLIオプション](https://www.php.net/manual/en/features.commandline.options.php)、[PHPエラー設定](https://www.php.net/manual/en/errorfunc.configuration.php)（2026年10月24日確認）。

## 試験設定・データ

`dev/config.test.php`をコンテナの`config.php`にコピーします。試験専用のユーザー名は`stage2`、パスワードは`local-only-filethingie`です。公開されたダミーの認証情報であり、本番配備用ではありません。実際の`config.php`や`data/`をビルドに取り込まない構成です。

設定はサンプルを基に、管理対象`files`、言語`ja`、`HIDEFILEPATHS=true`、新規ディレクトリ0755に変更しています。search・editを有効、その他の同梱プラグインを無効にしています。Apacheで`files`の直接公開を拒否します。この設定追加を認証・認可試験の代わりには扱いません。

`dev/seed.php`がASCII名、日本語名、空白名、空ファイル、入れ子、読取り専用の6ファイルを作成します。`www-data`からのアクセス可否・SHA-256・モードを記録しました。0444による内容の書込み制限と、親ディレクトリの権限による削除可否は別です。

## 試験と証跡

以下のコマンドを独立して実行します。既存の出力ディレクトリへの上書きは拒否します。再試行時は`run1`を別名に変更します。終了コードは0＝PASS、1＝FAIL、2＝BLOCKED／INCONCLUSIVEです。未修正ソースでは4件とも終了コード1が基準結果です。

```sh
python3 tools/stage2.py lint php83 --output artifacts/stage2/run1/LINT-201
python3 tools/stage2.py lint php85 --output artifacts/stage2/run1/LINT-202
python3 tools/stage2.py startup php83 --output artifacts/stage2/run1/TS-201
python3 tools/stage2.py startup php85 --output artifacts/stage2/run1/TS-202
```

各ケース実行前にID・名称を進捗として表示します。`result.json`に環境と判定、`lint.json`に各ファイルの構文結果、HTTP関連ファイルと`container.log`に起動の証跡を保存します。コンテナと作業ツリーのPHPファイルのハッシュ不一致時はアプリ試験を開始しません。

今回の証跡は[verification/stage2/2026-10-24](verification/stage2/2026-10-24/)です。JSONとログの時刻はUTCです。`checkout_commit`は試験時の親コミット、当時未コミットの環境ファイルは`harness-sha256.json`で識別します。`environment.json`には環境・試験データ・追加PHPスクリプトの構文確認を記録しました。各`command`配列が収集コマンドです。

## さくら実環境の確認

利用者確認済みの条件はスタンダード、FreeBSD、選択可能な最新系列PHP 8.3、追加プラグインなしです。PHP 8.5は表示されていません。利用者がPHPを切り替え、CLIの出力が5.6.40から8.3.36へ変化したことと、File Thingieが動作しなくなったことを報告しています。Web側の詳細版・SAPI・拡張とエラーログは未確認です。

SSHでは次を確認できます。これらはCLI側の情報であり、Webの情報とは区別します。

```sh
uname -sr
php -v
php -r 'echo "PHP=", PHP_VERSION, "\nSAPI=", PHP_SAPI, "\n";'
php -m
```

Web側は、実際のFile Thingie配置先と同じディレクトリに一時PHPファイルを置き、HTTP経由で確認します。次はbashでのファイル作成例です。既存ファイルと重ならない名前を選びます。

```sh
cat > php-runtime-check-20261024.php <<'PHP'
<?php
header('Content-Type: text/plain; charset=UTF-8');
echo 'PHP=', PHP_VERSION, "\n";
echo 'SAPI=', PHP_SAPI, "\n";
PHP
```

ブラウザーで当該ファイルのURLを開くか、次のコマンドで取得します。ディレクトリURLではなく、作成したファイルのURLを指定します。

```sh
curl -fsS 'https://ご自身のドメイン/配置先/php-runtime-check-20261024.php'
```

HTTP 401の場合はサイト側の認証が必要です。Basic認証なら`curl -fsS --user 'ユーザー名' '確認用ファイルのURL'`で取得でき、パスワードは実行後に入力します。確認後は配置先で`rm php-runtime-check-20261024.php`を実行して削除します。

`apache2handler`はApacheモジュール、`cgi-fcgi`はCGI／FastCGI、`fpm-fcgi`はPHP-FPMです。CLIの結果だけでWebの動作モードを確定しません。[PHP SAPI](https://www.php.net/manual/en/function.php-sapi-name.php)

スタンダードはCGI／モジュールの両方に対応しており、コントロールパネルの選択も照合します。[さくらのPHP設定案内](https://help.sakura.ad.jp/rs/2241/)

後続確認はFreeBSDの詳細版、Web側の拡張・容量制限・実行制限、ファイル権限、日本語名の保存と取得、ZIP・URL取得です。公式のnginx＋Apache構成とローカルのApache単体構成との差異も残ります。[さくらの基本仕様](https://help.sakura.ad.jp/rs/2251/)

この作業では本番への配置や設定変更を実施していません。利用者自身によるPHP切替の報告と、こちらのローカル検証を区別して記録しています。
