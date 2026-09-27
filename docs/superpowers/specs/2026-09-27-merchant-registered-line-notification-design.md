# 加盟店登録時の LINE 通知

作成日: 2026-09-27

きっかけ: 運用から「加盟店が登録されたのに通知が来ない」と連絡があった。

## 調査でわかったこと

通知が届かない原因は設定ミスではなく、**通知処理がそもそも実装されていない**ことだった。

加盟店を登録する経路は 3 つあるが、いずれも `Merchant::create()` の後に通知処理がない。

| 経路 | ファイル | 登録後の処理 |
| --- | --- | --- |
| LIFF | `app/Http/Controllers/MerchantController.php:134` | リッチメニュー切替 → `Log::info` → JSON 返却 |
| 管理画面 | `app/Http/Controllers/Admin/MerchantController.php:129` | redirect のみ |
| 代理店 | `app/Http/Controllers/Agency/MerchantController.php:70` | redirect のみ |

コントローラ全体で通知の呼び出しは `OrderController.php:318` の注文通知 1 か所だけ。
`app/Notifications` と `app/Jobs` はディレクトリごと存在しない。

### 実害はステータスにある

`merchants.status` は **1 = 有効 / 2 = 無効**（`resources/views/admin/merchants/edit.blade.php:46-47`）。
そして LIFF の登録フォームは

```php
// resources/views/merchants/create.blade.php:44
<input type="hidden" name="status" value="2">
```

となっており、**LIFF から登録された加盟店は必ず「無効」で入る**。
誰かが管理画面で有効に切り替えるまで使えない。通知が飛ばないと、この切り替えが
行われないまま放置される。これが「通知が来ない」の実害。

## 今回のスコープ

**加盟店が登録されたら、指定したユーザーの LINE に push 通知を送るところまで。**

対象

- `users` テーブルに `is_notify_target` フラグを追加
- 通知の送信を担うサービスクラスの新設
- 登録処理 3 か所からの呼び出し
- 管理画面のユーザー一覧で、フラグをチェックボックスで切り替えられるようにする

対象外（後続フェーズ）

- **LINE からのステータス有効化。** webhook エンドポイントも postback ハンドラも
  プロジェクトに存在しない（`routes/` と `app/Http/Controllers/` を検索してヒットゼロ）。
  公開 URL・署名検証・ハンドラ・権限チェックを新規に作ることになり、通知本体より
  大きい工事になるため分ける。
- **メール通知。** `EmailNotificationService` と同じ SMTP 経路になるが、
  この経路は認証が常時失敗する既知の未解決問題を抱えている。先に SMTP を直す必要がある。
  確認用に `php artisan test:smtp <宛先>` が用意されている（`app/Console/Commands/TestSmtp.php`）。

## 通知の宛先

送り先は「代理店オーナー兼、本部オーナー」にあたる特定の人。
この人たちは他のユーザーと同様に `users` テーブルに登録済みで、`line_id` を持っている。

宛先を環境変数に書くと、増減のたびにデプロイが必要になる。そのため
**`users` にフラグを立て、DB だけで宛先を管理できる形にする。**

### なぜ代理店テーブルを使わないか

`agencies` は 13 カラムで `line_id` を持たず、`email` しかない。
代理店はメール＋パスワードでログインする運用で LIFF ユーザーではないため、
LINE で送るには カラム追加 ＋ 代理店が自分の LINE を紐付ける画面（LIFF か LINE Login）
が必要になる。通知本体より紐付けのほうが大きい工事になるため採らない。

### カラム名を `is_owner` にしない理由

このプロジェクトには既に「オーナー」の意味が別にある。`merchants.user_id` が
加盟店のオーナーを指しており、`Merchant::owner()`（`app/Models/Merchant.php:68-71`）
として参照される。`users.is_owner` は「加盟店オーナーのフラグ」と読まれて混乱を招く。

「システム通知を受け取る人」を表す `is_notify_target` を採用する。
汎用名にしておけば、今後ほかの管理通知を足すときも同じフラグで送れる。

## データモデル

`users` テーブルに 1 カラム追加する。

```php
$table->boolean('is_notify_target')->default(false)->after('line_id');
```

既存行はすべて `false` で入るため、フラグを立てるまで誰にも通知は飛ばない。
デプロイは `php artisan migrate --force` を自動実行するが、カラム追加のみなので安全。

