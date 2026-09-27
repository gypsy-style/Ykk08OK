# 加盟店登録時のLINE通知 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 加盟店が登録されたとき、`users.is_notify_target` が立っていて `line_id` を持つユーザー全員の LINE へ push 通知を送る。

**Architecture:** `users` にフラグを 1 つ足し、送信を担う `MerchantRegisteredNotifier` を新設する。登録処理 3 経路からこのサービスを呼ぶ。送信経路は既存の `LineMessageService` → `Line/DirectLineSender` をそのまま使う（請求書通知・入金リマインドで本番稼働中）。フラグの切り替えは管理画面のユーザー一覧にチェックボックスを足して行う。

**Tech Stack:** Laravel / PHP、Blade、MySQL、LINE Messaging API（push）、Docker Compose（サービス名 `php_ykk08ok`、アプリは http://localhost:8884）

設計書: `docs/superpowers/specs/2026-09-27-merchant-registered-line-notification-design.md`

## Global Constraints

- **テストコードは書かない**（プロジェクト方針）。既存のテストスイートは壊れている。検証は `artisan tinker` / `curl` / ヘッドレス Chrome で行う
- `php artisan migrate:fresh` / `migrate:refresh` / `db:wipe` は**絶対に実行しない**（ローカル DB 全消し事故あり。シーダーでは戻らない）
- `.progress.json` には**一切触らない**（並行して動いている別セッションの状態が入っている）
- `resources/css/admin.css` と `public/build` は**触らない**。gitignore 済みで手動 FTP アップが必要。CSS を足すときは Blade の inline `<style>` に書く
- いま触っているファイルの既存スタイルに合わせる。大規模リファクタはしない
- カラム名は `is_notify_target`。`is_owner` は使わない（`merchants.user_id` ＝加盟店オーナー / `Merchant::owner()` と意味が衝突するため）
- **通知の失敗で加盟店登録を失敗させない。** `MerchantRegisteredNotifier` は例外を外へ投げない
- ルート名はグループで `admin.` プレフィックスが付く。`->name('users.update-notify-target')` と書けば最終的に `admin.users.update-notify-target` になる
- DB アクセスは Eloquent。文字列連結の生 SQL は禁止
- `env()` は config ファイル内でのみ使用する
- コミットメッセージは日本語。末尾に `Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>` を付ける

---

## File Structure

| ファイル | 責務 |
| --- | --- |
| `database/migrations/2026_09_27_000001_add_is_notify_target_to_users_table.php` | 新規。`users` に `is_notify_target` を追加 |
| `app/Models/User.php` | 変更。`$fillable` と `$casts` にフラグを追加 |
| `app/Services/MerchantRegisteredNotifier.php` | 新規。本文の組み立てと、通知対象全員への送信 |
| `app/Http/Controllers/MerchantController.php` | 変更。LIFF 登録後に通知を呼ぶ |
| `app/Http/Controllers/Admin/MerchantController.php` | 変更。管理画面登録後に通知を呼ぶ |
| `app/Http/Controllers/Agency/MerchantController.php` | 変更。代理店登録後に通知を呼ぶ |
| `routes/web.php` | 変更。フラグ更新のエンドポイントを 1 本追加 |
| `app/Http/Controllers/Admin/UserController.php` | 変更。`updateNotifyTarget` アクションを追加 |
| `resources/views/admin/users/index.blade.php` | 変更。行ごとのチェックボックスと fetch する JS |

---

### Task 1: マイグレーションと User モデル

**Files:**
- Create: `database/migrations/2026_09_27_000001_add_is_notify_target_to_users_table.php`
- Modify: `app/Models/User.php:20-27`（`$fillable`）、`app/Models/User.php:43-45`（`$casts`）

**Interfaces:**
- Consumes: なし（最初のタスク）
- Produces: `users.is_notify_target`（boolean, default false）。`App\Models\User` の `is_notify_target` プロパティが boolean にキャストされ、mass assignment 可能になる

- [ ] **Step 1: マイグレーションを作成する**

