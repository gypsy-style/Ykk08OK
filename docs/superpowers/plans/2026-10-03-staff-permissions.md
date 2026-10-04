# サロンスタッフの権限 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** オーナーがスタッフごとに5つの権限（注文・注文履歴・請求書・登録情報の修正・スタッフ管理）をオン・オフでき、サーバー側でも判定されるようにする。あわせて、本人確認のないスタッフ削除・追加、注文詳細・キャンセル、登録情報の表示・修正の穴を塞ぐ。

**Architecture:** `merchant_members` に権限列を5つ足し、`App\Services\MerchantAccess` に判定を集める。LIFF の API は `Controller::merchantAccessFromToken()` でアクセストークンから権限を引き、画面の出し分けとサーバーの判定の両方に使う。`liff.js` を通る画面（注文一覧・履歴）は、サーバーが返す値・HTML と Blade の中だけで出し分ける。注文詳細・キャンセル・登録情報の修正は署名付き URL、スタッフ追加の QR は HMAC の招待トークンで守る。

**Tech Stack:** Laravel（PHP 8）、Blade、jQuery、LIFF（`@line/liff`）、Vite

**設計書:** `docs/superpowers/specs/2026-10-03-staff-permissions-design.md`

## Global Constraints

- `resources/js/liff.js` は変更しない（変更禁止のファイル）。
- テストコードは書かない。確認はローカル（Docker + `LIFF_MOCK`）の画面操作と tinker で行う。
- `php artisan migrate:fresh` / `migrate:refresh` / `db:wipe` は絶対に実行しない（ローカルDBが消える。シーダーでは戻らない）。使ってよいのは `php artisan migrate` だけ。
- `.progress.json` には触らない（メインセッションが管理する）。
- 既存ファイルのスタイル（インデント・コメントの書き方・命名）に合わせる。修正は最小限・局所的に。
- 権限の列名は次の5つで固定: `can_order`, `can_view_order_history`, `can_view_invoice`, `can_edit_merchant`, `can_manage_staff`
- 初期値: `can_order` と `can_view_order_history` は true、残り3つは false。
- 権限を変えられるのはオーナーだけ。
- 署名付き URL の有効期限は `Carbon::now()->addDay()`（請求書と同じ）。
- 招待トークン: `hash_hmac('sha256', 'staff-invite:' . merchant_id, config('app.key'))`、照合は `hash_equals`。有効期限なし。
- 権限がないときの文言は「〜の権限がありません。サロンオーナーにご確認ください。」で揃える。
- push しない。コミットはタスクごとに行う（main ブランチ上でよい。push はユーザーの確認後にメインセッションが行う）。

## ローカル環境

- Docker: `docker compose -f /Users/sawadakeisuke/workspace/Ykk08OK/docker-compose.yml up -d`。web は http://localhost:8884（`/ykk08ok` プレフィックスなし）。
- artisan: `docker exec php_ykk08ok php artisan ...`
- ローカル `.env`: `APP_ENV=local` / `LIFF_MOCK=true` / `DUMMY_LINE_ID=line_dummy_001`（user 1 = merchant 1 のオーナー）。`dummy_` で始まるアクセストークンは `DUMMY_LINE_ID` のユーザーとして扱われる。
- スタッフとして確認するときは、`.env` の `DUMMY_LINE_ID` を確認用スタッフの `line_id` に一時的に変え、`docker exec php_ykk08ok php artisan config:clear` する。**確認が終わったら必ず `line_dummy_001` に戻す。**

## File Structure

| ファイル | 種別 | 役割 |
|---|---|---|
| `database/migrations/2026_10_03_000001_add_permissions_to_merchant_members_table.php` | 新規 | 権限列5つを追加 |
| `app/Models/MerchantMember.php` | 変更 | 権限列の `$casts` |
| `app/Services/MerchantAccess.php` | 新規 | 権限の判定・招待トークン |
| `app/Http/Controllers/Controller.php` | 変更 | `merchantAccessFromToken()` |
| `app/Http/Controllers/MerchantController.php` | 変更 | マイページ・登録情報の修正・スタッフ一覧・権限変更・スタッフ追加/削除・請求書一覧 |
| `app/Http/Controllers/OrderController.php` | 変更 | 会員ランク API・注文確定・注文履歴・詳細・キャンセル |
| `routes/web.php` | 変更 | 権限変更 API のルート |
| `resources/js/liff_information.js` | 変更 | 権限でボタンを出す |
| `resources/js/liff_member_list.js` | 変更 | 403 のときのメッセージ |
| `resources/js/liff_merchant_add_member.js` | 変更 | 招待トークンを送る |
| `resources/views/merchants/information.blade.php` | 変更 | `EDIT_URL` を削除 |
| `resources/views/merchants/edit.blade.php` | 変更 | 署名付きの更新 URL へ送る |
| `resources/views/merchants/invoice_expired.blade.php` | 変更 | タイトルを差し替え可能に |
| `resources/views/merchants/member_list.blade.php` | 変更 | QR/コピー URL に招待トークン、権限の切り替え、削除にトークン |
| `resources/views/merchants/partials/member_list.blade.php` | 変更 | 権限のチェックボックス、自分の削除ボタンを出さない |
| `resources/views/merchants/add_member.blade.php` | 変更 | エラー表示欄 |
| `resources/views/order/list.blade.php` | 変更 | 注文の権限がないときのメッセージ |
| `resources/views/order/partials/history.blade.php` | 変更 | 詳細リンクを署名付きに |
| `resources/views/order/partials/no_permission.blade.php` | 新規 | 権限がないときの HTML 部品 |
| `resources/views/order/detail.blade.php` | 変更 | 署名付きのキャンセル URL へ送る |

---

### Task 1: 権限の列と判定の土台

**Files:**
- Create: `database/migrations/2026_10_03_000001_add_permissions_to_merchant_members_table.php`
- Create: `app/Services/MerchantAccess.php`
- Modify: `app/Models/MerchantMember.php`
- Modify: `app/Http/Controllers/Controller.php`

