# 加盟店ふりがな欄 実装計画

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 本部の管理画面と代理店画面の加盟店 登録／編集で、サロン名のふりがなを保存・再表示できるようにする。

**Architecture:** `merchants` に nullable な `name_kana` カラムを1本足し、`Merchant::$fillable` に加える。保存は既存の `Merchant::create($request->all())` / `$merchant->update($request->all())` にそのまま乗る。管理画面と代理店画面の4つの Blade にサロン名の直下でテキスト入力欄を置き、対応する4つのコントローラメソッドにひらがな限定の任意バリデーションを足す。

**Tech Stack:** PHP 8.1 / Laravel（`return new class extends Migration` 形式）/ Blade / MySQL

設計書: `docs/superpowers/specs/2026-09-04-merchant-name-kana-design.md`

## Global Constraints

- **テストコードは書かない**（`/Users/sawadakeisuke/workspace/CLAUDE.md` の方針）。検証は artisan tinker とブラウザでの目視で行う。
- 修正は最小限・局所的に。周辺のリファクタはしない。
- いま触っているファイルの既存スタイルに合わせる。インデントは既存行に合わせる。
- ローカル実行は必ずコンテナ経由: `docker exec php_ykk08ok php artisan ...`
- ローカルの URL は `http://localhost:8884`
- バリデーションルールは4箇所すべて同一の文字列を使う:
  `['nullable', 'string', 'max:255', 'regex:/\A[ぁ-んー\s　]+\z/u']`
- エラーメッセージは4箇所すべて同一: `ふりがなはひらがなで入力してください。`
- 正規表現の文字クラスには**全角スペースを実体で含める**。`\s` は全角スペースにマッチしない。

---

## File Structure

| ファイル | 役割 | 操作 |
| --- | --- | --- |
| `database/migrations/2026_09_04_000001_add_name_kana_to_merchants_table.php` | `merchants.name_kana` の追加 | 新規 |
| `app/Models/Merchant.php` | `$fillable` に `name_kana` を追加 | 修正 |
| `app/Http/Controllers/Admin/MerchantController.php` | 本部側 store / update のバリデーション | 修正 |
| `resources/views/admin/merchants/create.blade.php` | 本部側 新規登録フォーム | 修正 |
| `resources/views/admin/merchants/edit.blade.php` | 本部側 編集フォーム | 修正 |
| `app/Http/Controllers/Agency/MerchantController.php` | 代理店側 store / update のバリデーション | 修正 |
| `resources/views/agencies/merchants/create.blade.php` | 代理店側 新規登録フォーム | 修正 |
| `resources/views/agencies/merchants/edit.blade.php` | 代理店側 編集フォーム | 修正 |

---

### Task 1: カラム追加とモデル

**Files:**
- Create: `database/migrations/2026_09_04_000001_add_name_kana_to_merchants_table.php`
- Modify: `app/Models/Merchant.php:19-34`

**Interfaces:**
- Consumes: なし
- Produces: `merchants.name_kana`（`varchar(255)` NULL 可）と、`Merchant` モデルの mass assignment 対象キー `name_kana`。Task 2 と Task 3 はこのキーが `$fillable` にある前提で `$request->all()` 経由の保存に依存する。

- [ ] **Step 1: マイグレーションファイルを作成**

