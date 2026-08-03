# 売上管理から未振込の加盟店へ督促LINEを一斉送信する設計

作成日: 2026-08-03

## 目的

`admin/sales`（売上管理）で、**その月の振込確認にチェックが入っていない加盟店へ、
同じ文面の督促LINEをまとめて送る**。文面の編集も同じ画面で行い、設定画面を行き来しなくて済むようにする。

現状は加盟店ごとの「請求書を送信」ボタンしかないため、未入金が10件あれば10回クリックする必要がある。

## スコープ外

- 督促の自動送信（バッチ・スケジュール実行）。今回は管理者の手動送信のみ
- 督促の送信回数や送信ログの一覧画面
- 振込確認の一括チェック（別途実装予定）
- 代理店画面からの督促送信

## 送信対象

表示中の月について、次をすべて満たす加盟店。

1. その月に確定注文（status 2/3/5/6）があり、売上管理の一覧に出ている
2. `merchant_payment_confirmations` にその月の行が**無い**（＝振込未確認）

**テスト加盟店（`merchants.is_test = 1`）も対象に含める。**
売上管理の一覧自体がテスト加盟店を含んだまま表示しているため、見えているものと送信対象を一致させる。

**すでに督促を送った加盟店も対象から外さない。** 督促は日を置いて2回目を送る運用があるため、
確認モーダルで「うち◯件はすでに送信済みです」と注意を出すに留める。

当月（未確定月）を表示しているときは督促ブロックごと非表示にする（`InvoiceService::isFixedMonth()`）。

## データ設計

新テーブル `payment_reminder_sends`。`invoice_line_sends` と同じ構成に揃える。

| カラム | 型 | 内容 |
|---|---|---|
| id | bigint | |
| merchant_id | unsignedBigInteger | 加盟店ID |
| month | string(7) | 対象月 `YYYY-MM` |
| line_id | string nullable | 送信先のLINEユーザーID |
| status | string | `success` / `failed` |
| error | text nullable | 失敗時のメッセージ |
| sent_at | timestamp nullable | 送信日時 |
| timestamps | | |

- `unique(['merchant_id', 'month'])`
- `updateOrCreate` で上書きするため、保持するのは**最後に送った1件**。
  一覧の「督促済 8/3」表示と、モーダルの送信済み件数の判定にはこれで足りる
- 失敗時に既存の `success` 行を上書きしない（再送失敗で成功記録を消さない）。
  `InvoiceLineSender` と同じ扱い

モデル: `App\Models\PaymentReminderSend`

文面テンプレートは `settings` テーブルに保存する（キー: `payment_reminder_message`）。

## サービス

### `App\Services\PaymentReminderMessageService`

`InvoiceLineMessageService` と同じ役割。督促用のテンプレートとプレースホルダを持つ。

| プレースホルダ | 内容 |
|---|---|
| `{merchant_name}` | 加盟店名 |
| `{month_label}` | 請求対象月（例: 2026年7月分） |
| `{month}` | 請求対象月（例: 2026-07） |
| `{total}` | ご請求金額（税込・送料込） |
| `{payment_due_date}` | 支払期限（請求月翌月の15日。請求書PDFと同じ計算） |
| `{invoice_url}` | 請求書ページのURL |

メソッド構成も `InvoiceLineMessageService` に揃える。

- `placeholders()` / `defaultTemplate()` / `template()` / `render()` / `renderSample()`

有効フラグ（`KEY_ENABLED`）と段階公開の絞り込み（`KEY_TARGET_IDS`）は持たない。
自動送信が無く、対象は画面上で目視できるため不要。

### `App\Services\PaymentReminderSender`

`InvoiceLineSender` と同じ形。1加盟店・1ヶ月分を送信する。

```
send(Merchant $merchant, string $month, ?string $overrideLineId = null): array
```

処理:

1. 送信先LINE IDを決める（`$overrideLineId` 優先、無ければ `$merchant->owner->line_id`）
2. LINE IDが無ければスキップ
3. **その月の振込確認済み行があればスキップ**
   モーダルを開いたまま別タブでチェックを入れた場合に、支払い済みの店へ督促が飛ぶのを防ぐ
4. `InvoiceService::forMonth()` で集計。注文0件ならスキップ
5. `PaymentReminderMessageService` で本文を生成し `LineMessageService::sendMessage()` で送信
6. `payment_reminder_sends` に `updateOrCreate` で履歴を保存
   （`$overrideLineId` 指定時＝テスト送信は履歴を残さない）

返り値: `['success' => bool, 'skipped' => bool, 'message' => string, 'sent_at' => Carbon|null]`

## ルート

既存の admin ルートグループ内に追加する。

```php
Route::post('sales/{merchant}/payment-reminder', [AdminSalesController::class, 'sendPaymentReminder'])
    ->name('sales.payment_reminder');
Route::post('sales/payment-reminder-message', [AdminSalesController::class, 'updateReminderMessage'])
    ->name('sales.payment_reminder_message');
```

- どちらも JSON を返す
- `sendPaymentReminder` は `month` をボディで受け、`YYYY-MM` 形式か検証。当月なら 400
- 倉庫アカウント（`permission = 2`）は `AdminPermission` ミドルウェアで自動的に 403 になる

**一斉送信専用のエンドポイントは作らない。** フロントから1件ずつ
`sales/{merchant}/payment-reminder` を順に叩く（理由は「実行方式」を参照）。

## コントローラ

`Admin\SalesController` に追加する。

**`index()` への追加**

