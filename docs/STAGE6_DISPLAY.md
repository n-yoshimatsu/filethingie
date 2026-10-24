# 段階6 既存表示・デザインの引継ぎ

利用者回答は「表示やデザインを変更した」。変更箇所と値は未取得、機能変更が一切ないとするソース確認は未実施。SA-602で改行以外の差があるindex.php／css/ft.cssを確認対象とする方針。

## 読取り専用比較

```text
php sakura-display-diff.php BACKUP_DIRECTORY HOME_DIRECTORY WEB_ROOT
```

[比較キット](verification/stage6/2026-10-24/sakura-display-diff.zip)を非公開作業領域へ展開して実行。SHA-256は[sakura-display-diff-SHA256SUMS](verification/stage6/2026-10-24/sakura-display-diff-SHA256SUMS)。保全コピー・配布候補のZIPの変更なし。

SA-602の保全コピーの完了マーカーとmanifestを検証し、app/index.phpとapp/css/ft.cssだけを読取り。各内容がmanifestに記録されたSHA-256と一致することの確認。比較基準は同梱した上流固定コミットa7511ad57e65b64d603e9cb68a20359ad0a3a4e6のft2.php／css/ft.css、同梱SHA-256の照合。

CRLFをLFへ揃えた一時コピーをホーム直下の新規0700ディレクトリに作成し、CLIのdiff -uで比較。出力ヘッダーの相対ラベル化、出力中のホームパスを<HOME>へ置換。今回作成した一時ファイルだけの後片付け。設定本文・管理データ本文の読取り、既存PHPの実行、復元先や本番への書込み、ネットワーク通信なし。

PHPのproc_openとCLIのdiffが必要。これらが利用できない場合は比較失敗として終了。PHP／diffのFreeBSD実機経路はSA-604で確認する範囲。

## 共有する差分の扱い

結果のdiff_linesは変更箇所と前後の行。見出し・URL・コメント等の利用者固有の文字列を含む可能性。共有前の利用者による目視確認と、パスワード等の秘密情報・個人情報の非共有。

password／passwd／secret／token等の認証情報を示す語が差分にある場合、そのファイルの差分全体を非表示とし、withheld_sensitive_marker=trueの記録。これは誤検出もある補助であり、任意の秘密情報を漏れなく検出する保証ではない仕様。非表示の場合は値を送らず、個別に確認方法を調整する扱い。

comparison_completed=trueは比較処理の完了だけの意味。差分の採用、本番移植、画面表示の互換性を検証した判定ではない範囲。SA-604で差分内容と引継ぎ方針を確認後、複製データを使った移行リハーサルで表示と通常操作を検証する順序。

## ローカル検証

TS-606／607、PHP 8.3.36／8.5.12の人工fixtureで各22比較PASS。タイトル・色の変更抽出、改行差の除外、ホームパスの非出力、認証語を含む差分の保留、設定非実行、比較元不変、改変・リンクの拒否、後片付け。

```bash
python3 tools/build-stage6-diff.py artifacts/stage6/reproduce-diff.zip
mkdir -p artifacts/stage6/reproduce-diff-kit
unzip -q artifacts/stage6/reproduce-diff.zip -d artifacts/stage6/reproduce-diff-kit
docker compose run --rm --no-deps -T -v "$PWD:/workspace:ro" --entrypoint php php83 /workspace/tools/stage6-diff-test.php /workspace/artifacts/stage6/reproduce-diff-kit
docker compose run --rm --no-deps -T -v "$PWD:/workspace:ro" --entrypoint php php85 /workspace/tools/stage6-diff-test.php /workspace/artifacts/stage6/reproduce-diff-kit
```