**Interfaces:**
- Produces:
  - `App\Services\MerchantAccess::PERMISSIONS`（5つの列名の配列）
  - `App\Services\MerchantAccess::LABELS`（列名 => 表示名）
  - `MerchantAccess::forUser(?User $user): MerchantAccess`
  - プロパティ `$access->user`（`User|null`）, `$access->merchant`（`Merchant|null`）, `$access->member`（`MerchantMember|null`）, `$access->isOwner`（bool）
  - `$access->can(string $permission): bool`
  - `$access->permissions(): array`（列名 => bool）
  - `MerchantAccess::inviteToken(int $merchantId): string`
  - `MerchantAccess::isValidInviteToken($merchantId, $token): bool`
  - `Controller::merchantAccessFromToken($accessToken): ?MerchantAccess`（トークン不正・ユーザー未登録なら null）

- [ ] **Step 1: マイグレーションを作る**

`database/migrations/2026_10_03_000001_add_permissions_to_merchant_members_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * スタッフごとの権限
     *
     * 初期値で今の動き（注文と注文履歴だけできる）を保つ。
     * 既存のスタッフにも、これから追加するスタッフにも同じ値が入る。
     */
    public function up(): void
    {
        Schema::table('merchant_members', function (Blueprint $table) {
            $table->boolean('can_order')->default(true);
            $table->boolean('can_view_order_history')->default(true);
            $table->boolean('can_view_invoice')->default(false);
            $table->boolean('can_edit_merchant')->default(false);
            $table->boolean('can_manage_staff')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('merchant_members', function (Blueprint $table) {
            $table->dropColumn([
                'can_order',
                'can_view_order_history',
                'can_view_invoice',
                'can_edit_merchant',
                'can_manage_staff',
            ]);
        });
    }
};
```

- [ ] **Step 2: モデルに casts を足す**

`app/Models/MerchantMember.php` の `$fillable` の下に追加:

```php
    protected $casts = [
        'can_order' => 'boolean',
        'can_view_order_history' => 'boolean',
        'can_view_invoice' => 'boolean',
        'can_edit_merchant' => 'boolean',
        'can_manage_staff' => 'boolean',
    ];
```

`$fillable` には足さない（権限は `storeMember` の `create()` では渡さず、列の初期値に任せる。変更は権限変更 API でプロパティに直接代入する）。

- [ ] **Step 3: MerchantAccess を作る**

`app/Services/MerchantAccess.php`:

```php
<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\MerchantMember;
use App\Models\User;

/**
 * サロンでの権限を判定する
 *
 * オーナー（merchants.user_id が本人）は全権限を持つ。
 * スタッフ（merchant_members.user_id が本人）は、その行の権限列の値に従う。
 * どちらでもない人は何もできない。
 *
 * 画面のボタンを出し分けるだけでなく、LIFF の裏の API でも必ずここで判定する。
 * API は URL さえ分かれば誰でも呼べるため。
 */
class MerchantAccess
{
    const PERMISSIONS = [
        'can_order',
        'can_view_order_history',
        'can_view_invoice',
        'can_edit_merchant',
        'can_manage_staff',
    ];

    const LABELS = [
        'can_order' => '注文する',
        'can_view_order_history' => '注文履歴を見る',
        'can_view_invoice' => '請求書確認',
        'can_edit_merchant' => '登録情報の修正',
        'can_manage_staff' => 'スタッフ管理',
    ];

    /** @var User|null */
    public $user;

    /** @var Merchant|null */
    public $merchant;

    /** @var MerchantMember|null */
    public $member;

    /** @var bool */
    public $isOwner = false;

    /**
     * @param User|null $user
     * @return self
     */
    public static function forUser(?User $user)
    {
        $access = new self();
        $access->user = $user;

        if (!$user) {
            return $access;
        }

        $merchant = Merchant::where('user_id', $user->id)->first();
        if ($merchant) {
            $access->merchant = $merchant;
            $access->isOwner = true;
            return $access;
        }

        // 削除済みサロンのスタッフ行が残っていても所属扱いにしない
        $member = MerchantMember::where('user_id', $user->id)->whereHas('merchant')->first();
        if ($member) {
            $access->member = $member;
            $access->merchant = $member->merchant;
        }

        return $access;
    }

    /**
     * @param string $permission PERMISSIONS のどれか
     * @return bool
     */
    public function can($permission)
    {
        if (!in_array($permission, self::PERMISSIONS, true) || !$this->merchant) {
            return false;
        }

        if ($this->isOwner) {
            return true;
        }

        return (bool) $this->member->{$permission};
    }

    /**
     * @return array 列名 => bool
     */
    public function permissions()
    {
        $result = [];
        foreach (self::PERMISSIONS as $permission) {
            $result[$permission] = $this->can($permission);
        }
        return $result;
    }

    /**
     * スタッフ追加用 QR に入れる招待トークン
     *
     * LIFF を通すとクエリが liff.state に包まれ、Laravel の署名付き URL を
     * そのまま検証できないため、merchant_id に対する HMAC を使う。
     * 紙に印刷して使うこともあるので有効期限は付けない。
     *
     * @param int $merchantId
     * @return string
     */
    public static function inviteToken($merchantId)
    {
        return hash_hmac('sha256', 'staff-invite:' . (int) $merchantId, config('app.key'));
    }

    /**
     * @param mixed $merchantId
     * @param mixed $token
     * @return bool
     */
    public static function isValidInviteToken($merchantId, $token)
    {
        if (!is_string($token) || $token === '' || !is_numeric($merchantId)) {
            return false;
        }

        return hash_equals(self::inviteToken((int) $merchantId), $token);
    }
}
```

- [ ] **Step 4: Controller にトークンから権限を引くメソッドを足す**

`app/Http/Controllers/Controller.php` の `use` に追加:

```php
use App\Models\User;
use App\Services\MerchantAccess;
```

`getLineProfile()` の直後に追加:

```php
    /**
     * LIFF のアクセストークンから、ユーザーとサロンでの権限を引く
     *
     * @param mixed $accessToken
     * @return MerchantAccess|null トークンが不正、またはユーザー未登録なら null
     */
    protected function merchantAccessFromToken($accessToken): ?MerchantAccess
    {
        if (!is_string($accessToken) || $accessToken === '') {
            return null;
        }

        $profile = $this->getLineProfile($accessToken);
        if (!$profile) {
            return null;
        }

        $user = User::where('line_id', $profile['line_id'])->first();
        if (!$user) {
            return null;
        }

        return MerchantAccess::forUser($user);
    }
```

