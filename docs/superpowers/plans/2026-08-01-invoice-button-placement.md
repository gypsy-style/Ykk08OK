# 請求書ボタン上部配置 実装計画

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 登録情報画面の請求書ボタンを最下部から代理店名の上へ移し、請求書が1件も無い加盟店には出さない。

**Architecture:** ボタンは従来どおりHTMLに常置し、表示可否はJSが決める。`.lmf-title_block.tall` の `margin-bottom:-50px` をボタン側の `margin-top:50px` で打ち消して帯へのめり込みを防ぐ。請求書の有無は `InvoiceService::hasInvoice()` を新設して `getMerchantInformation` のレスポンスに載せる。

**Tech Stack:** Laravel 10 / Blade / 素のJS（LIFF SDK） / Vite / Docker（php_ykk08ok, web_ykk08ok）

**元になった設計:** `docs/superpowers/specs/2026-08-01-invoice-button-placement-design.md`

## Global Constraints

- **テストコードは書かない**（`/Users/sawadakeisuke/workspace/CLAUDE.md` の方針）。各タスクは tinker とブラウザでの検証で代替する。TDDの手順は適用しない。
- 修正は最小限・局所的に。いま触っているファイルの既存スタイルに合わせる。全面リライトはしない。
- ローカルの画面は `http://localhost:8884`。`LIFF_MOCK=true` でLIFF認証はスキップされ、`dummy_` で始まるトークンは `.env` の `DUMMY_LINE_ID`（`line_dummy_001`）のユーザーとして扱われる。
- artisan は必ず `docker exec php_ykk08ok php artisan ...` で実行する。ホストからはDBに到達できない。
- CSS/JSを変更したら `npm run build` が必要。Vite devサーバーは動いていない。
- 確定注文のステータスは `InvoiceService::SALES_STATUSES`（`[2, 3, 5, 6]`）。当月分は未確定として除外する。
- 本番反映は git push に加えて `public/build` の手動アップロードが必要（`public/build` は `.gitignore` 済み）。この計画には含めない。
- 作業ツリーには別セッションの変更（`resources/views/admin/sales/invoice.blade.php`、`resources/views/merchants/invoice_pdf.blade.php`、`.progress.json`）が入っている。**コミットは対象ファイルを名指しで `git add` すること。`git add -A` / `git add .` は使わない。**

---

### Task 1: レイアウトを B-2 の最終形にする

作業ツリーには案を見比べるための暫定コードが入っている。これを取り除き、B-2だけを残す。

**Files:**
- Modify: `resources/css/front.css`（`.lmf-btn_box.btn_or` の定義の直後にある暫定ブロック）
- Modify: `resources/views/merchants/information.blade.php`

**Interfaces:**
- Consumes: なし
- Produces: CSSクラス `btn_top`（`.lmf-btn_box` と併用）。`.lmf-content` の先頭要素をヘッダー帯の下へ押し下げる。請求書ボタンの要素は `<p class="lmf-btn_box invoice_list btn_top">` として `.lmf-content` の先頭に常置される（`display:none` 初期値）。

- [ ] **Step 1: front.css から B-1 用の暫定ルールを削除する**

`resources/css/front.css` の以下のブロックを

```css
/* コンテンツ先頭に置くボタン
   .lmf-title_block.tall の margin-bottom:-50px を打ち消して帯へのめり込みを防ぐ */
.lmf-btn_box.btn_top {
	margin-top: 50px;
}
.lmf-btn_box.btn_topover a {
	box-shadow: 0px 0px 15px 0px rgba(0, 0, 0, .3);
}
```

こう置き換える（`.btn_topover` を削除するだけ）。

```css
/* コンテンツ先頭に置くボタン
   .lmf-title_block.tall の margin-bottom:-50px を打ち消して帯へのめり込みを防ぐ */
.lmf-btn_box.btn_top {
	margin-top: 50px;
}
```

- [ ] **Step 2: blade からプレビュー用の分岐を削除する**

`resources/views/merchants/information.blade.php` の以下の2行を

```blade
                @php $ui = request('ui') === 'b1' ? 'invoice_top_overlap btn_wh btn_topover' : 'btn_top'; @endphp
                <p class="lmf-btn_box invoice_list {{ $ui }}" style="display:none;"><a href="{{ route('merchants.invoices') }}">請求書</a></p>
```

この1行に置き換える。

```blade
                <p class="lmf-btn_box invoice_list btn_top" style="display:none;"><a href="{{ route('merchants.invoices') }}">請求書</a></p>
```

- [ ] **Step 3: ビルドする**