`database/migrations/2026_09_04_000001_add_name_kana_to_merchants_table.php` を新規作成する。ファイル名の連番形式と無名クラス形式は既存の `2026_08_01_000002_add_is_test_to_merchants_table.php` に合わせている。

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
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('name_kana')->nullable()->after('name'); // サロン名のふりがな
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn('name_kana');
        });
    }
};
```

- [ ] **Step 2: マイグレーションを実行**

Run: `docker exec php_ykk08ok php artisan migrate`
Expected: `2026_09_04_000001_add_name_kana_to_merchants_table` が `DONE` になる。

- [ ] **Step 3: カラムが追加されたことを確認**

Run:
```bash
docker exec php_ykk08ok php artisan tinker --execute="foreach(DB::select('DESCRIBE merchants') as \$c){echo \$c->Field.' | '.\$c->Type.' | null='.\$c->Null.PHP_EOL;}"
```
Expected: 出力に `name_kana | varchar(255) | null=YES` が含まれ、`name` の直後に並んでいる。

- [ ] **Step 4: `$fillable` に `name_kana` を追加**

`app/Models/Merchant.php` の `$fillable` 配列で、`'name',` の直後に1行足す。

```php
    protected $fillable = [
        'agency_id',
        'name',
        'name_kana',
        'merchant_code',
```

- [ ] **Step 5: mass assignment で保存・再取得できることを確認**

Run:
```bash
docker exec php_ykk08ok php artisan tinker --execute="\$m=App\Models\Merchant::first(); \$before=\$m->name_kana; \$m->update(['name_kana'=>'てすとさろん']); echo 'saved='.App\Models\Merchant::find(\$m->id)->name_kana.PHP_EOL; \$m->update(['name_kana'=>\$before]); var_dump(App\Models\Merchant::find(\$m->id)->name_kana);"
```
Expected: `saved=てすとさろん` が表示され、そのあと元の値（`NULL`）に戻る。

**注意:** このコマンドは検証用に既存レコードを一時的に書き換えて元に戻している。実行後に `NULL` に戻っていることを必ず出力で確認すること。

- [ ] **Step 6: コミット**

```bash
git -C /Users/sawadakeisuke/workspace/Ykk08OK add database/migrations/2026_09_04_000001_add_name_kana_to_merchants_table.php app/Models/Merchant.php
git -C /Users/sawadakeisuke/workspace/Ykk08OK commit -m "加盟店にサロン名のふりがなカラムを追加"
```

---

### Task 2: 管理画面（本部）の入力欄とバリデーション

**Files:**
- Modify: `app/Http/Controllers/Admin/MerchantController.php:50-74`（store）, `:92-119`（update）
- Modify: `resources/views/admin/merchants/create.blade.php:15-16`
- Modify: `resources/views/admin/merchants/edit.blade.php:33-34`

**Interfaces:**
- Consumes: Task 1 の `merchants.name_kana` と `Merchant::$fillable` の `name_kana`
- Produces: 本部側フォームの `name` 属性 `name_kana`。Task 3 は同じ入力名・同じバリデーションルール文字列・同じエラーメッセージを代理店側で再利用する。

- [ ] **Step 1: store のバリデーションにルールを追加**

`app/Http/Controllers/Admin/MerchantController.php` の `store` で、`'name' => 'required|string|max:255',` の直後に1行足す。

```php
            'name' => 'required|string|max:255',
            'name_kana' => ['nullable', 'string', 'max:255', 'regex:/\A[ぁ-んー\s　]+\z/u'],
```

- [ ] **Step 2: store のバリデーションにエラーメッセージを追加**

同じ `store` の `validate()` の閉じ括弧を書き換える。変更前は `'user_id' => [...]` の配列を閉じたあとの行。

変更前:
```php
        ]);

        Merchant::create($request->all());
```

変更後:
```php
        ], [
            'name_kana.regex' => 'ふりがなはひらがなで入力してください。',
        ]);

        Merchant::create($request->all());
```

- [ ] **Step 3: update のバリデーションにルールを追加**

同ファイルの `update` で、`'name' => 'required|string|max:255',` の直後に1行足す。`update` の中身は `try {` の内側にあるためインデントが1段深い。

```php
                'name' => 'required|string|max:255',
                'name_kana' => ['nullable', 'string', 'max:255', 'regex:/\A[ぁ-んー\s　]+\z/u'],
```

- [ ] **Step 4: update のバリデーションにエラーメッセージを追加**

`update` の `validate()` の閉じ括弧を書き換える。`'agency_id' => 'required|integer|exists:agencies,id',` の次の行。

変更前:
```php
                'agency_id' => 'required|integer|exists:agencies,id',
            ]);
```

変更後:
```php
                'agency_id' => 'required|integer|exists:agencies,id',
            ], [
                'name_kana.regex' => 'ふりがなはひらがなで入力してください。',
            ]);