- [ ] **Step 5: マイグレーションを流して確認する**

Run: `docker exec php_ykk08ok php artisan migrate`
Expected: `2026_10_03_000001_add_permissions_to_merchant_members_table` が DONE。

Run:
```bash
docker exec php_ykk08ok php artisan tinker --execute="\$a = App\Services\MerchantAccess::forUser(App\Models\User::where('line_id','line_dummy_001')->first()); var_dump(\$a->isOwner, \$a->merchant->id, \$a->permissions()); \$m = App\Models\MerchantMember::whereHas('merchant')->first(); if (\$m) { \$s = App\Services\MerchantAccess::forUser(\$m->user); var_dump(\$s->isOwner, \$s->permissions()); } var_dump(App\Services\MerchantAccess::isValidInviteToken(1, App\Services\MerchantAccess::inviteToken(1)), App\Services\MerchantAccess::isValidInviteToken(2, App\Services\MerchantAccess::inviteToken(1)));"
```
Expected: オーナーは `isOwner=true`・5つすべて true。スタッフ（いれば）は `isOwner=false`・`can_order`/`can_view_order_history` だけ true。招待トークンは `true` と `false`。

スタッフ行が1件もない場合は、確認用に1件作る（あとの Task でも使う）:
```bash
docker exec php_ykk08ok php artisan tinker --execute="\$u = App\Models\User::create(['name'=>'確認用スタッフ','email'=>'line_staff_check@example.com','line_id'=>'line_staff_check','richmenu_id'=>'RICHMENU_ID_4']); App\Models\MerchantMember::create(['merchant_id'=>1,'user_id'=>\$u->id,'line_id'=>'line_staff_check']); echo \$u->id;"
```

- [ ] **Step 6: Commit**

```bash
git -C /Users/sawadakeisuke/workspace/Ykk08OK add database/migrations/2026_10_03_000001_add_permissions_to_merchant_members_table.php app/Services/MerchantAccess.php app/Models/MerchantMember.php app/Http/Controllers/Controller.php
git -C /Users/sawadakeisuke/workspace/Ykk08OK commit -m "スタッフの権限列と判定の仕組みを追加"
```

---

### Task 2: マイページと登録情報の修正

**Files:**
- Modify: `app/Http/Controllers/MerchantController.php`（`edit` / `update` / `getMerchantInformation` / `getInvoiceList`）
- Modify: `resources/js/liff_information.js:84-113`
- Modify: `resources/views/merchants/information.blade.php:37`
- Modify: `resources/views/merchants/edit.blade.php:59`
- Modify: `resources/views/merchants/invoice_expired.blade.php:6`

**Interfaces:**
- Consumes: `Controller::merchantAccessFromToken()`, `MerchantAccess::can()`, `permissions()`, `isOwner`, `merchant`
- Produces: `get-merchant-information` のレスポンスに `is_owner`（bool）・`permissions`（列名 => bool）・`edit_url`（string|null）を追加。`merchants.invoice_expired` が `$title` を受け取れる。

- [ ] **Step 1: 期限切れ画面のタイトルを差し替え可能にする**

`resources/views/merchants/invoice_expired.blade.php:6`:

```blade
<title>{{ $title ?? '請求書' }}</title>
```

- [ ] **Step 2: getMerchantInformation を権限ベースにする**

`app/Http/Controllers/MerchantController.php` の `use` に追加:

```php
use App\Services\MerchantAccess;
```

`getMerchantInformation()` 全体を置き換える:

```php
    public function getMerchantInformation(Request $request)
    {
        $access = $this->merchantAccessFromToken($request->input('access_token'));
        if (!$access) {
            return response()->json(['error' => 'User not found or invalid token'], 404);
        }

        $merchant = $access->merchant;
        if (!$merchant) {
            return response()->json(['error' => '対応する店舗が見つかりません'], 404);
        }

        // 登録情報の修正画面は本人確認のため署名付き URL で開かせる
        $editUrl = null;
        if ($access->can('can_edit_merchant')) {
            $editUrl = URL::temporarySignedRoute(
                'merchants.edit',
                Carbon::now()->addDay(),
                ['id' => $merchant->id]
            );
        }

        return response()->json([
            'user_id' => $access->user->id,
            'merchant_user_id' => $merchant->user_id,
            'merchant_id' => $merchant->id,
            'merchant_code' => $merchant->merchant_code,
            'name' => $merchant->name,
            'status' => $merchant->status,
            'postal_code' => $merchant->postal_code1 . '-' . $merchant->postal_code2,
            'address' => $merchant->address,
            'phone' => $merchant->phone,
            'bank_account_name' => $merchant->bank_account_name,
            'agency_name' => optional($merchant->agency)->name,
            'has_invoice' => $access->can('can_view_invoice') && $this->invoiceService->hasInvoice($merchant),
            'is_owner' => $access->isOwner,
            'permissions' => $access->permissions(),
            'edit_url' => $editUrl,
        ]);
    }
```

- [ ] **Step 3: edit / update を署名付き URL 必須にする**

`edit()` を置き換える:

```php
    public function edit(Request $request, $id)
    {
        // 本人確認のため、マイページが権限のある人にだけ発行する署名付き URL で開かせる
        if (!$request->hasValidSignature()) {
            return response()->view('merchants.invoice_expired', [
                'title' => '登録情報の修正',
                'message' => 'このリンクの有効期限が切れています。マイページからもう一度開いてください。',
            ], 403);
        }

        $merchant = Merchant::findOrFail($id); // IDで検索、見つからなければ404

        $updateUrl = URL::temporarySignedRoute(
            'merchants.update',
            Carbon::now()->addDay(),
            ['id' => $merchant->id]
        );

        return view('merchants.edit', compact('merchant', 'updateUrl'));
    }
```

`update()` の `try {` の直前に追加:

```php
        if (!$request->hasValidSignature()) {
            return response()->json([
                'success' => false,
                'error' => 'このリンクの有効期限が切れています。マイページからもう一度開いてください。',
            ], 403);
        }
```