`database/migrations/2026_09_27_000001_add_is_notify_target_to_users_table.php` を新規作成。
既存の `2026_09_16_000001_add_is_test_to_agencies_table.php` と同じ書き方に揃えること。

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_notify_target')->default(false)->after('line_id'); // システム通知の送信対象
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_notify_target');
        });
    }
};
```

- [ ] **Step 2: マイグレーションを流す**

Run:
```bash
docker compose -f /Users/sawadakeisuke/workspace/Ykk08OK/docker-compose.yml exec -T php_ykk08ok php artisan migrate
```

Expected: `2026_09_27_000001_add_is_notify_target_to_users_table ... DONE`

**`migrate:fresh` は絶対に使わないこと。**

- [ ] **Step 3: カラムが増えたことを確認する**

Run:
```bash
docker compose -f /Users/sawadakeisuke/workspace/Ykk08OK/docker-compose.yml exec -T php_ykk08ok php artisan db:table users
```

Expected: 列の一覧に `is_notify_target boolean` が現れ、`line_id` の直後に並んでいる。カラム数が 11 → 12 になる。

- [ ] **Step 4: User モデルに追記する**

`app/Models/User.php` の `$fillable` の最後（`'richmenu_id',` の次）に 1 行足す。

```php
    protected $fillable = [
        'merchant_id',
        'name',
        'display_name',
        'email',
        'password',
        'line_id',
        'richmenu_id',
        'is_notify_target',
    ];
```

`$casts` にも 1 行足す。

```php
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_notify_target' => 'boolean',
    ];
```

- [ ] **Step 5: 更新と絞り込みができることを確認する**

Run:
```bash
docker compose -f /Users/sawadakeisuke/workspace/Ykk08OK/docker-compose.yml exec -T php_ykk08ok php artisan tinker --execute="\$u = App\Models\User::first(); \$u->update(['is_notify_target' => true]); echo 'count=' . App\Models\User::where('is_notify_target', true)->count() . ' type=' . gettype(App\Models\User::first()->is_notify_target);"
```

Expected: `count=1 type=boolean`

（`type=boolean` になっていれば `$casts` が効いている。整数の `1` が返る場合は `$casts` の追記漏れ）

- [ ] **Step 6: 確認用に立てたフラグを戻す**

Run:
```bash
docker compose -f /Users/sawadakeisuke/workspace/Ykk08OK/docker-compose.yml exec -T php_ykk08ok php artisan tinker --execute="App\Models\User::query()->update(['is_notify_target' => false]); echo 'count=' . App\Models\User::where('is_notify_target', true)->count();"
```

Expected: `count=0`

- [ ] **Step 7: コミット**

```bash
git -C /Users/sawadakeisuke/workspace/Ykk08OK add database/migrations/2026_09_27_000001_add_is_notify_target_to_users_table.php app/Models/User.php
git -C /Users/sawadakeisuke/workspace/Ykk08OK commit -m "$(cat <<'EOF'
users に通知対象フラグ is_notify_target を追加

加盟店登録のLINE通知の宛先を DB だけで管理できるようにする。
既存行は false で入るため、フラグを立てるまで通知は飛ばない。

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: 通知サービス MerchantRegisteredNotifier

**Files:**
- Create: `app/Services/MerchantRegisteredNotifier.php`

**Interfaces:**
- Consumes: Task 1 の `users.is_notify_target`。既存の `App\Services\LineMessageService::sendMessage($userId, $message)`（戻り値は `['status' => 'success'|'error', 'message' => string, ...]` の配列）
- Produces:
  - `MerchantRegisteredNotifier::buildBody(Merchant $merchant): string`
  - `MerchantRegisteredNotifier::notify(Merchant $merchant): void` — 例外を外へ投げない

- [ ] **Step 1: サービスクラスを作成する**

`app/Services/MerchantRegisteredNotifier.php` を新規作成。
既存の `app/Services/InvoiceLineSender.php` と同じ構成（クラスコメント、コンストラクタ注入、`buildBody` を公開）に揃えること。