```

- [ ] **Step 5: PHP の構文チェック**

Run: `docker exec php_ykk08ok php -l app/Http/Controllers/Admin/MerchantController.php`
Expected: `No syntax errors detected`

- [ ] **Step 6: バリデーションルールの挙動を確認**

Run:
```bash
docker exec php_ykk08ok php artisan tinker --execute="\$r=['nullable','string','max:255','regex:/\A[ぁ-んー\s　]+\z/u']; foreach(['さろんてすと'=>'ok','ながおんー'=>'ok','あい うえ'=>'ok',''=>'ok','サロン'=>'ng','salon'=>'ng','さろん1'=>'ng'] as \$v=>\$want){\$ok=!Validator::make(['name_kana'=>\$v],['name_kana'=>\$r])->fails(); echo str_pad(\$v==''?'(空)':\$v,12).' expect='.\$want.' actual='.(\$ok?'ok':'ng').PHP_EOL;}"
```
Expected: 全行で `expect` と `actual` が一致する。ひらがな・長音符・スペース・空文字が `ok`、カタカナ・英字・数字混じりが `ng`。

- [ ] **Step 7: 新規登録フォームに入力欄を追加**

`resources/views/admin/merchants/create.blade.php` のサロン名の `<dd>` の直後（16行目のあと）に2行足す。

```blade
                <dt><label for="product_code">サロン名</label></dt>
                <dd><input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required></dd>
                <dt><label for="name_kana">ふりがな</label></dt>
                <dd><input type="text" class="form-control" id="name_kana" name="name_kana" value="{{ old('name_kana') }}" placeholder="ひらがなで入力"></dd>
```

- [ ] **Step 8: 編集フォームに入力欄を追加**

`resources/views/admin/merchants/edit.blade.php` のサロン名の `<dd>` の直後（34行目のあと）に2行足す。`value` が `old()` の第2引数にモデル値を渡す形になっている点が create と違う。

```blade
                <dt><label for="product_code">サロン名</label></dt>
                <dd><input type="text" class="form-control" id="name" name="name" value="{{ old('name', $merchant->name) }}" required></dd>
                <dt><label for="name_kana">ふりがな</label></dt>
                <dd><input type="text" class="form-control" id="name_kana" name="name_kana" value="{{ old('name_kana', $merchant->name_kana) }}" placeholder="ひらがなで入力"></dd>
```

- [ ] **Step 9: Blade の構文チェック**

Run: `docker exec php_ykk08ok php artisan view:cache && docker exec php_ykk08ok php artisan view:clear`
Expected: どちらも成功し、Blade のコンパイルエラーが出ない。

- [ ] **Step 10: ブラウザで目視確認**

`http://localhost:8884` の管理画面にログインし、加盟店の新規登録と編集を開く。

確認すること:
1. サロン名の直下に「ふりがな」欄がある
2. 空欄のまま保存できる
3. `サロン` などカタカナで保存すると「ふりがなはひらがなで入力してください。」が出て保存されない
4. `さろん` で保存すると成功し、編集画面を開き直すと値が入っている

- [ ] **Step 11: コミット**

```bash
git -C /Users/sawadakeisuke/workspace/Ykk08OK add app/Http/Controllers/Admin/MerchantController.php resources/views/admin/merchants/create.blade.php resources/views/admin/merchants/edit.blade.php
git -C /Users/sawadakeisuke/workspace/Ykk08OK commit -m "管理画面の加盟店 登録・編集にふりがな欄を追加"
```

---

### Task 3: 代理店画面の入力欄とバリデーション

**Files:**
- Modify: `app/Http/Controllers/Agency/MerchantController.php:47-64`（store）, `:79-96`（update）
- Modify: `resources/views/agencies/merchants/create.blade.php:15-16`
- Modify: `resources/views/agencies/merchants/edit.blade.php:13-14`

**Interfaces:**
- Consumes: Task 1 の `merchants.name_kana` と `Merchant::$fillable` の `name_kana`
- Produces: なし（この計画の最終タスク）

- [ ] **Step 1: store のバリデーションにルールとメッセージを追加**

`app/Http/Controllers/Agency/MerchantController.php` の `store`。`'name' => ...` の直後にルールを足し、閉じ括弧にメッセージを足す。管理画面側と `update` の分岐条件が違うだけで、ルール文字列とメッセージは完全に同じものを使う。

変更前:
```php
        $request->validate([
            'name' => 'required|string|max:255',
```

変更後:
```php
        $request->validate([
            'name' => 'required|string|max:255',
            'name_kana' => ['nullable', 'string', 'max:255', 'regex:/\A[ぁ-んー\s　]+\z/u'],
```

変更前:
```php
            'user_id' => 'required|integer|exists:users,id',
        ]);

        Merchant::create($request->all());
```

変更後:
```php
            'user_id' => 'required|integer|exists:users,id',
        ], [
            'name_kana.regex' => 'ふりがなはひらがなで入力してください。',
        ]);

        Merchant::create($request->all());
```

- [ ] **Step 2: update のバリデーションにルールとメッセージを追加**

同ファイルの `update`。こちらは `Merchant::findOrFail($id)` の手前で閉じている。

