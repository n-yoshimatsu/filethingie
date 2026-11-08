# PHP切替を伴う追加サイトの移行準備

2026年11月8日。既存WebはPHP 7.4、CLIでは明示パスのPHP 8.3を利用できる環境向けの追加手順。アプリ46ファイルは正式配布版と同一。既存サイトの識別情報・認証情報・保存データの非掲載。

## 旧入口の扱い

既存の `ft2.php` がある場合だけ、切替計画への追加。配置予定数は50、存在しない場合は従来どおり49。既存内容・通常権限・更新時刻の保全と、先の保全コピーとの一致確認。未レビューの変更やリンクの拒否。

停止時の `ft2.php` は `index.php` を読み込む短い入口への変更。両URLのGET・POSTとも、indexの停止中は503、新版の有効化後は新版への到達。元のURLの維持、別々の旧版・新版を並行実行する経路の解消。

`rollback` は両入口の停止を維持したプログラムの復元。稼働中に増えた保存データの保持。旧 `ft2.php` の実行再開はこの時点では行わない扱い。

`cancel --legacy-runtime-confirmed` は停止のみ、または `rollback` 完了後の旧入口復旧。Web側のPHPを旧アプリに対応する設定へ戻したことの、操作者による明示確認が必須。元の入口の内容・権限・更新時刻の復元。PHP設定自体の変更や、実際の旧アプリの稼働確認はツールの対象外。

## 実機の順序

1. 読取り専用棚卸し、保全コピー、隔離復元、表示差分のレビュー。
2. 新規フォルダーへの検証版・データ複製の配置。Webが旧PHPの場合はこの時点での画面操作の保留。
3. `sakura-cutover-prepare.php HOME REHEARSAL_ID` による非公開資材準備。検証版の内部照合と配置予定ファイルの保全。Web受入は別の確認であり、準備完了をWeb合格とする扱いなし。
4. 書込み経路・進行中処理・開始時刻の確認後、`stop ... --writers-idle`。両入口のBasic認証とHTTP 503の実測。
5. `backup ... --web-503-confirmed` による停止時の最終保全。管理ファイルと停止前入口は別々の保全。Web PHP設定変更前の元の版・モードの記録。
6. コントロールパネルによるWeb PHP変更。非公開0600設定を読める実行ユーザーの確認。Web版・SAPI・拡張・ログ・既存他サイトの表示確認。CLIのPHP表示をWeb確認の代用としない条件。
7. 検証版の通常UIからのWeb受入と、試験用ファイル削除後の内部照合。実データ原本への操作なし。
8. Web受入後の `apply`、続く `resume`。停止中の部分配置からの再開拒否。新版本番のWeb受入と `verify` による配置・保存データ・認証・ログ確認。
9. 異常時は入口の停止維持、必要に応じて `rollback`。旧PHP設定への復帰確認後だけの `cancel --legacy-runtime-confirmed` と旧サイトのHTTP確認。

この順序はPHP切替を伴う追加サイト向け。前回の受入結果の流用なし。さくらのPHP設定操作は操作者による実施。

## ローカル検証

入力：使い捨てLinux Apache環境、既存の人工Basic認証fixtureと通常HTTP、PHP 8.3.36／8.5.12。新規ケースは旧 `ft2.php` として固定の短い人工プログラムを入力。実機への故障注入・設定変更なし。

| ケース | 期待結果 | 結果 |
|---|---|---|
| TS-616／617 | 旧入口なしの停止・中断・配置・再開・復元、原本保全 | 各36比較PASS |
| 準備ツール既存自己検証 | 非公開49対象の準備、未レビュー変更の拒否 | 各20比較PASS |
| TS-620／621 | 旧入口を含む50対象、両URLの未認証401・認証後GET/POST 503、旧版への取消、新版両入口200、改変した入口の拒否、停止維持の復元、更新データ保持 | 各122比較PASS |

各ケース用fixtureはTS-612／613の35比較による準備。初期3試行はApache起動と再読込みの競合で本試験未到達、BLOCKED。初回ランナーのFAIL表記からの判定訂正、元ログのローカル保持。設定を起動前に配置するランナーでの再実行。最終[検証記録](verification/stage6/2026-11-08/legacy-cutover-tests.json)・[ソース対応](verification/stage6/2026-11-08/source-identity.json)。

さくら実機のPHP切替・旧本番アプリの稼働復旧は未検証。ローカルの取消試験は、対応済みWeb実行環境を操作者が確認した後のファイル復元とHTTPの検証。

再現例（出力先は新規パス）：

```sh
python3 tools/build-stage6-cutover.py artifacts/legacy-kit.zip
python3 -m zipfile -e artifacts/legacy-kit.zip artifacts/legacy-kit
python3 tools/stage6-legacy-test.py php83 baseline --kit artifacts/legacy-kit --output artifacts/legacy-test-83-baseline
python3 tools/stage6-legacy-test.py php83 legacy --kit artifacts/legacy-kit --output artifacts/legacy-test-83
python3 tools/stage6-legacy-test.py php85 baseline --kit artifacts/legacy-kit --output artifacts/legacy-test-85-baseline
python3 tools/stage6-legacy-test.py php85 legacy --kit artifacts/legacy-kit --output artifacts/legacy-test-85
```

事前に `docker compose build php83 php85` で検証イメージの作成。起動するコンテナーは外部ネットワークなし、ホストへのポート公開なし、終了時の削除。