```php
<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * 加盟店が登録されたことを、通知対象ユーザーのLINEへ知らせる
 *
 * 宛先は users.is_notify_target が立っていて line_id を持つユーザー全員。
 * 通知の失敗で加盟店登録そのものを巻き添えにしてはいけないため、
 * このクラスは例外を外へ投げない。失敗はログにだけ残す。
 */
class MerchantRegisteredNotifier
{
    private $lineMessageService;

    public function __construct(LineMessageService $lineMessageService)
    {
        $this->lineMessageService = $lineMessageService;
    }

    /**
     * 送信する本文を組み立てる
     *
     * LIFF からの登録は status=2（無効）で入るため、有効化を促す文面を必ず入れる。
     *
     * @param Merchant $merchant
     * @return string
     */
    public function buildBody(Merchant $merchant)
    {
        $lines = [];
        $lines[] = '新しい加盟店が登録されました';
        $lines[] = '';
        $lines[] = 'サロン名：' . $merchant->name;
        $lines[] = '加盟店コード：' . $merchant->merchant_code;
        $lines[] = '電話番号：' . $merchant->phone;
        $lines[] = '登録日時：' . optional($merchant->created_at)->format('Y/m/d H:i');
        $lines[] = '';
        $lines[] = '現在このサロンは「無効」です。';
        $lines[] = '管理画面から有効に切り替えてください。';
        $lines[] = route('admin.merchants.edit', $merchant->id);

        return implode("\n", $lines);
    }

    /**
     * 通知対象ユーザー全員へ送信する
     *
     * @param Merchant $merchant
     * @return void
     */
    public function notify(Merchant $merchant)
    {
        try {
            // line_id が無いユーザーは送れないのでクエリの段階で外す
            $targets = User::where('is_notify_target', true)
                ->whereNotNull('line_id')
                ->get();

            if ($targets->isEmpty()) {
                Log::warning('加盟店登録通知: 送信対象のユーザーがいません', [
                    'merchant_id' => $merchant->id,
                ]);
                return;
            }

            $body = $this->buildBody($merchant);

            foreach ($targets as $target) {
                $result = $this->lineMessageService->sendMessage($target->line_id, $body);

                // 1件失敗しても残りの宛先には送り切る
                if (($result['status'] ?? '') !== 'success') {
                    Log::error('加盟店登録通知の送信に失敗', [
                        'merchant_id' => $merchant->id,
                        'user_id' => $target->id,
                        'result' => $result,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('加盟店登録通知でエラー', [
                'merchant_id' => $merchant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

- [ ] **Step 2: 本文が組み立てられることを確認する**

Run:
```bash
docker compose -f /Users/sawadakeisuke/workspace/Ykk08OK/docker-compose.yml exec -T php_ykk08ok php artisan tinker --execute="\$m = App\Models\Merchant::first(); echo app(App\Services\MerchantRegisteredNotifier::class)->buildBody(\$m);"
```

Expected: 下のような本文が表示される。加盟店コードが `GOON-XXXX` 形式、最終行が `http://localhost:8884/admin/merchants/<id>/edit` になっていること。

```
新しい加盟店が登録されました

サロン名：（1件目の加盟店名）
加盟店コード：GOON-XXXX
電話番号：（電話番号）
登録日時：2026/09/27 10:00

現在このサロンは「無効」です。
管理画面から有効に切り替えてください。
http://localhost:8884/admin/merchants/1/edit
```

- [ ] **Step 3: 宛先ゼロのときに警告が出ることを確認する**

Run:
```bash
docker compose -f /Users/sawadakeisuke/workspace/Ykk08OK/docker-compose.yml exec -T php_ykk08ok php artisan tinker --execute="app(App\Services\MerchantRegisteredNotifier::class)->notify(App\Models\Merchant::first()); echo 'done';"
docker compose -f /Users/sawadakeisuke/workspace/Ykk08OK/docker-compose.yml exec -T php_ykk08ok tail -n 3 storage/logs/laravel.log
```

Expected: 例外で落ちずに `done` が出る。ログの末尾に
`加盟店登録通知: 送信対象のユーザーがいません {"merchant_id":1}` が記録されている。

- [ ] **Step 4: 宛先がいるときに送信を試みることを確認する**