`app/Models/User.php` の `$fillable` に `is_notify_target` を追加し、
`$casts` に `'is_notify_target' => 'boolean'` を追加する。

## 通知サービス

`app/Services/MerchantRegisteredNotifier.php` を新設する。
既存の `InvoiceLineSender` / `PaymentReminderSender` と同じ粒度・同じ作法で書く。

送信経路は既存のものをそのまま使う。`LineMessageService` → `Line/DirectLineSender`
（`app/Services/Line/DirectLineSender.php:13-42`）が LINE の push API を叩く構成で、
請求書通知と入金リマインドが本番で稼働している実績がある。

### 宛先の取得

```php
User::where('is_notify_target', true)
    ->whereNotNull('line_id')
    ->get();
```

`line_id` が NULL のユーザーはフラグが立っていても送れないため、クエリの段階で除外する。

### エラー処理

**通知の失敗で加盟店登録を巻き添えにしてはいけない。** 例外はサービスの外に投げない。

- 宛先が 0 件 → `Log::warning`。フラグの立て忘れに気づけるようにする
- 1 件の送信に失敗 → `Log::error` に加盟店 ID と宛先を残し、**残りの宛先への送信は続行する**
- 予期しない例外 → サービス内で握りつぶして `Log::error`

## 通知本文

調査で判明した「登録直後は無効」を本文で明示し、有効化を促す。

```
新しい加盟店が登録されました

サロン名：{name}
加盟店コード：{merchant_code}
電話番号：{phone}
登録日時：{created_at}

現在このサロンは「無効」です。
管理画面から有効に切り替えてください。
{管理画面URL}
```

`merchant_code` は `Merchant::booted()` の `creating` フックで
`GOON-XXXX` 形式が自動採番される（`app/Models/Merchant.php:42-58`）ため、
登録直後から値が入っている。

`{管理画面URL}` は `route('admin.merchants.edit', $merchant->id)` で組み立てる。
`route()` は `APP_URL` を元に絶対 URL を返すため、そのまま LINE から開ける。
請求書通知で使っている `InvoiceService::invoiceUrl()`（LIFF の URL）とは別物で、
こちらは通知を受け取る側が管理画面にログインして操作することを想定している。

## 呼び出し箇所

3 経路すべてに入れる。管理画面と代理店の 2 か所は `Merchant::create()` の戻り値を
捨てているため、`$merchant` を受け取る形に変える。

| 経路 | ファイル | 変更内容 |
| --- | --- | --- |
| LIFF | `MerchantController.php:134` | `$merchant` は既にあるので呼び出しを足すだけ |
| 管理画面 | `Admin/MerchantController.php:129` | 戻り値を受け取ってから呼び出し |
| 代理店 | `Agency/MerchantController.php:70` | 戻り値を受け取ってから呼び出し |

## 管理画面 UI

`resources/views/admin/users/index.blade.php` の各行にチェックボックスを追加する。

このページには既に、行ごとの `<select>` を変更すると即座に保存する仕組みがある
（リッチメニューの切り替え。`:53` にセレクト、`:78-89` に fetch する JS）。
**このパターンをそのまま踏襲する。** 新しい作法を持ち込まない。

- 新規ルート: `POST /admin/users/{user}/update-notify-target`
- 新規アクション: `Admin\UserController::updateNotifyTarget`
- レスポンスは既存の `updateRichmenu` に合わせて JSON

同ページは各ユーザーの LINE ID を既に表示している（`:49`）ため、
「LINE ID を持っていない人にチェックを入れてしまう」ことを画面上で防げる。

## 注意点

テスト加盟店のオーナーに `is_notify_target` を立てると本番の通知が飛ぶ。
フラグを立てるのは人の操作なので運用で防げるが、認識しておく。

### 未決事項：テスト加盟店の除外要否

既存の注文通知は `TestDataFilter::isTestMerchant()` でテスト加盟店を除外している
（`app/Http/Controllers/OrderController.php:317`）。今回の加盟店登録通知にはこの
ガードが入っていない。そのため、テスト代理店の招待リンクから登録された加盟店でも
本部へ通知が飛ぶ。

ガードを入れる案（注文通知と揃える）と、あえて入れずに本番でテスト加盟店を使った
動作確認に使う案の両方が考えられ、**どちらにするかは未決**。次のフェーズ着手前に
方針を決める。
