# 段階6 準備段階の結果

2026年10月24日。段階6全体は進行中、本番の切替・正式タグは未実施。

## 検証済みの範囲

| ID | 対象 | 結果 | 根拠 |
|---|---|---|---|
| SA-601 | さくら本番の読取り専用棚卸し | PASS | 利用者提供のCLI出力、設定候補・認証配置・337ファイル／約1.15 GBの把握。実効認証や切替の判定対象外 |
| TS-601／602 | 棚卸しツール、PHP 8.3.36／8.5.12 | PASS | 共用解析処理への変更後、各32比較一致。秘密情報非出力・設定非実行・原本不変 |
| TS-603 | 配布候補の再現と対象確認 | PASS | 8比較一致。同じ固定元からのZIP再現、46ファイルの段階5ハッシュ一致、ライセンス包含、設定・認証・データ・受入入力の非同梱 |
| TS-604／605 | 保全コピーと隔離復元、PHP 8.3.36／8.5.12 | PASS | 各34比較一致。日本語・空・12 MiBファイル、隠しファイル、内部／外部／子フォルダー参照の認証関連。内容・通常権限・更新時刻の復元一致、原本不変、改変・逸脱・リンク・動的設定・未完了コピーの拒否 |
| SA-602 | さくら本番の保全コピー | NOT RUN | 検証済みキットの利用者実行待ち。エージェント側SSH接続先なし |

ローカル5ケース140比較のPASS、SA-601の棚卸しレビューPASS。ローカル試験環境はLinuxコンテナー、さくらFreeBSDでの保全・復元ツールの動作は未検証。ツールの合格と本番移行完了の区別。

最初のコピー試験は両PHPで34比較PASS。その後、子フォルダーの.htaccessからの認証ファイル参照を追加保全対象とした同じシナリオの再実行も各34比較PASS。掲載証跡は最終版。失敗を省略したPASS記録ではない経緯。

[最新結果一覧とツールハッシュ](verification/stage6/2026-10-24/preparation-summary.json)。旧棚卸し試験結果は同ディレクトリのsummary.jsonに保持。

## 配布候補

アプリ固定コミット`3ab04adb323c1f5fe2d8b1cd144b6cbb3d0a1eb4`、index.phpへのファイル名変更のみ、バイト列の変更なし。サンプル設定とライセンスを含む46ファイル。

- [アプリZIP](verification/stage6/2026-10-24/filethingie-php83-85-candidate.zip)
- [保全・復元用CLIキット](verification/stage6/2026-10-24/sakura-backup-kit.zip)
- [SHA-256](verification/stage6/2026-10-24/SHA256SUMS)
- [アプリmanifest](verification/stage6/2026-10-24/candidate-manifest.json)

正式リリース前の候補。本番への直接上書き展開用ではない状態。保全キットには本番設定や認証情報の同梱なし。

## 再実行

リポジトリ直下、既存のPHP検証用Dockerイメージを使う手順。出力先は未作成の名前を指定。

```bash
python3 tools/stage6-package-test.py artifacts/stage6/reproduce-package
```

TS-604／605のfixtureは公開ソース比較用manifestのみ人工データへ差替えた隔離キット。実際のツールのPHPコードは配布キットと同一、元のPHPコードや判定処理の差替えなし。

```bash
mkdir -p artifacts/stage6/reproduce-kit
unzip -q artifacts/stage6/reproduce-package/first/sakura-backup-kit.zip -d artifacts/stage6/reproduce-kit
cp docs/verification/stage6/2026-10-24/backup-fixture-manifest.json artifacts/stage6/reproduce-kit/upstream-manifest.json
docker compose run --rm --no-deps -T -v "$PWD:/workspace:ro" --entrypoint php php83 /workspace/tools/stage6-backup-test.php /workspace/artifacts/stage6/reproduce-kit
docker compose run --rm --no-deps -T -v "$PWD:/workspace:ro" --entrypoint php php85 /workspace/tools/stage6-backup-test.php /workspace/artifacts/stage6/reproduce-kit
```

## 残る作業

本番保全コピーの検証、上流との差分に応じた独自変更の確認、複製データを使うBasic認証だけのリハーサル、実機隔離復元、最終停止バックアップ・切替・再確認、正式リリース。現在の公開URL・Basic認証・既存保存データの維持方針。

認証の未解決参照は取得した事実として記録、現在の認証不良とする断定なし。旧PHPアプリを戻してもPHP 8.3での稼働回復とは別問題のため、旧ソースの保全とサービス復旧の区別。