`resources/views/merchants/edit.blade.php:59` の fetch 先を置き換える（`{{ }}` だと `&` が `&amp;` になり署名が壊れるため `@json` を使う）:

```blade
            fetch(@json($updateUrl), {
```

- [ ] **Step 4: 請求書一覧 API を権限ベースにする**

`getInvoiceList()` の先頭から `$merchant` を取るところまで（`$accessToken = ...` 〜 `if (!$merchant) {...}`）を置き換える:

```php
        $access = $this->merchantAccessFromToken($request->input('access_token'));
        if (!$access) {
            return response()->json(['error' => 'User not found or invalid token'], 404);
        }

        $merchant = $access->merchant;
        if (!$merchant || !$access->can('can_view_invoice')) {
            return response()->json(['error' => '請求書を見る権限がありません。サロンオーナーにご確認ください。'], 403);
        }
```

以降（`monthlyBreakdown` 以下）はそのまま。

- [ ] **Step 5: マイページの JS を権限ベースにする**

`resources/views/merchants/information.blade.php:37` の `window.EDIT_URL = ...` の行を削除する。

`resources/js/liff_information.js` の `updateMerchantInformation()` のうち、`// 編集画面のURLを生成` から関数の終わりまでを置き換える:

```js
    // 権限に応じてボタンを出す（同じ判定をサーバー側の API でも行っている）
    const permissions = data.permissions || {};
    if (permissions.can_edit_merchant && data.edit_url) {
        $('#edit_link a').attr('href', data.edit_url);
        document.getElementById('edit_link').style.display = 'block';
    }
    if (permissions.can_manage_staff) {
        document.querySelector('.lmf-btn_box.member_list').style.display = 'block';
    }
    // 「請求書」ボタンは確定済みの請求書がある場合のみ表示
    if (permissions.can_view_invoice && data.has_invoice) {
        document.querySelector('.lmf-btn_box.invoice_list').style.display = 'block';
    }
}
```

- [ ] **Step 6: 確認する**

Run: `npm --prefix /Users/sawadakeisuke/workspace/Ykk08OK run build`
Expected: エラーなく終わる。

ブラウザで http://localhost:8884/merchants/information を開く（オーナー = `line_dummy_001`）。
Expected: 「登録情報を修正する」「登録スタッフ一覧」が出る。請求書は確定済みがあれば出る。「登録情報を修正する」を押すと編集画面が開き、保存できる（保存後のマイページ遷移は本番 LIFF の URL なのでローカルでは外へ飛んでよい）。

http://localhost:8884/merchants/edit/1 を署名なしで直接開く。
Expected: 「このリンクの有効期限が切れています。」が出る（403）。

`.env` の `DUMMY_LINE_ID` を `line_staff_check` に変えて `config:clear` し、マイページを開く。
Expected: 3つのボタンがどれも出ない。tinker で `App\Models\MerchantMember::where('line_id','line_staff_check')->update(['can_edit_merchant'=>true,'can_view_invoice'=>true])` してから開き直すと、「登録情報を修正する」（と、確定済み請求書があれば「請求書」）が出る。確認後、2列を false に戻し、`DUMMY_LINE_ID` を `line_dummy_001` に戻して `config:clear`。

- [ ] **Step 7: Commit**

```bash
git -C /Users/sawadakeisuke/workspace/Ykk08OK add app/Http/Controllers/MerchantController.php resources/js/liff_information.js resources/views/merchants/information.blade.php resources/views/merchants/edit.blade.php resources/views/merchants/invoice_expired.blade.php
git -C /Users/sawadakeisuke/workspace/Ykk08OK commit -m "マイページのボタンと登録情報の修正を権限で判定する"
```

---

### Task 3: スタッフ一覧・権限の切り替え・スタッフの追加と削除

**Files:**
- Modify: `app/Http/Controllers/MerchantController.php`（`add_member` / `destroy_member` / `storeMember` / `getMemberList` / `renderMemberListHtml` / 新規 `updateMemberPermission`）
- Modify: `routes/web.php:73` 付近
- Modify: `resources/views/merchants/partials/member_list.blade.php`
- Modify: `resources/views/merchants/member_list.blade.php`
- Modify: `resources/js/liff_member_list.js`
- Modify: `resources/views/merchants/add_member.blade.php`
- Modify: `resources/js/liff_merchant_add_member.js`

**Interfaces:**
- Consumes: `Controller::merchantAccessFromToken()`, `MerchantAccess::forUser()`, `can()`, `isOwner`, `merchant`, `user`, `MerchantAccess::PERMISSIONS`, `LABELS`, `inviteToken()`, `isValidInviteToken()`
- Produces:
  - `POST /api/merchant/member_permission`（入力 `access_token`, `member_user_id`, `permission`, `value`）→ `{success: true}` / 403 / 404 / 422
  - `POST /api/merchant/member_list` のレスポンス: `{html, merchant_id}`（招待トークンは HTML 内の `#invite_token`）。権限がなければ 403。
  - `DELETE /merchants/member/{id}` は `access_token` 必須
  - `POST /merchants/store_member` は `invite_token` 必須

- [ ] **Step 1: スタッフ一覧 API を権限ベースにする**

`getMemberList()` と `renderMemberListHtml()` を置き換える:

```php
    public function getMemberList(Request $request)
    {
        $access = $this->merchantAccessFromToken($request->input('access_token'));
        if (!$access) {
            return response()->json(['error' => 'User not found or invalid token'], 404);
        }

        $merchant = $access->merchant;
        if (!$merchant || !$access->can('can_manage_staff')) {
            return response()->json(['error' => 'スタッフ一覧を見る権限がありません。サロンオーナーにご確認ください。'], 403);
        }

        // 開いた人がスタッフの場合もあるので、オーナーは店舗から引く
        $owner = User::find($merchant->user_id);
        $members = MerchantMember::where('merchant_id', $merchant->id)->with('user')->get();

        $html = $this->renderMemberListHtml($owner, $members, $merchant->id, $access);

        return response()->json(['html' => $html, 'merchant_id' => $merchant->id]);
    }

    public function renderMemberListHtml($owner, $members, $merchant_id, MerchantAccess $access)
    {
        return view('merchants.partials.member_list', [
            'owner' => $owner,
            'members' => $members,
            'merchant_id' => $merchant_id,
            'viewerUserId' => $access->user->id,
            // 権限を変えられるのはオーナーだけ（スタッフ同士で権限を広げ合えないように）
            'canEditPermissions' => $access->isOwner,
            'inviteToken' => MerchantAccess::inviteToken($merchant_id),
        ])->render();
    }
```