Run: `npm run build`
Expected: `✓ built in ...` で終わり、エラーが出ない。

- [ ] **Step 4: マークアップを確認する**

Run: `curl -s "http://localhost:8884/merchants/information" | grep -n "lmf-btn_box\|lmf-white_block"`
Expected: `invoice_list btn_top` の `<p>` が `lmf-white_block` の `<div>` **より前の行**に出る。`invoice_top_overlap` や `btn_wh` は出ない。

Run: `curl -s "http://localhost:8884/merchants/information?ui=b1" | grep -c "btn_topover"`
Expected: `0`（プレビュー分岐が消えているので `?ui=b1` でも何も変わらない）

- [ ] **Step 5: ブラウザで見た目を確認する**

`http://localhost:8884/merchants/information` を開く。確認するのは3点。

1. 請求書ボタンが「代理店名」より上、青いヘッダー帯の**下**に出ている
2. ボタンの上部が帯に食い込んでいない（帯の下端から30pxほど空く）
3. 「登録スタッフ一覧」は従来どおりページ最下部にあり、請求書ボタンは最下部から消えている

- [ ] **Step 6: コミットする**

```bash
git add resources/css/front.css resources/views/merchants/information.blade.php
git commit -m "請求書ボタンを登録情報の上部へ移動"
```

---

### Task 2: 請求書の有無を判定して API に載せる

**Files:**
- Modify: `app/Services/InvoiceService.php`（`monthlyBreakdown()` の直後、`forMonth()` の前）
- Modify: `app/Http/Controllers/MerchantController.php`（`getMerchantInformation()` の `return response()->json([...])`）

**Interfaces:**
- Consumes: `InvoiceService::SALES_STATUSES`（既存の定数 `[2, 3, 5, 6]`）
- Produces: `InvoiceService::hasInvoice(Merchant $merchant): bool` と、`POST /get-merchant-information` のレスポンスに追加される `has_invoice`（bool）

- [ ] **Step 1: InvoiceService に hasInvoice() を追加する**

`app/Services/InvoiceService.php` の `monthlyBreakdown()` の閉じ括弧の直後に挿入する。`Order` と `Carbon` はファイル先頭で import 済みなので追加不要。

```php
    /**
     * 確定済み（前月まで）の請求書が1件でもあるか
     *
     * @param Merchant $merchant
     * @return bool
     */
    public function hasInvoice(Merchant $merchant)
    {
        return Order::where('merchant_id', $merchant->id)
            ->whereIn('status', self::SALES_STATUSES)
            ->where('created_at', '<', Carbon::now()->startOfMonth())
            ->exists();
    }
```

- [ ] **Step 2: 判定が正しいか確認する**

Run:

```bash
docker exec php_ykk08ok php artisan tinker --execute="
\$s = app(App\Services\InvoiceService::class);
foreach (App\Models\Merchant::all() as \$m) {
  echo \$m->id . ' ' . \$m->name . ' => ' . var_export(\$s->hasInvoice(\$m), true) . PHP_EOL;
}
"
```

Expected: 加盟店ごとに `true` / `false` が並ぶ。`ダミー加盟店`（id=1）は確定注文が6ヶ月分あるので `true`。ここで **`false` になる加盟店のidを1つ控えておく**（Task 3 の検証で使う）。1件も `false` が無い場合は、Task 3 の Step 4 で代わりに一時的なデータ変更を使う。

- [ ] **Step 3: レスポンスに has_invoice を追加する**

`app/Http/Controllers/MerchantController.php` の `getMerchantInformation()` の返却配列に1行足す。`$this->invoiceService` はコンストラクタで注入済み。

```php
        return response()->json([
            'user_id' => $user->id,
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
            'has_invoice' => $this->invoiceService->hasInvoice($merchant),
        ]);
```

- [ ] **Step 4: APIのレスポンスを確認する**

このエンドポイントはCSRF保護下にあるためcurlでは叩きにくい。ブラウザの開発者ツールで確認する。

`http://localhost:8884/merchants/information` を開き、Networkタブの `get-merchant-information` のレスポンスJSONに `has_invoice: true` が含まれることを確認する。

- [ ] **Step 5: コミットする**

```bash
git add app/Services/InvoiceService.php app/Http/Controllers/MerchantController.php
git commit -m "請求書の有無を判定する hasInvoice を追加"
```

---

### Task 3: 請求書がある場合だけボタンを出す

**Files:**
- Modify: `resources/js/liff_information.js`（`updateMerchantInformation()` 内、オーナー判定のブロック）