変更前:
```php
        $request->validate([
            'name' => 'required|string|max:255',
```

変更後:
```php
        $request->validate([
            'name' => 'required|string|max:255',
            'name_kana' => ['nullable', 'string', 'max:255', 'regex:/\A[ぁ-んー\s　]+\z/u'],
```

変更前:
```php
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $merchant = Merchant::findOrFail($id);
```

変更後:
```php
            'user_id' => 'required|integer|exists:users,id',
        ], [
            'name_kana.regex' => 'ふりがなはひらがなで入力してください。',
        ]);

        $merchant = Merchant::findOrFail($id);
```

- [ ] **Step 3: PHP の構文チェック**

Run: `docker exec php_ykk08ok php -l app/Http/Controllers/Agency/MerchantController.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: 新規登録フォームに入力欄を追加**

`resources/views/agencies/merchants/create.blade.php` のサロン名の `<dd>` の直後（16行目のあと）に2行足す。管理画面と違い `<label for>` が `name` になっている点はそのままにする。

```blade
                <dt><label for="name">サロン名</label></dt>
                <dd><input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required></dd>
                <dt><label for="name_kana">ふりがな</label></dt>
                <dd><input type="text" class="form-control" id="name_kana" name="name_kana" value="{{ old('name_kana') }}" placeholder="ひらがなで入力"></dd>
```

- [ ] **Step 5: 編集フォームに入力欄を追加**

`resources/views/agencies/merchants/edit.blade.php` のサロン名の `<dd>` の直後（14行目のあと）に2行足す。

```blade
                <dt><label for="name">サロン名</label></dt>
                <dd><input type="text" class="form-control" id="name" name="name" value="{{ old('name', $merchant->name) }}" required></dd>
                <dt><label for="name_kana">ふりがな</label></dt>
                <dd><input type="text" class="form-control" id="name_kana" name="name_kana" value="{{ old('name_kana', $merchant->name_kana) }}" placeholder="ひらがなで入力"></dd>
```

- [ ] **Step 6: Blade の構文チェック**

Run: `docker exec php_ykk08ok php artisan view:cache && docker exec php_ykk08ok php artisan view:clear`
Expected: どちらも成功し、Blade のコンパイルエラーが出ない。

- [ ] **Step 7: ブラウザで目視確認**

代理店アカウントでログインし、加盟店の新規登録と編集を開く。Task 2 の Step 10 と同じ4点を確認する。

- [ ] **Step 8: 加盟店本人の編集でふりがなが消えないことを確認**

`resources/views/merchants/edit.blade.php`（加盟店本人の LIFF 編集画面）にはふりがな欄を置かないため `name_kana` は送信されない。ふりがなを入れた加盟店で、本人側の編集からサロン名などを更新し、そのあと管理画面の編集を開いてふりがなが残っていることを確認する。

ローカルで LIFF 画面を触りにくい場合は、送信されないケースを直接再現して確認する。

Run:
```bash
docker exec php_ykk08ok php artisan tinker --execute="\$m=App\Models\Merchant::first(); \$before=\$m->name_kana; \$m->update(['name_kana'=>'てすとさろん']); \$m->update(['name'=>\$m->name]); echo 'after='.App\Models\Merchant::find(\$m->id)->name_kana.PHP_EOL; \$m->update(['name_kana'=>\$before]); var_dump(App\Models\Merchant::find(\$m->id)->name_kana);"
```
Expected: `after=てすとさろん`（`name_kana` を含まない更新をしても値が残る）。そのあと元の値に戻る。

- [ ] **Step 9: コミット**

```bash
git -C /Users/sawadakeisuke/workspace/Ykk08OK add app/Http/Controllers/Agency/MerchantController.php resources/views/agencies/merchants/create.blade.php resources/views/agencies/merchants/edit.blade.php
git -C /Users/sawadakeisuke/workspace/Ykk08OK commit -m "代理店画面の加盟店 登録・編集にふりがな欄を追加"
```

---

## デプロイ時の注意

本番デプロイは GitHub Actions の `Deploy to Production` が `git pull` と
`php artisan migrate --force` を実行する。Task 1 のマイグレーションはこれで自動適用される。

直近の run 33235675229 は SSH 接続タイムアウトで失敗している。push 後は
`gh run list --repo gypsy-style/Ykk08OK` でデプロイの成否を必ず確認すること。
失敗している場合、マイグレーションが本番に当たっていないため画面は 500 になる。