ローカルには LINE のアクセストークンが無いため push は失敗する。**失敗しても例外が外に漏れず、error ログが残ること**を確認するのが目的。

Run:
```bash
docker compose -f /Users/sawadakeisuke/workspace/Ykk08OK/docker-compose.yml exec -T php_ykk08ok php artisan tinker --execute="\$u = App\Models\User::first(); \$u->update(['is_notify_target' => true, 'line_id' => 'Udummy0000000000000000000000000000']); app(App\Services\MerchantRegisteredNotifier::class)->notify(App\Models\Merchant::first()); echo 'done';"
docker compose -f /Users/sawadakeisuke/workspace/Ykk08OK/docker-compose.yml exec -T php_ykk08ok tail -n 5 storage/logs/laravel.log
```

Expected: 例外で落ちずに `done` が出る。ログに `加盟店登録通知の送信に失敗` が `merchant_id` と `user_id` 付きで残っている。

- [ ] **Step 5: 確認用に書き換えたユーザーを戻す**

Run:
```bash
docker compose -f /Users/sawadakeisuke/workspace/Ykk08OK/docker-compose.yml exec -T php_ykk08ok php artisan tinker --execute="App\Models\User::query()->update(['is_notify_target' => false]); echo 'count=' . App\Models\User::where('is_notify_target', true)->count();"
```

Expected: `count=0`

（Step 4 で `line_id` を上書きした 1 件目のユーザーは、元が NULL なら戻さなくても通知対象から外れる。元の値を控えていた場合は併せて戻すこと）

- [ ] **Step 6: コミット**

```bash
git -C /Users/sawadakeisuke/workspace/Ykk08OK add app/Services/MerchantRegisteredNotifier.php
git -C /Users/sawadakeisuke/workspace/Ykk08OK commit -m "$(cat <<'EOF'
加盟店登録のLINE通知サービスを追加

is_notify_target が立っていて line_id を持つユーザー全員へ push する。
通知の失敗で加盟店登録を巻き添えにしないよう、例外は外へ投げない。

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: 登録3経路からの呼び出し

**Files:**
- Modify: `app/Http/Controllers/MerchantController.php:98`（`store` のシグネチャ）、`:143` 付近（`Log::info` の直後）
- Modify: `app/Http/Controllers/Admin/MerchantController.php:98`（`store` のシグネチャ）、`:129`（`Merchant::create`）
- Modify: `app/Http/Controllers/Agency/MerchantController.php:46`（`store` のシグネチャ）、`:70`（`Merchant::create`）

**Interfaces:**
- Consumes: Task 2 の `MerchantRegisteredNotifier::notify(Merchant $merchant): void`
- Produces: なし（呼び出しを足すだけ）

注意: 3 つの `store()` はいずれもコンストラクタではなく**メソッドインジェクション**で依存を受け取る形に揃える。LIFF 版が既に `store(LineRichMenuService $lineRichMenuService, Request $request)` という書き方をしており、この作法に合わせる。

- [ ] **Step 1: LIFF の登録処理に足す**

`app/Http/Controllers/MerchantController.php` の `store` のシグネチャに引数を 1 つ足す。

変更前（`:98`）:
```php
    public function store(LineRichMenuService $lineRichMenuService, Request $request)
```

変更後:
```php
    public function store(LineRichMenuService $lineRichMenuService, MerchantRegisteredNotifier $notifier, Request $request)
