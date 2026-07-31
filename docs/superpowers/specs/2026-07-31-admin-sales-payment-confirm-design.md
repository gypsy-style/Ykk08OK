# 管理画面 売上一覧に「振込確認」と「請求書送信」を追加する設計

作成日: 2026-07-31

## 目的

`admin/sales`（売上管理の加盟店一覧）で、加盟店ごとに次の2つを行えるようにする。

1. **振込確認** — その月の請求に対する入金を確認したことを記録する
2. **請求書送信** — オーナーのLINEへ請求書の案内を手動で送る

請求書の月次LINE自動送信（`invoice:send-line`）は既に実装済み。今回の送信ボタンは
**同じ本文・同じ履歴管理のまま、管理者が任意のタイミングで1件だけ送れるようにする**もの。

## スコープ外

- 請求書PDFそのものをLINEで送ること（LINEの仕様上できない。従来通りLIFFページのURLを送る）
- 振込確認の絞り込み・サマリー表示
- 振込確認と請求書送信の連動（送信済みでも未入金はあり得るため、独立した操作とする）

## データ設計

新テーブル `merchant_payment_confirmations`。

| カラム | 型 | 内容 |
|---|---|---|
| id | bigint | |
| merchant_id | unsignedBigInteger | 加盟店ID |
| month | string(7) | 対象月 `YYYY-MM` |
| admin_id | unsignedBigInteger nullable | 確認した管理者 |
| confirmed_at | timestamp | 確認日時 |
| timestamps | | |

- `unique(['merchant_id', 'month'])`
- **チェックON = 行を作る / チェックOFF = 行を削除する。** 状態を表す列は持たない
- 誰がいつ確認したかは `admin_id` と `confirmed_at` に残る

モデル: `App\Models\MerchantPaymentConfirmation`（`invoice_line_sends` と同じ書き方に揃える）

## 送信ロジックの共通化

現在 `App\Console\Commands\SendMonthlyInvoiceLine` の中に「本文生成 → LINE送信 → 履歴保存」が
インラインで書かれている。管理画面から同じことをするため、1加盟店・1ヶ月分の送信を
新サービス `App\Services\InvoiceLineSender` に切り出す。

```
send(Merchant $merchant, string $month, ?string $overrideLineId = null): array
```

処理内容:

1. 送信先LINE IDを決める（`$overrideLineId` があればそれ、無ければ `$merchant->owner->line_id`）
2. LINE IDが無ければ送信せず失敗を返す
3. `InvoiceService::forMonth()` で集計し、`InvoiceLineMessageService` で本文を生成
4. `LineMessageService::sendMessage()` で送信
5. `invoice_line_sends` に `updateOrCreate` で履歴を保存
   （`$overrideLineId` 指定時＝テスト送信は履歴を残さない。既存コマンドの挙動を維持）

返り値: `['success' => bool, 'message' => string]`

`SendMonthlyInvoiceLine` は対象の絞り込み・`--dry-run`・`--force`・`--to` などの制御だけ残し、
送信本体はこのサービスに委譲する。これにより本文・履歴の扱いがバッチと管理画面で必ず一致する。

## ルート

既存の admin ルートグループ内に追加する。

```php
Route::post('sales/{merchant}/payment-confirm', [AdminSalesController::class, 'togglePaymentConfirm'])
    ->name('sales.payment_confirm');
Route::post('sales/{merchant}/send-invoice', [AdminSalesController::class, 'sendInvoiceLine'])
    ->name('sales.send_invoice');
```

- どちらも `month`（`YYYY-MM`）をリクエストボディで受け、形式を検証する
- 倉庫アカウント（`permission = 2`）は `AdminPermission` ミドルウェアのホワイトリスト方式により
  自動的に 403 になる。追加の権限実装は不要
- どちらも JSON を返す

## コントローラ

`Admin\SalesController` に2メソッドを追加する。

**`togglePaymentConfirm`**
- 月が `YYYY-MM` 形式か検証、当月以降は 400
- 既存行があれば削除、無ければ作成（`admin_id` は `Auth::guard('admin')->id()`）
- 返り値: `['confirmed' => bool, 'confirmed_at' => '7/31' 形式の文字列 or null]`

**`sendInvoiceLine`**
- 月が `YYYY-MM` 形式か検証、当月以降は 400（`InvoiceService::isFixedMonth()` を使う）
- `InvoiceLineSender::send()` を呼ぶ
- 返り値: `['success' => bool, 'message' => string, 'sent_at' => '7/31' 形式 or null]`

`index()` では、表示中の月の振込確認状況と送信履歴をまとめて取得し、
`merchantSales` の各行から参照できるようにビューへ渡す（加盟店ごとにクエリを撃たない）。

## 画面

`resources/views/admin/sales/index.blade.php` の加盟店一覧の各行に追加する。

- **振込確認チェックボックス** — クリックで即 POST。確認済みなら「確認済 7/31」を併記
- **「請求書を送信」ボタン** — 送信済みなら「送信済 7/31」を併記し、押下時に
  「再送しますか？」の確認ダイアログを出す。未送信ならダイアログ無しで送信

**当月（未確定月）を表示しているときは、チェックボックスも送信ボタンも表示しない。**
当月は請求書自体が確定していないため。

JSは `admin/settings/invoice_line.blade.php` と同じく **blade内のインライン `fetch`** で書く。
Vite の別ファイルにしないのは、`public/build` が git 管理外で本番へ手動アップロードが必要なため。
インラインならデプロイ漏れによる機能不全が起きない。

## エラー処理

| ケース | 挙動 |
|---|---|
| オーナーのLINE ID未登録 | 送信せず「オーナーのLINE IDが未登録です」を行内に表示 |
| LINE API が失敗 | `invoice_line_sends` に `failed` で記録し、行内にエラー表示 |
| 二重クリック | 送信中はボタンを `disabled` にする |
| 当月への操作 | サーバー側で 400 を返す（UIでも非表示にするが二重で防ぐ） |
| 通信エラー | 行内に「通信に失敗しました」を表示し、チェックボックスは元の状態に戻す |

## 動作確認手順

テストコードは書かない方針のため、手動で確認する。

1. `php artisan migrate` でテーブルが作られること
2. 前月を表示し、振込確認をON → リロードしても確認済みのままであること
3. 同じチェックをOFF → リロードしても未確認に戻っていること
4. 当月を表示し、チェックボックスと送信ボタンが出ないこと
5. LINE ID未登録の加盟店で送信 → エラーメッセージが出て履歴が残らないこと
6. LINE ID登録済みの加盟店で送信 → LINEが届き「送信済」表示になること
7. もう一度送信 → 確認ダイアログが出て、OKで再送されること
8. `php artisan invoice:send-line --dry-run` が従来通り動くこと（本文が変わっていないこと）
9. 倉庫アカウント（permission=2）でログインし、2つのURLに直接POSTして403になること