- `$reminderSends` — 表示中の月の `payment_reminder_sends`（`success` のみ）を `merchant_id` で引ける形に
- `$reminderTargets` — 未振込の加盟店の配列（`merchant_id` / `merchant_name` / 督促済み日）
- `$reminderMessage` — 保存済みテンプレート（未設定なら既定テンプレート）
- `$reminderPreview` — 先頭の対象加盟店の実データで生成した本文。対象0件なら空文字

いずれも `$isFixedMonth` が false のときは計算しない。

**`sendPaymentReminder($merchantId, Request $request)`**

- 月の形式を検証、当月以降は 400
- `PaymentReminderSender::send()` を呼ぶ
- 返り値: `['success' => bool, 'skipped' => bool, 'message' => string, 'sent_at' => 'n/j' or null]`

**`updateReminderMessage(Request $request)`**

- `payment_reminder_message` を必須・4000文字以内で検証
- `Setting` に保存し `['success' => true]` を返す

## 画面

`resources/views/admin/sales/index.blade.php` の**集計ブロックと加盟店リストの間**に
督促ブロックを追加する。中身は `admin/sales/partials/reminder.blade.php` に切り出し、
index.blade.php の肥大化を防ぐ。

### 督促ブロック（畳んだ状態）

```
振込督促の一斉送信                     未振込 5件 / 12件
[文面を編集 ▼]                        [➤ 未振込の5件に送信]
```

- 対象が0件のときは送信ボタンを非活性にし、「未振込の加盟店はありません」と表示する
- 当月表示中はブロックごと出さない

### 文面エディタ（「文面を編集」で開く）

`admin/settings/invoice_line.blade.php` と同じUIを使う。

- 本文 textarea ＋ プレースホルダ挿入ボタン ＋ 文字数カウント（上限4000）
- 右側にLINEトーク風プレビュー（JS側でサンプル値を差し込む）
- 「文面を保存」ボタン → `sales.payment_reminder_message` へ POST

保存は送信とは独立した操作にする。文面だけ直して後で送る運用があるため。

### 確認モーダル

送信ボタンを押すと開く。**このモーダルが唯一のガード。**

- 対象加盟店の一覧。すでに督促済みの店には「督促済 8/3」バッジを付ける
- 送信済みが1件以上あれば「うち◯件はすでに送信済みです」の注意書き
- 実際に送られる文面のプレビュー（`$reminderPreview`。先頭の対象店の実データ）
- 「5件に送信する」ボタンと「キャンセル」ボタン
- **ボタンを押すまで一切送信しない。** 背景クリックや ESC でも閉じられるのは送信前のみ

### 実行方式

`QUEUE_CONNECTION=sync` のため、一括を1リクエストで処理するとPHPの実行時間上限に達する恐れがある。
**フロントから1件ずつ順にAJAXを投げる。**

- モーダル内に「送信中 3/5」と進捗を表示。送信中は閉じるボタンと背景クリックを無効化
- 1件失敗しても中断せず最後まで進める
- 完了後に「成功3件 / スキップ1件 / 失敗1件」の内訳と、失敗した加盟店名を表示
- 閉じると `location.reload()`（振込確認の既存挙動と揃える）

### CSS/JS

- CSSは `admin/sales/partials/reminder.blade.php` 内の `<style>` に書く。
  `resources/css/admin.css` は触らない
- JSも同ファイル内のインライン `fetch`。Vite の別ファイルにしない

理由: `public/build` は git 管理外で本番へ手動アップロードが必要なため、
インラインにしておけばデプロイ漏れによる機能不全が起きない。既存の設定画面と同じ方針。

## エラー処理

| ケース | 挙動 |
|---|---|
| オーナーのLINE ID未登録 | スキップ扱い。結果に加盟店名と理由を表示 |
| 対象月の注文なし | スキップ扱い |
| 送信直前に振込確認済みになっていた | スキップ扱い。理由を「振込確認済みのためスキップ」と表示 |
| LINE API が失敗 | `payment_reminder_sends` に `failed` で記録し、結果に加盟店名を表示 |
| 通信エラー | その1件を失敗として数え、次の加盟店へ進む |
| 送信ボタンの二重クリック | モーダルを開いた時点でボタンを非活性化 |
| 当月への送信 | サーバー側で 400（UIでも非表示にするが二重で防ぐ） |
| 文面が空 | 保存時にバリデーションエラー。空のまま送信ボタンは押せない |

## 動作確認手順

テストコードは書かない方針のため、手動で確認する。

1. `php artisan migrate` で `payment_reminder_sends` が作られること
2. 当月を表示 → 督促ブロックが出ないこと
3. 前月を表示 → 督促ブロックが集計ブロックとリストの間に出て、未振込の件数が一覧の未チェック数と一致すること
4. 「文面を編集」を開き、プレースホルダ挿入とプレビューが動くこと
5. 文面を保存 → リロードしても保存した文面が残ること
6. 送信ボタン → モーダルに対象一覧と文面プレビューが出ること。キャンセルで何も送られないこと
7. 送信実行 → 進捗が進み、対象店のLINEに届くこと
8. リロード後、送信した店に「督促済 n/j」が出ること
9. もう一度モーダルを開くと「うち◯件はすでに送信済みです」が出ること
10. LINE ID未登録の加盟店を含めて送信 → スキップとして結果に出て、他の店の送信は止まらないこと
11. モーダルを開いたまま別タブで振込確認をONにしてから送信 → その店がスキップされること
12. 全員が振込確認済みの月を表示 → 送信ボタンが非活性になっていること
13. 倉庫アカウント（permission=2）でURLに直接POSTして403になること