```

ファイル冒頭の `use` 宣言に 1 行足す（既存の `use App\Services\...` の並びに揃える）:
```php
use App\Services\MerchantRegisteredNotifier;
```

`Log::info(...)` の直後（`:143` の次の行）に通知の呼び出しを足す。

変更前:
```php
            Log::info("Merchant created: {$merchant->id}, Richmenu switched: {$line_id}");

            return response()->json([
```

変更後:
```php
            Log::info("Merchant created: {$merchant->id}, Richmenu switched: {$line_id}");

            $notifier->notify($merchant);

            return response()->json([
```

- [ ] **Step 2: 管理画面の登録処理に足す**

`app/Http/Controllers/Admin/MerchantController.php` の `store` のシグネチャを変える。

変更前（`:98`）:
```php
    public function store(Request $request)
```

変更後:
```php
    public function store(Request $request, MerchantRegisteredNotifier $notifier)
```

ファイル冒頭の `use` 宣言に 1 行足す:
```php
use App\Services\MerchantRegisteredNotifier;
```

`:129` の `Merchant::create($request->all());` は戻り値を捨てているので、受け取ってから通知する。

変更前:
```php
        Merchant::create($request->all());

        return redirect()->route('admin.merchants.index')->with('success', '加盟店を登録しました。');
```

変更後:
```php
        $merchant = Merchant::create($request->all());

        $notifier->notify($merchant);

        return redirect()->route('admin.merchants.index')->with('success', '加盟店を登録しました。');
```

- [ ] **Step 3: 代理店の登録処理に足す**

`app/Http/Controllers/Agency/MerchantController.php` の `store` のシグネチャを変える。

変更前（`:46`）:
```php
    public function store(Request $request)
```

変更後:
```php
    public function store(Request $request, MerchantRegisteredNotifier $notifier)
```

ファイル冒頭の `use` 宣言に 1 行足す:
```php
use App\Services\MerchantRegisteredNotifier;
```

`:70` の `Merchant::create($request->all());` を変える。

変更前:
```php
        Merchant::create($request->all());

        return redirect()->route('agencies.merchants.index')->with('success', '加盟店を登録しました。');
```

変更後:
```php
        $merchant = Merchant::create($request->all());

        $notifier->notify($merchant);

        return redirect()->route('agencies.merchants.index')->with('success', '加盟店を登録しました。');
```

- [ ] **Step 4: 構文エラーが無いことを確認する**

Run:
```bash
docker compose -f /Users/sawadakeisuke/workspace/Ykk08OK/docker-compose.yml exec -T php_ykk08ok php -l app/Http/Controllers/MerchantController.php
docker compose -f /Users/sawadakeisuke/workspace/Ykk08OK/docker-compose.yml exec -T php_ykk08ok php -l app/Http/Controllers/Admin/MerchantController.php
docker compose -f /Users/sawadakeisuke/workspace/Ykk08OK/docker-compose.yml exec -T php_ykk08ok php -l app/Http/Controllers/Agency/MerchantController.php
```

Expected: 3 つとも `No syntax errors detected`

- [ ] **Step 5: 管理画面から実際に登録して通知が呼ばれることを確認する**

ログイン用のクッキーを作る（`_token` の値は毎回変わるので、1 行目の出力を 2 行目に貼ること）。

```bash
curl -s -c /tmp/goon_cookie.txt http://localhost:8884/admin/login | grep -o 'name="_token" value="[^"]*"'
curl -s -b /tmp/goon_cookie.txt -c /tmp/goon_cookie.txt -X POST http://localhost:8884/admin/login -d '_token=<上で取れた値>' -d 'email=admin@example.com' -d 'password=password' -o /dev/null -w '%{http_code}\n'
```

Expected: 2 行目が `302`

ログを空にしてから、管理画面の加盟店登録画面を開いて 1 件登録する（ブラウザで
http://localhost:8884/admin/merchants/create を開いて手で登録してもよい）。登録後:

```bash
docker compose -f /Users/sawadakeisuke/workspace/Ykk08OK/docker-compose.yml exec -T php_ykk08ok tail -n 5 storage/logs/laravel.log
```

Expected: 登録した直後に `加盟店登録通知: 送信対象のユーザーがいません` が新しく 1 件増えている。
（通知対象が 0 件なのでこの警告が出るのが正しい。これが出れば呼び出しが届いている証拠）

- [ ] **Step 6: 登録が通知の失敗に巻き込まれないことを確認する**

Run:
```bash
docker compose -f /Users/sawadakeisuke/workspace/Ykk08OK/docker-compose.yml exec -T php_ykk08ok php artisan tinker --execute="\$u = App\Models\User::first(); \$u->update(['is_notify_target' => true, 'line_id' => 'Udummy0000000000000000000000000000']); echo 'ready';"
```

この状態でもう一度、管理画面から加盟店を 1 件登録する。

Expected: **画面は「加盟店を登録しました。」で正常に完了する**（push は失敗するが登録は成功する）。
`storage/logs/laravel.log` には `加盟店登録通知の送信に失敗` が残る。
`App\Models\Merchant::latest('id')->first()` で登録した加盟店が実在することを確認する。

確認後、フラグを戻す:
```bash
docker compose -f /Users/sawadakeisuke/workspace/Ykk08OK/docker-compose.yml exec -T php_ykk08ok php artisan tinker --execute="App\Models\User::query()->update(['is_notify_target' => false]); echo 'count=' . App\Models\User::where('is_notify_target', true)->count();"
```

Expected: `count=0`

- [ ] **Step 7: コミット**

```bash
git -C /Users/sawadakeisuke/workspace/Ykk08OK add app/Http/Controllers/MerchantController.php app/Http/Controllers/Admin/MerchantController.php app/Http/Controllers/Agency/MerchantController.php
git -C /Users/sawadakeisuke/workspace/Ykk08OK commit -m "$(cat <<'EOF'
加盟店の登録3経路からLINE通知を呼ぶ

LIFF・管理画面・代理店のいずれから登録しても通知が飛ぶようにする。
管理画面と代理店は Merchant::create の戻り値を捨てていたので受け取る形に変更。

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: 管理画面のユーザー一覧にチェックボックスを追加

**Files:**
- Modify: `routes/web.php:143`（`update-richmenu` の次の行）
- Modify: `app/Http/Controllers/Admin/UserController.php`（`updateRichmenu` の後ろにアクションを追加）
- Modify: `resources/views/admin/users/index.blade.php:50-60`（行のマークアップ）、`:76-101`（JS）

**Interfaces:**
- Consumes: Task 1 の `users.is_notify_target`
- Produces: `POST /admin/users/{user}/update-notify-target`（ルート名 `admin.users.update-notify-target`）。リクエストボディは `{"is_notify_target": true|false}`、レスポンスは `{"success": true}`

注意: このルートグループは名前に `admin.` を自動で付ける。既存の `->name('admin.users.update-richmenu')` は二重に付いて実際には `admin.admin.users.update-richmenu` になっている（既存の不具合。ビューが `route()` を使わず URL 直書きのため表面化していない）。**新しいルートは `->name('users.update-notify-target')` と書くこと。**

- [ ] **Step 1: ルートを追加する**

`routes/web.php` の `:143`（`update-richmenu` の行）の直後に 1 行足す。

```php
        Route::post('/users/{user}/update-richmenu', [AdminUserController::class, 'updateRichmenu'])->name('admin.users.update-richmenu');
        Route::post('/users/{user}/update-notify-target', [AdminUserController::class, 'updateNotifyTarget'])->name('users.update-notify-target');
```

- [ ] **Step 2: ルートが登録されたことを確認する**

Run:
```bash
docker compose -f /Users/sawadakeisuke/workspace/Ykk08OK/docker-compose.yml exec -T php_ykk08ok php artisan route:list --name=update-notify-target
```

Expected: `POST admin/users/{user}/update-notify-target admin.users.update-notify-target › Admin\UserController@updateNotifyTarget`

- [ ] **Step 3: コントローラにアクションを追加する**

`app/Http/Controllers/Admin/UserController.php` の `updateRichmenu` メソッドの直後に足す。
既存の `updateRichmenu` と同じく JSON を返す形に揃えること。

```php
    /**
     * システム通知の送信対象フラグを切り替える
     *
     * 一覧のチェックボックスから即時保存されるため、JSON を返す。
     */
    public function updateNotifyTarget(Request $request, User $user)
    {
        $request->validate([
            'is_notify_target' => 'required|boolean',
        ]);

        $isNotifyTarget = $request->boolean('is_notify_target');

        // LINE ID が無いユーザーは通知を受け取れないので、対象にさせない
        if ($isNotifyTarget && !$user->line_id) {
            return response()->json([
                'success' => false,
                'message' => 'このユーザーはLINE IDが未登録のため、通知対象にできません。',
            ], 400);
        }

        $user->update([
            'is_notify_target' => $isNotifyTarget,
        ]);

        return response()->json(['success' => true]);
    }
```

`use App\Models\User;` と `use Illuminate\Http\Request;` が冒頭に無ければ足す（`updateRichmenu` が `Request` と `User` を使っているので、通常は既にある）。

- [ ] **Step 4: ビューにチェックボックスを足す**

`resources/views/admin/users/index.blade.php` のリッチメニューの `<div class="lma-select_box">` ブロック（`:50-60`）の直後に足す。

```blade
                    <div class="lma-select_box">
                        リッチメニュー：
                        <select class="form-control richmenu-select" data-user-id="{{ $user->id }}">
                            @foreach($richmenuOptions as $key => $value)
                            <option value="{{ $key }}" {{ $user->richmenu_id == $key ? 'selected' : '' }}>
                                {{ $key }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="lma-select_box">
                        <label class="notify-target-label">
                            <input type="checkbox" class="notify-target-check" data-user-id="{{ $user->id }}" {{ $user->is_notify_target ? 'checked' : '' }} @if(!$user->line_id) disabled @endif>
                            通知を受け取る
                        </label>
                        @if(!$user->line_id)
                        <span class="notify-target-note">LINE ID未登録</span>
                        @endif
                    </div>
```

- [ ] **Step 5: JS を足す**

同ファイルの `<script>` 内、`.richmenu-select` の `forEach` ブロックの後ろに足す。
既存の fetch と同じ書き方（`BASE_URL` 、`X-CSRF-TOKEN`、`alert` での結果表示）に揃えること。

```javascript
    document.querySelectorAll('.notify-target-check').forEach(check => {
        check.addEventListener('change', function () {
            let userId = this.dataset.userId;
            let isNotifyTarget = this.checked;

            fetch(`${BASE_URL}/admin/users/${userId}/update-notify-target`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ is_notify_target: isNotifyTarget })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(isNotifyTarget ? "通知対象に設定しました！" : "通知対象から外しました！");
                } else {
                    // 保存できていないので、チェックの見た目を元に戻す
                    this.checked = !isNotifyTarget;
                    alert(data.message || "更新に失敗しました。");
                }
            })
            .catch(error => {
                this.checked = !isNotifyTarget;
                console.error('Error:', error);
            });
        });
    });
```

- [ ] **Step 6: 見た目を整えるCSSを足す**

同ファイル末尾の `@endsection` の直前に `<style>` を足す。
**`resources/css/admin.css` は触らないこと**（gitignore 済みで手動 FTP が必要）。

```html
<style>
    .notify-target-label {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        cursor: pointer;
    }
    /* LINE ID が無い行は操作できないことを見た目でも示す */
    .notify-target-label:has(input:disabled) {
        color: #999;
        cursor: not-allowed;
    }
    .notify-target-note {
        margin-left: 6px;
        font-size: 12px;
        color: #999;
    }
</style>
```

- [ ] **Step 7: 画面を描画して確認する**

curl のセッションを作り、ヘッドレス Chrome でレンダリングする（`project_visual_check` の手順）。

```bash
curl -s -c /tmp/goon_cookie.txt http://localhost:8884/admin/login | grep -o 'name="_token" value="[^"]*"'
curl -s -b /tmp/goon_cookie.txt -c /tmp/goon_cookie.txt -X POST http://localhost:8884/admin/login -d '_token=<上で取れた値>' -d 'email=admin@example.com' -d 'password=password' -o /dev/null -w '%{http_code}\n'
curl -s -b /tmp/goon_cookie.txt http://localhost:8884/admin/users -o /tmp/users.html -w '%{http_code}\n'
perl -pe 's{(href|src)="/}{$1="http://localhost:8884/}g' /tmp/users.html > /tmp/users_rw.html
"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless --disable-gpu --no-sandbox --screenshot=/tmp/users_after.png --window-size=1280,900 --hide-scrollbars --virtual-time-budget=4000 file:///tmp/users_rw.html
```

Expected: 各ユーザーの行に「通知を受け取る」チェックボックスが表示される。
LINE ID が空のユーザーの行はチェックボックスが disabled になり、「LINE ID未登録」が灰色で出ている。
Read ツールで `/tmp/users_after.png` を開いて目視すること。

- [ ] **Step 8: 保存されることを確認する**

LINE ID を持つユーザーの id を調べてから、エンドポイントを直接叩く。

まず対象の id と CSRF トークンを控える（トークンは Step 7 で保存した `/tmp/users.html` の meta タグから取る）。

```bash
docker compose -f /Users/sawadakeisuke/workspace/Ykk08OK/docker-compose.yml exec -T php_ykk08ok php artisan tinker --execute="echo App\Models\User::whereNotNull('line_id')->value('id');"
grep -o 'name="csrf-token" content="[^"]*"' /tmp/users.html
```

控えた 2 つの値を埋めて叩く。

```bash
curl -s -b /tmp/goon_cookie.txt -X POST http://localhost:8884/admin/users/<上のid>/update-notify-target -H 'Content-Type: application/json' -H 'X-CSRF-TOKEN: <上のcontentの値>' -d '{"is_notify_target": true}'
docker compose -f /Users/sawadakeisuke/workspace/Ykk08OK/docker-compose.yml exec -T php_ykk08ok php artisan tinker --execute="echo 'count=' . App\Models\User::where('is_notify_target', true)->count();"
```

Expected: curl のレスポンスが `{"success":true}`、その後の count が `1`

- [ ] **Step 9: LINE ID が無いユーザーは弾かれることを確認する**

```bash
docker compose -f /Users/sawadakeisuke/workspace/Ykk08OK/docker-compose.yml exec -T php_ykk08ok php artisan tinker --execute="echo App\Models\User::whereNull('line_id')->value('id');"
```

id が取れた場合、その id に対して Step 8 と同じ curl を叩く。

Expected: HTTP 400 と `{"success":false,"message":"このユーザーはLINE IDが未登録のため、通知対象にできません。"}`

（`line_id` が NULL のユーザーがローカルに 1 件も無ければ、このステップは「該当なし」として飛ばしてよい）

- [ ] **Step 10: 確認用に立てたフラグを戻す**

```bash
docker compose -f /Users/sawadakeisuke/workspace/Ykk08OK/docker-compose.yml exec -T php_ykk08ok php artisan tinker --execute="App\Models\User::query()->update(['is_notify_target' => false]); echo 'count=' . App\Models\User::where('is_notify_target', true)->count();"
```

Expected: `count=0`

- [ ] **Step 11: コミット**

```bash
git -C /Users/sawadakeisuke/workspace/Ykk08OK add routes/web.php app/Http/Controllers/Admin/UserController.php resources/views/admin/users/index.blade.php
git -C /Users/sawadakeisuke/workspace/Ykk08OK commit -m "$(cat <<'EOF'
管理画面のユーザー一覧で通知対象を切り替えられるようにする

リッチメニューのセレクトと同じ即時保存のパターンを踏襲。
LINE ID が未登録のユーザーはチェックできないようにした。

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
EOF
)"
```

---

## デプロイ時の注意

- `main` への push で本番へ自動デプロイされ、`php artisan migrate --force` が走る。Task 1 のマイグレーションはカラム追加のみなので既存データに影響しない
- デプロイでは `npm run build` は走らない。今回 JS/CSS はすべて Blade 内に書くのでビルド不要
- **本番では、通知対象のユーザーにチェックを入れるまで通知は飛ばない。** デプロイ後に管理画面のユーザー一覧で代理店オーナー兼本部オーナーにチェックを入れる作業が必要
- テスト加盟店のオーナーにチェックを入れると、テスト登録でも本番の通知が飛ぶ。チェックを入れるのは人の操作なので運用で防げるが、認識しておくこと
- 通知が飛ばないときは `storage/logs/laravel.log` の `加盟店登録通知:` で始まる行を見る。宛先ゼロなら warning、送信失敗なら error が出ている