- [ ] **Step 2: スタッフ一覧の部品に権限のチェックボックスを足す**

`resources/views/merchants/partials/member_list.blade.php` 全体を置き換える:

```blade
<!-- オーナー -->
 <div class="lmf-staff_block lmf-white_block">
    <dl class="lmf-info_list">
        <dt>名前</dt>
        <dd class="name">{{ optional($owner)->name }}</dd>
        <dt>LINE ID</dt>
        <dd class="id">{{ optional($owner)->line_id }}</dd>
    </dl>
</div>
<!-- 登録スタッフ -->
@if ($members->isNotEmpty())
    @foreach ($members as $member)
        <div class="lmf-staff_block lmf-white_block">
            <dl class="lmf-info_list">
                <dt>名前</dt>
                <dd class="name">{{ $member->user->name }}</dd>
                <dt>LINE ID</dt>
                <dd class="id">{{ $member->user->line_id }}</dd>
            </dl>
            {{-- 権限はオーナーだけが変えられる。スタッフ管理の権限を持つスタッフには見るだけで出す --}}
            <div style="margin: 10px 0; font-size: 14px;">
                <p style="margin: 0 0 6px; font-weight: bold;">権限</p>
                @foreach (\App\Services\MerchantAccess::LABELS as $column => $label)
                    <label style="display: block; margin: 4px 0;">
                        <input type="checkbox" class="member-permission" data-user_id="{{ $member->user_id }}" data-permission="{{ $column }}" {{ $member->{$column} ? 'checked' : '' }} {{ $canEditPermissions ? '' : 'disabled' }}>
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            {{-- 自分自身は削除できない --}}
            @if ((int) $member->user_id !== (int) $viewerUserId)
            <p class="lmf-btn_box btn_pk btn_min">
                <button type="button" data-href="#modal_delete" class="modal_open modal_delete" data-user_id="{{ $member->user->id }}" data-delete_staff_name="{{ $member->user->name }}">削除する</button>
            </p>
            @endif
        </div>
    @endforeach
@else
    <p class="lmf-no-staff">スタッフは登録されていません</p>
@endif
<p class="lmf-btn_box"><button type="button" data-href="#modal_add" class="modal_open" data-merchant_id="">スタッフを追加する</button></p>
<input type="hidden" name="merchant_id" id="merchant_id" value="{{ $merchant_id }}">
<input type="hidden" name="invite_token" id="invite_token" value="{{ $inviteToken }}">
```

- [ ] **Step 3: 権限変更 API を作る**

`MerchantController` の `getMemberList()` の前に追加:

```php
    /**
     * スタッフの権限を1つ切り替える（オーナーだけ）
     */
    public function updateMemberPermission(Request $request)
    {
        $access = $this->merchantAccessFromToken($request->input('access_token'));
        if (!$access || !$access->merchant || !$access->isOwner) {
            return response()->json(['error' => '権限を変更できるのはサロンオーナーだけです。'], 403);
        }

        $permission = $request->input('permission');
        if (!in_array($permission, MerchantAccess::PERMISSIONS, true)) {
            return response()->json(['error' => '変更できない項目です。'], 422);
        }

        // 別のサロンのスタッフは変えられないよう、自分のサロンに絞って探す
        $member = MerchantMember::where('user_id', $request->input('member_user_id'))
            ->where('merchant_id', $access->merchant->id)
            ->first();
        if (!$member) {
            return response()->json(['error' => 'スタッフが見つかりません。'], 404);
        }

        $member->{$permission} = $request->boolean('value');
        $member->save();

        return response()->json(['success' => true]);
    }
```

`routes/web.php` の `Route::post('/api/merchant/member_list', ...)` の次の行に追加:

```php
Route::post('/api/merchant/member_permission', [UserMerchantController::class, 'updateMemberPermission']);
```

- [ ] **Step 4: スタッフ削除に本人確認を入れる**

`destroy_member()` 全体を置き換える:

```php
    public function destroy_member(LineRichMenuService $lineRichMenuService, Request $request, $id)
    {
        $access = $this->merchantAccessFromToken($request->input('access_token'));
        if (!$access || !$access->can('can_manage_staff')) {
            return response()->json(['error' => 'スタッフを削除する権限がありません。サロンオーナーにご確認ください。'], 403);
        }

        if ((int) $id === (int) $access->user->id) {
            return response()->json(['error' => '自分自身は削除できません。'], 422);
        }

        // 別のサロンのスタッフは消せないよう、自分のサロンに絞って探す
        $merchantMember = MerchantMember::where('user_id', $id)
            ->where('merchant_id', $access->merchant->id)
            ->first();
        if (!$merchantMember) {
            return response()->json(['error' => 'スタッフが見つかりません。'], 404);
        }

        $richmenu_2 = \App\Services\RichMenuSlots::id('RICHMENU_ID_2');
        if ($merchantMember->line_id) {
            $lineRichMenuService->switchRichMenu($merchantMember->line_id, $richmenu_2);
            User::where('id', $merchantMember->user_id)->update(['richmenu_id' => 'RICHMENU_ID_2']);
        }
        $merchantMember->delete();

        return response()->json(['success' => true, 'message' => 'Member deleted successfully'], 200);
    }
```

- [ ] **Step 5: スタッフ追加を招待トークン必須にする**

`add_member()` 全体を置き換える:

```php
    public function add_member(Request $request)
    {
        $merchant_id = $request->query('merchant_id');
        $invite_token = $request->query('invite_token');
        if (!$merchant_id) {
            $liffState = $request->query('liff_state');
            $decodedState = urldecode($liffState);
            $cleanState = ltrim($decodedState, '?'); // 先頭の `?` を削除
            parse_str($cleanState, $params);
            $merchant_id = $params['merchant_id'] ?? null;
            $invite_token = $params['invite_token'] ?? null;
        }

        // 正しい QR から来た人だけ受け付ける（merchant_id の数字を変えて別のサロンに入れないように）
        if (!MerchantAccess::isValidInviteToken($merchant_id, $invite_token)) {
            return response()->view('merchants.invoice_expired', [
                'title' => 'スタッフ追加',
                'message' => 'このスタッフ追加用のURLは無効です。サロンオーナーに新しいQRコードを出してもらってください。',
            ], 403);
        }

        $merchant = Merchant::find($merchant_id);

        if (!$merchant) {
            abort(404, 'Merchant not found');
        }
        return view('merchants.add_member', compact('merchant'));
    }
```

`storeMember()` の `$merchant_id = $request->input('merchant_id');` の直後に追加:

```php
        if (!MerchantAccess::isValidInviteToken($merchant_id, $request->input('invite_token'))) {
            return response()->json([
                'error' => 'invalid invite token',
                'message' => 'このスタッフ追加用のURLは無効です。サロンオーナーに新しいQRコードを出してもらってください。',
            ], 403);
        }

        if (!Merchant::find($merchant_id)) {
            return response()->json(['error' => 'Merchant not found', 'message' => 'サロンが見つかりません。'], 404);
        }
```

同じメソッドの `// データベースに保存` の直前に追加:

```php
        // すでにどこかのサロンのオーナーかスタッフである人は追加しない（二重所属を防ぐ）
        if (MerchantAccess::forUser($user)->merchant) {
            return response()->json([
                'error' => 'already belongs',
                'message' => 'すでにサロンに登録されています。',
            ], 409);
        }
```

- [ ] **Step 6: 追加画面の JS で招待トークンを送る**

`resources/views/merchants/add_member.blade.php` の `</form>` の直後に追加（JS が参照しているが今は無い要素）:

```blade
                <p id="message" style="display: none;"></p>
```

`resources/js/liff_merchant_add_member.js` を次のように変える。

`let merchantId = urlParams.get("merchant_id");` の次の行に追加:

```js
        let inviteToken = urlParams.get("invite_token");
```

`liff.state` から取り出す if の中（`if (stateParams.has("merchant_id")) {...}` の直後）に追加:

```js
            if (stateParams.has("invite_token")) {
                inviteToken = stateParams.get("invite_token");
            }
```

ログイン時の `redirectUri` を置き換える:

```js
            let redirectUri = `${window.location.origin}${window.location.pathname}?merchant_id=${merchantId}&invite_token=${encodeURIComponent(inviteToken || '')}`;
```

`payload` を置き換える:

```js
            const payload = {
                merchant_id: merchantId,
                invite_token: inviteToken,
                access_token: accessToken
            };
```

- [ ] **Step 7: スタッフ一覧画面の JS を変える**

`resources/views/merchants/member_list.blade.php` の `generateQRCode()` の `const url = ...` を置き換える:

```js
        const inviteToken = $('#invite_token').val() || '';
        const url = `https://liff.line.me/${liffIdAddMember}?merchant_id=${merchantId}&invite_token=${encodeURIComponent(inviteToken)}`;
```

`copyToClipboard()` の `const url = ...` を置き換える:

```js
        const inviteToken = document.getElementById("invite_token").value;
        const url = `https://liff.line.me/${liffIdAddMember}?merchant_id=${merchantId}&invite_token=${encodeURIComponent(inviteToken)}`;
```

削除の `$.ajax({...})` を置き換える（本人確認のためアクセストークンを送る。DELETE の本文は環境によって読まれないことがあるので `_method` で送る）:

```js
            $.ajax({
                type: "POST",
                url: `{{ route('merchant.member.destroy', ':id') }}`.replace(':id', deleteUserID),
                dataType: "json",
                data: {
                    _method: "DELETE",
                    access_token: $('#access_token').val(),
                },
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"), // CSRF対策
                },
                success: function(response) {
                    alert(response.message);
                    location.reload();
                },
                error: function(xhr, status, error) {
                    alert("削除に失敗しました: " + (xhr.responseJSON ? xhr.responseJSON.error : error));
                }
            });
```

同じ `$(function() { ... })` の中（削除ボタンのハンドラの後）に、権限の切り替えを追加:

```js
        // 権限の切り替え（チェックを変えたらその場で保存。失敗したら元に戻す）
        $(document).on('change', '.member-permission', function() {
            const $box = $(this);
            const checked = $box.prop('checked');
            $box.prop('disabled', true);

            $.ajax({
                type: "POST",
                url: "{{ url('/api/merchant/member_permission') }}",
                dataType: "json",
                data: {
                    access_token: $('#access_token').val(),
                    member_user_id: $box.data('user_id'),
                    permission: $box.data('permission'),
                    value: checked ? 1 : 0,
                },
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
                },
                success: function() {
                    const $msg = $('<span style="color: #06c755; margin-left: 6px; font-size: 12px;">保存しました</span>');
                    $box.closest('label').append($msg);
                    setTimeout(function() { $msg.remove(); }, 1500);
                },
                error: function(xhr) {
                    $box.prop('checked', !checked);
                    alert(xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : '保存に失敗しました');
                },
                complete: function() {
                    $box.prop('disabled', false);
                }
            });
        });
```

`resources/js/liff_member_list.js` の `if (response.ok) { ... } else { throw ... }` の `else` を置き換える:

```js
        } else if (response.status === 403) {
            document.getElementById('staff-list').innerHTML = '<p class="lmf-no-staff">スタッフ一覧を見る権限がありません。サロンオーナーにご確認ください。</p>';
        } else {
            throw new Error('Failed to send order data.');
        }