**Interfaces:**
- Consumes: `POST /get-merchant-information` のレスポンスの `has_invoice`（Task 2 で追加）、CSSクラス `invoice_list`（Task 1 で配置）
- Produces: なし（このタスクで機能が完成する）

- [ ] **Step 1: 表示条件を追加する**

`resources/js/liff_information.js` の以下のブロックを

```js
    if(userId == merchantUserId) {
         // 「登録情報を修正する」ボタンを表示
         document.querySelector('.lmf-btn_box.btn_dgy.btn_small').style.display = 'block';
         // 「登録スタッフ一覧」ボタンを表示
         document.querySelector('.lmf-btn_box.member_list').style.display = 'block';
         // 「請求書」ボタンを表示
         document.querySelector('.lmf-btn_box.invoice_list').style.display = 'block';
    }
```

こう置き換える。

```js
    if(userId == merchantUserId) {
         // 「登録情報を修正する」ボタンを表示
         document.querySelector('.lmf-btn_box.btn_dgy.btn_small').style.display = 'block';
         // 「登録スタッフ一覧」ボタンを表示
         document.querySelector('.lmf-btn_box.member_list').style.display = 'block';
         // 「請求書」ボタンは確定済みの請求書がある場合のみ表示
         if (data.has_invoice) {
             document.querySelector('.lmf-btn_box.invoice_list').style.display = 'block';
         }
    }
```

- [ ] **Step 2: ビルドする**

Run: `npm run build`
Expected: エラーなく完了する。

- [ ] **Step 3: 請求書ありのケースを確認する**

`http://localhost:8884/merchants/information` を開く。`DUMMY_LINE_ID=line_dummy_001` は `ダミー加盟店`（id=1、確定注文6ヶ月分）のオーナーなので、請求書ボタンが表示される。ボタンから請求書一覧へ遷移でき、月ごとの行が出ることも確認する。

- [ ] **Step 4: 請求書なしのケースを確認する**

Task 2 の Step 2 で控えた「`hasInvoice` が `false` の加盟店」のオーナーの `line_id` を調べる。

```bash
docker exec php_ykk08ok php artisan tinker --execute="
\$m = App\Models\Merchant::find(<控えたid>);
echo App\Models\User::find(\$m->user_id)->line_id . PHP_EOL;
"
```

`.env` の `DUMMY_LINE_ID` をその値に一時的に書き換え、`docker exec php_ykk08ok php artisan config:clear` を実行してから画面を開く。

Expected: 「登録情報を修正する」「登録スタッフ一覧」は出るが、**請求書ボタンだけ出ない**。

確認できたら `.env` を `DUMMY_LINE_ID=line_dummy_001` に戻し、もう一度 `docker exec php_ykk08ok php artisan config:clear` を実行する。`.env` はコミットしない。

`hasInvoice` が `false` の加盟店が1つも無い場合は、代わりに `ダミー加盟店` の確定注文を一時的にキャンセル扱いにして確認し、確認後に必ず戻す。

```bash
# 一時的にキャンセル扱いにする
docker exec php_ykk08ok php artisan tinker --execute="
App\Models\Order::where('merchant_id', 1)->whereIn('status', [2,3,5,6])->update(['status' => 9]);
"
# 確認後に戻す（status 6 = 発送済みへ）
docker exec php_ykk08ok php artisan tinker --execute="
App\Models\Order::where('merchant_id', 1)->where('status', 9)->update(['status' => 6]);
"
```

戻す側のクエリは元から `status=9` だった注文も 6 に変えてしまう。`ダミー加盟店` にキャンセル注文が元からある場合はこの手順を使わず、別加盟店を用意すること。

- [ ] **Step 5: スタッフアカウントで出ないことを確認する**

`MerchantMember` として登録されているユーザーの `line_id` を `DUMMY_LINE_ID` に設定し、`config:clear` して画面を開く。

```bash
docker exec php_ykk08ok php artisan tinker --execute="
\$mm = App\Models\MerchantMember::first();
echo \$mm ? App\Models\User::find(\$mm->user_id)->line_id : 'NO MEMBER';
"
```

Expected: 3つのボタンがどれも表示されない（オーナー判定が偽のため）。`MerchantMember` が1件も無ければこの確認は省略してよい。確認後は `.env` を戻して `config:clear`。

- [ ] **Step 6: コミットする**

```bash
git add resources/js/liff_information.js
git commit -m "請求書がある場合のみ請求書ボタンを表示"
```

---

## 完了後

`public/build` は `.gitignore` 対象のため、本番へは git push とは別に手動アップロードが必要。反映後、本番のLINE（オーナーアカウント）で請求書ボタンの位置と表示可否を確認する。