```

- [ ] **Step 8: 確認する**

Run: `npm --prefix /Users/sawadakeisuke/workspace/Ykk08OK run build`
Expected: エラーなく終わる。

スタッフ一覧は LIFF_MOCK に対応していないので、API は tinker で確認する（CSRF を通さず直接コントローラーを呼ぶ）:

```bash
docker exec php_ykk08ok php artisan tinker --execute="\$c = app(App\Http\Controllers\MerchantController::class); \$r = Illuminate\Http\Request::create('/api/merchant/member_permission','POST',['access_token'=>'dummy_x','member_user_id'=>App\Models\User::where('line_id','line_staff_check')->value('id'),'permission'=>'can_view_invoice','value'=>1]); echo \$c->updateMemberPermission(\$r)->getContent(), PHP_EOL; var_dump(App\Models\MerchantMember::where('line_id','line_staff_check')->value('can_view_invoice')); \$r2 = Illuminate\Http\Request::create('/x','POST',['access_token'=>'dummy_x','member_user_id'=>1,'permission'=>'is_admin','value'=>1]); echo \$c->updateMemberPermission(\$r2)->getContent(), PHP_EOL; \$r3 = Illuminate\Http\Request::create('/x','POST',['access_token'=>'dummy_x']); echo substr(\$c->getMemberList(\$r3)->getContent(), 0, 200);"
```
Expected: `{"success":true}`、`bool(true)`、`変更できない項目です` を含む JSON、スタッフ一覧の HTML の先頭。最後に `App\Models\MerchantMember::where('line_id','line_staff_check')->update(['can_view_invoice'=>false])` で戻す。

`.env` の `DUMMY_LINE_ID` を `line_staff_check` にして `config:clear` し、同じ tinker で:
- `updateMemberPermission` → `権限を変更できるのはサロンオーナーだけです`（403）
- `getMemberList` → `スタッフ一覧を見る権限がありません`（403）
- `destroy_member(app(App\Services\LineRichMenuService::class), Request::create('/x','POST',['access_token'=>'dummy_x']), 1)` → 403
- `can_manage_staff` を true にしてから `getMemberList` → HTML が返り、自分の行に「削除する」が無い・チェックボックスが `disabled`。`destroy_member(..., 自分のuser_id)` → `自分自身は削除できません`（422）

確認後、`can_manage_staff` を false に戻し、`DUMMY_LINE_ID` を `line_dummy_001` に戻して `config:clear`。

招待トークン:
- http://localhost:8884/merchants/add_member?merchant_id=1 → 「このスタッフ追加用のURLは無効です」
- `docker exec php_ykk08ok php artisan tinker --execute="echo App\Services\MerchantAccess::inviteToken(1);"` で出たトークンを付けて http://localhost:8884/merchants/add_member?merchant_id=1&invite_token=<トークン> → サロン名が出る

- [ ] **Step 9: Commit**

```bash
git -C /Users/sawadakeisuke/workspace/Ykk08OK add app/Http/Controllers/MerchantController.php routes/web.php resources/views/merchants/partials/member_list.blade.php resources/views/merchants/member_list.blade.php resources/js/liff_member_list.js resources/views/merchants/add_member.blade.php resources/js/liff_merchant_add_member.js
git -C /Users/sawadakeisuke/workspace/Ykk08OK commit -m "スタッフ一覧で権限を切り替えられるようにし、スタッフの追加・削除に本人確認を入れる"
```

---

### Task 4: 注文・注文履歴・詳細・キャンセル

**Files:**
- Modify: `app/Http/Controllers/OrderController.php`（`getMemberRank` / `register` / `store` / `cancel` / `detail` / `getOrderHistory`）
- Create: `resources/views/order/partials/no_permission.blade.php`
- Modify: `resources/views/order/list.blade.php`
- Modify: `resources/views/order/partials/history.blade.php:47`
- Modify: `resources/views/order/detail.blade.php:105`

**Interfaces:**
- Consumes: `Controller::merchantAccessFromToken()`, `MerchantAccess::forUser()`, `can()`, `merchant`
- Produces: `api/merchant/member_rank` のレスポンスに `can_order`（bool）を追加。`order.detail` / `order.cancel` は署名付き URL 必須。

`resources/js/liff.js` は変更しない。

- [ ] **Step 1: use を足す**

`app/Http/Controllers/OrderController.php` の `use` に追加:

```php
use App\Services\MerchantAccess;
use Carbon\Carbon;
use Illuminate\Support\Facades\URL;
```

- [ ] **Step 2: 会員ランク API で注文できるかも返す**

`getMemberRank()` のうち、`$profile = ...` の取得から `return response()->json([...]);` まで（メソッドの中身全体）を置き換える:

```php
        $access = $this->merchantAccessFromToken($request->input('access_token'));
        if (!$access) {
            return response()->json(['error' => 'User not found or invalid token'], 404);
        }

        $merchant = $access->merchant;
        if (!$merchant) {
            return response()->json(['error' => 'Merchant not found'], 404);
        }

        return response()->json([
            'member_rank' => (int) ($merchant->member_rank ?? 1),
            // 注文画面はリッチメニューから全スタッフが開けるため、ここで出し分ける
            'can_order' => $access->can('can_order'),
        ]);
```

- [ ] **Step 3: 注文画面で権限がないときのメッセージを出す**

`resources/views/order/list.blade.php` の `<main class="lmf-main_contents">` の直後に追加:

```blade
		<div id="noOrderPermission" class="lmf-white_block" style="display: none; text-align: center; padding: 20px; margin: 20px 0; font-size: 14px;">
			注文の権限がありません。サロンオーナーにご確認ください。
		</div>
```

同じファイルのインラインスクリプトの `.then(function(data) {` の中、`var rank = ...` の前に追加:

```js
                // 注文の権限がないスタッフには商品一覧と確認ボタンを出さない（注文確定時にサーバーでも止める）
                if (data.can_order === false) {
                    document.getElementById('noOrderPermission').style.display = 'block';
                    var form = document.getElementById('send_form');
                    if (form) form.style.display = 'none';
                    var confirmButton = document.getElementById('confirm_button');
                    if (confirmButton) confirmButton.style.display = 'none';
                }
```

- [ ] **Step 4: 注文の確認・確定でも止める**

`register()` の `// 加盟店を特定（オーナー or メンバー）` から `$memberRank = ...;` の直前までを置き換える:

```php
        // 加盟店を特定（オーナー or メンバー）
        $access = MerchantAccess::forUser($user);
        if (!$access->can('can_order')) {
            return redirect()->route('order.list')->withErrors(['line_id' => '注文の権限がありません。サロンオーナーにご確認ください。']);
        }
        $merchant = $access->merchant;
```

`store()` の `$merchant = Merchant::where('user_id', $userId)->first();` から、その後の `if (!$merchant) { return ... '対応する店舗が見つかりません' ... }` までを置き換える:

```php
        $access = MerchantAccess::forUser($user);
        $merchant = $access->merchant;
        if (!$merchant) {
            return response()->json(['error' => '対応する店舗が見つかりません'], 404);
        }
        if (!$access->can('can_order')) {
            return response()->json(['error' => '注文の権限がありません。サロンオーナーにご確認ください。'], 403);
        }
```

- [ ] **Step 5: 注文履歴を権限で出し分け、詳細リンクを署名付きにする**

`resources/views/order/partials/no_permission.blade.php`:

```blade
<div class="lmf-white_block" style="text-align: center; padding: 20px; margin: 20px 0; font-size: 14px;">
    {{ $message }}
</div>
```

`getOrderHistory()` の `$accessToken = ...` から `$merchant_id = $merchant->id;` までを置き換える:

```php
        $access = $this->merchantAccessFromToken($data['accessToken'] ?? null);
        if (!$access) {
            return response()->json(['error' => 'User not found or invalid token'], 404);
        }

        $merchant = $access->merchant;
        if (!$merchant) {
            return response()->json(['error' => '対応する店舗が見つかりません'], 404);
        }

        // 履歴は liff.js が返した HTML をそのまま表示するので、権限がなければメッセージの HTML を返す
        if (!$access->can('can_view_order_history')) {
            $html = view('order.partials.no_permission', [
                'message' => '注文履歴を見る権限がありません。サロンオーナーにご確認ください。',
            ])->render();
            return response()->json(['html' => $html]);
        }

        $merchant_id = $merchant->id;
```

同じメソッドの `$orders->each(function ($order) { ... });` の中に追加（`total_price_included` の行の次）:

```php
            // 注文詳細は本人確認のため署名付き URL で開かせる
            $order->detail_url = URL::temporarySignedRoute('order.detail', Carbon::now()->addDay(), ['order' => $order->id]);
```

`resources/views/order/partials/history.blade.php:47` を置き換える:

```blade
        <a href="{{ $order->detail_url }}">注文詳細</a>
```

- [ ] **Step 6: 詳細・キャンセルを署名付き URL 必須にする**

`detail()` を置き換える:

```php
    public function detail(Request $request, Order $order)
    {
        // 履歴を見られる人にだけ発行する署名付き URL で開かせる（注文 ID の数字を変えて他店の注文を見られないように）
        if (!$request->hasValidSignature()) {
            return response()->view('merchants.invoice_expired', [
                'title' => '注文詳細',
                'message' => 'このリンクの有効期限が切れています。注文履歴からもう一度開いてください。',
            ], 403);
        }

        $order->load('details.product', 'merchant', 'agency');
        $cancelUrl = URL::temporarySignedRoute('order.cancel', Carbon::now()->addDay(), ['order' => $order->id]);
        return view('order.detail', compact('order', 'cancelUrl'));
    }
```

`cancel()` の先頭（`$order_id = $request->input('order_id');` 〜 `if(!$order) {...}`）を置き換える:

```php
        // 注文詳細を開ける人にだけ発行する署名付き URL で受け付ける
        if (!$request->hasValidSignature()) {
            return response()->json(['error' => 'このリンクの有効期限が切れています。注文履歴からもう一度開いてください。'], 403);
        }

        $order = Order::find($request->query('order'));
        if(!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }
```

`resources/views/order/detail.blade.php:105` を置き換える（`{{ }}` だと `&` が `&amp;` になり署名が壊れるため `@json`）:

```blade
        fetch(@json($cancelUrl), {
```

- [ ] **Step 7: 確認する**

ローカル（オーナー）で http://localhost:8884/order/list を開く。
Expected: 今まで通り商品一覧が出る。注文確認→確定まで進める。

http://localhost:8884/order/history を開く。
Expected: 履歴が出る。「注文詳細」を押すと詳細が開く（URL に `expires` と `signature` が付いている）。代理店未処理の注文ならキャンセルできる。

http://localhost:8884/order/detail/1 を署名なしで開く。
Expected: 「このリンクの有効期限が切れています。」（403）

`.env` の `DUMMY_LINE_ID` を `line_staff_check` にして `config:clear`。tinker で `can_order` と `can_view_order_history` を false にして:
- `/order/list` → 「注文の権限がありません。」が出て商品一覧が出ない
- `/order/history` → 「注文履歴を見る権限がありません。」が出る

確認後、2列を true に戻し、`DUMMY_LINE_ID` を `line_dummy_001` に戻して `config:clear`。

- [ ] **Step 8: Commit**

```bash
git -C /Users/sawadakeisuke/workspace/Ykk08OK add app/Http/Controllers/OrderController.php resources/views/order/partials/no_permission.blade.php resources/views/order/list.blade.php resources/views/order/partials/history.blade.php resources/views/order/detail.blade.php
git -C /Users/sawadakeisuke/workspace/Ykk08OK commit -m "注文と注文履歴を権限で判定し、注文詳細・キャンセルを署名付きURLにする"
```

---

### Task 5: 仕上げ（メインセッションが行う）

- [ ] **Step 1: 確認用データを片付ける**

Task 1 で確認用スタッフを作った場合は削除する:

```bash
docker exec php_ykk08ok php artisan tinker --execute="\$u = App\Models\User::where('line_id','line_staff_check')->first(); if (\$u) { App\Models\MerchantMember::where('user_id',\$u->id)->delete(); \$u->delete(); }"
```

`.env` の `DUMMY_LINE_ID` が `line_dummy_001` に戻っていることを確認する。

- [ ] **Step 2: 全体のレビュー**

`git -C /Users/sawadakeisuke/workspace/Ykk08OK diff 720bbd2..HEAD` を設計書と突き合わせる。

- [ ] **Step 3: デプロイ前にユーザーへ伝えること**

- マイグレーションが1本あり、push すると `migrate --force` で自動適用される（初期値付きの列追加のみ）。
- JS を変えたので `npm run build` → `public/build` を FTP でアップする必要がある。
- **今まで配ったスタッフ追加用の QR・URL は使えなくなる**（招待トークンが無いため）。オーナーにスタッフ一覧から QR を出し直してもらう必要がある。
- 開いたままの注文詳細・登録情報の修正画面は、開き直すまで使えない。
