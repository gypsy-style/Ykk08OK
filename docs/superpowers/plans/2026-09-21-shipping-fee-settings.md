# 都道府県別の送料設定画面 実装計画

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 管理画面の設定に「送料設定」ページを追加し、送料設定金額と 47 都道府県 × 2 種類の送料を保存できるようにする。

**Architecture:** 既存の設定セクション（`Admin/SettingController` ＋ `settings` キーバリューテーブル）の
パターンをそのまま踏襲する。新テーブルもマイグレーションも作らない。47 都道府県の一覧は
`config/prefectures.php` に切り出して 1 箇所にまとめ、送料は `shipping_threshold`（数値）と
`shipping_fees`（JSON）の 2 キーに保存する。保存した値は本計画の範囲ではどこからも参照されない。

**Tech Stack:** Laravel（PHP）、Blade、MySQL、Docker Compose（サービス名 `php_ykk08ok`）、ローカルは http://localhost:8884

**設計書:** `docs/superpowers/specs/2026-09-21-shipping-fee-settings-design.md`

## Global Constraints

- **テストコードは書かない。** 本プロジェクトの方針。確認は画面・curl・`artisan tinker` で行う。
- **`php artisan migrate:fresh` / `migrate:refresh` / `db:wipe` は絶対に実行しない。** ローカル DB を全消しする事故が過去にあった。
- **`.progress.json` には触らない。**
- **`main` に直接コミットしない。** 作業ブランチは `feature/shipping-fee-settings`（作成済み）。
- **`public/` 配下のビルド成果物を触らない。** `public/build` は git 管理外で、変更すると別途 FTP アップロードが必要になる。CSS はビュー内のインライン `<style>` で完結させ、`resources/css/admin.css` は編集しない。
- **いま触っているファイルの既存スタイルに合わせる。** 大規模リファクタや全面リライトはしない。
- 保存した送料を注文に反映する処理は本計画の対象外。実装しない。
- 都道府県名は `config('prefectures')` を唯一の定義元とする。他の場所に配列をベタ書きしない。
- 金額の単位はすべて「円（税込）」。画面のラベルもこの表記で統一する。

---

## ファイル構成

| ファイル | 種別 | 責務 |
| --- | --- | --- |
| `config/prefectures.php` | 新規 | 47 都道府県名の配列。唯一の定義元 |
| `resources/views/merchants/create.blade.php` | 変更 | :27 のベタ書き配列を `config('prefectures')` に置き換え |
| `routes/web.php` | 変更 | `settings/shipping` の GET / POST を追加 |
| `app/Http/Controllers/Admin/SettingController.php` | 変更 | `shipping()` / `updateShipping()` / private `shippingFees()` を追加 |
| `resources/views/admin/settings/_nav.blade.php` | 変更 | 「送料設定」のリンクを 1 行追加 |
| `resources/views/admin/settings/shipping.blade.php` | 新規 | 送料設定フォーム |

---

## 事前準備：管理画面にログインしたセッションを作る

確認作業で毎回使うので、最初に 1 回だけ実行してクッキーを用意する。
セッションが切れて 302 が返るようになったら、このブロックをもう一度実行すること。

- [ ] **クッキーを捨ててログインページから CSRF トークンを取る**

```bash
rm -f /tmp/goon_cookie.txt
curl -s -c /tmp/goon_cookie.txt http://localhost:8884/admin/login | grep -o 'name="_token" value="[^"]*"'
```

出力例: `name="_token" value="AbCdEf..."`。この値を次のコマンドの `<TOKEN>` に入れる。

- [ ] **ログインする**

```bash
curl -s -b /tmp/goon_cookie.txt -c /tmp/goon_cookie.txt -X POST http://localhost:8884/admin/login -d "_token=<TOKEN>" -d "email=admin@example.com" -d "password=password" -o /dev/null -w "%{http_code}\n"
```

期待: `302`（ダッシュボードへのリダイレクト）。

- [ ] **ログインできたことを確かめる**

```bash
curl -s -b /tmp/goon_cookie.txt -o /dev/null -w "%{http_code}\n" http://localhost:8884/admin/settings/custom-css
```

期待: `200`。`302` が返る場合はログインできていないので、トークンを取り直してやり直す。

---

## Task 1: 都道府県の一覧を config に切り出す

47 都道府県の配列が `resources/views/merchants/create.blade.php:27` にベタ書きされている。
送料設定画面でも同じ一覧が必要になるため、先に `config/prefectures.php` へ移し、
既存画面をそこ参照に置き換える。**この時点で挙動は一切変わらない。**

**Files:**
- Create: `config/prefectures.php`
- Modify: `resources/views/merchants/create.blade.php:27`

**Interfaces:**
- Consumes: なし
- Produces: `config('prefectures')` — 47 都道府県名（日本語文字列）を北から順に並べた添字配列。Task 2 のコントローラとビューが使う

- [ ] **Step 1: `config/prefectures.php` を作る**

```php
<?php

return [
    '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
    '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
    '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県',
    '岐阜県', '静岡県', '愛知県', '三重県',
    '滋賀県', '京都府', '大阪府', '兵庫県', '奈良県', '和歌山県',
    '鳥取県', '島根県', '岡山県', '広島県', '山口県',
    '徳島県', '香川県', '愛媛県', '高知県',
    '福岡県', '佐賀県', '長崎県', '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県',
];
```

- [ ] **Step 2: 要素数が 47 であることを確認する**

```bash
docker compose -f /Users/sawadakeisuke/workspace/Ykk08OK/docker-compose.yml exec -T php_ykk08ok php artisan tinker --execute="echo count(config('prefectures')) . PHP_EOL; echo config('prefectures')[0] . ' / ' . config('prefectures')[46] . PHP_EOL;"
```

期待: `47` と `北海道 / 沖縄県`。

- [ ] **Step 3: LIFF の加盟店新規登録画面のベタ書きを置き換える**

`resources/views/merchants/create.blade.php:27` の

```blade
                                @foreach(['北海道','青森県','岩手県','宮城県','秋田県','山形県','福島県','茨城県','栃木県','群馬県','埼玉県','千葉県','東京都','神奈川県','新潟県','富山県','石川県','福井県','山梨県','長野県','岐阜県','静岡県','愛知県','三重県','滋賀県','京都府','大阪府','兵庫県','奈良県','和歌山県','鳥取県','島根県','岡山県','広島県','山口県','徳島県','香川県','愛媛県','高知県','福岡県','佐賀県','長崎県','熊本県','大分県','宮崎県','鹿児島県','沖縄県'] as $pref)
```

を次の 1 行に置き換える。前後の行（`<option value="">選択してください</option>` と
`<option value="{{ $pref }}" ...>`、`@endforeach`）はそのまま残すこと。

```blade
                                @foreach(config('prefectures') as $pref)
```

- [ ] **Step 4: 置き換え後も 47 件の option が出ることを確認する**

この画面は LIFF 用で認証なしに開ける。`old()` の三項演算子の分だけ属性のあとに空白が入り
`<option value="北海道" >` と出力されるため、`>` までをパターンに含めないこと。

```bash
curl -s http://localhost:8884/merchants/create -o /tmp/mc.html -w "%{http_code}\n"
grep -c '<option value="[^"]\+"' /tmp/mc.html
grep -o '<option value="北海道"\|<option value="沖縄県"' /tmp/mc.html
```

期待: `200`、`47`（`value=""` の「選択してください」は `[^"]\+` に一致しないので除外される）、
そして北海道・沖縄県の 2 行。置き換え前に同じコマンドを実行して同じ結果になることを
確かめておくと、差分が無いことを確認できる。

この画面の郵便番号検索（`resources/views/merchants/create.blade.php:60-84`）は
セレクトの `options` を走査して `address1` と一致するものを選ぶ実装で、配列の出どころが
変わっても影響を受けない。JS は触らないこと。

- [ ] **Step 5: コミット**

```bash
git add config/prefectures.php resources/views/merchants/create.blade.php
git commit -m "都道府県の一覧を config/prefectures.php に切り出す"
```

---

## Task 2: 送料設定ページを追加する

ルート 2 本、コントローラのメソッド 2 つ（＋ private ヘルパー 1 つ）、ナビ 1 行、ビュー 1 枚を追加する。
既存の「会社情報」ページ（`resources/views/admin/settings/company_info.blade.php`、
`Admin/SettingController::companyInfo()` / `updateCompanyInfo()`）が最も近い形なので、それに合わせる。

**Files:**
- Modify: `routes/web.php:171`（company-info の POST の直後に挿入）
- Modify: `app/Http/Controllers/Admin/SettingController.php:159`（`updateCompanyInfo()` の直後に挿入）
- Modify: `resources/views/admin/settings/_nav.blade.php:9`（会社情報の直後に挿入）
- Create: `resources/views/admin/settings/shipping.blade.php`

**Interfaces:**
- Consumes: `config('prefectures')`（Task 1）、`App\Models\Setting::getValue($key, $default)` と `Setting::updateOrCreate(['key' => ...], ['value' => ...])`（既存）
- Produces: `settings` テーブルの 2 キー
  - `shipping_threshold` — 送料設定金額。数値文字列、または未設定を表す空文字列
  - `shipping_fees` — JSON 文字列。`{"北海道":{"under":0,"over":0}, ... 47件}`。全都道府県のキーが必ず揃い、値は 0 以上の整数

- [ ] **Step 1: ルートを 2 本追加する**

`routes/web.php` の :171（`settings/company-info` の POST）と :172（`settings/invoice-line` の GET）の
あいだに、次の 2 行を挿入する。

```php
        Route::get('settings/shipping', [AdminSettingController::class, 'shipping'])->name('settings.shipping');
        Route::post('settings/shipping', [AdminSettingController::class, 'updateShipping'])->name('settings.update_shipping');
```

- [ ] **Step 2: コントローラにメソッドを追加する**

`app/Http/Controllers/Admin/SettingController.php` の `updateCompanyInfo()` の閉じ括弧（:159）の
直後、`invoiceLine()` の前に次を挿入する。

```php
    public function shipping()
    {
        $prefectures = config('prefectures');
        $threshold = Setting::getValue('shipping_threshold', '');
        $fees = $this->shippingFees($prefectures);

        return view('admin.settings.shipping', compact('prefectures', 'threshold', 'fees'));
    }

    public function updateShipping(Request $request)
    {
        $request->validate([
            'threshold' => 'nullable|integer|min:0',
            'fees.*.under' => 'nullable|integer|min:0',
            'fees.*.over' => 'nullable|integer|min:0',
        ], [
            'threshold.integer' => '送料設定金額は半角数字で入力してください。',
            'threshold.min' => '送料設定金額は0以上で入力してください。',
            'fees.*.under.integer' => '半角数字で入力してください。',
            'fees.*.under.min' => '0以上で入力してください。',
            'fees.*.over.integer' => '半角数字で入力してください。',
            'fees.*.over.min' => '0以上で入力してください。',
        ]);

        $input = (array) $request->input('fees', []);
        $fees = [];

        // 知らない都道府県が紛れ込んでも保存しない。空欄は0として扱う
        foreach (config('prefectures') as $pref) {
            $row = isset($input[$pref]) && is_array($input[$pref]) ? $input[$pref] : [];
            $fees[$pref] = [
                'under' => (int) ($row['under'] ?? 0),
                'over' => (int) ($row['over'] ?? 0),
            ];
        }

        Setting::updateOrCreate(
            ['key' => 'shipping_threshold'],
            ['value' => $request->filled('threshold') ? (string) (int) $request->input('threshold') : '']
        );
        Setting::updateOrCreate(
            ['key' => 'shipping_fees'],
            ['value' => json_encode($fees, JSON_UNESCAPED_UNICODE)]
        );

        return redirect()->route('admin.settings.shipping')->with('success', '送料設定を保存しました。');
    }

    /**
     * 保存済みの都道府県別送料を、全都道府県ぶん0埋めした配列で返す
     *
     * 未保存でもビューが全行を描けるようにするため、欠けている都道府県は0で補う。
     */
    private function shippingFees(array $prefectures)
    {
        $saved = json_decode((string) Setting::getValue('shipping_fees', ''), true);
        if (!is_array($saved)) {
            $saved = [];
        }

        $fees = [];
        foreach ($prefectures as $pref) {
            $row = isset($saved[$pref]) && is_array($saved[$pref]) ? $saved[$pref] : [];
            $fees[$pref] = [
                'under' => (int) ($row['under'] ?? 0),
                'over' => (int) ($row['over'] ?? 0),
            ];
        }

        return $fees;
    }
```

`use` 文の追加は不要。`Setting` と `Request` はファイル冒頭（:7, :12）で既に読み込まれている。

- [ ] **Step 3: 設定ナビに「送料設定」を追加する**

`resources/views/admin/settings/_nav.blade.php` の :9（会社情報）と :10（請求書LINE通知）の
あいだに次の 1 行を挿入する。

```blade
    <li><a href="{{ route('admin.settings.shipping') }}" @if($active === 'shipping') class="is-active" @endif>送料設定</a></li>
```

- [ ] **Step 4: ビューを作る**

`resources/views/admin/settings/shipping.blade.php` を新規作成する。

```blade
@extends('admin.layouts.app')

@section('title', '管理画面 [送料設定]')

@push('head')
<style>
    .shipping-fields {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }
    .shipping-threshold {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .shipping-threshold .label {
        font-weight: bold;
    }
    .shipping-fields input[type="text"] {
        width: 110px;
        padding: 6px 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 14px;
        text-align: right;
        box-sizing: border-box;
    }
    .shipping-table {
        border-collapse: collapse;
    }
    .shipping-table th {
        padding: 0 12px 8px 0;
        text-align: left;
        font-weight: bold;
        white-space: nowrap;
    }
    .shipping-table td {
        padding: 3px 12px 3px 0;
        vertical-align: top;
        white-space: nowrap;
    }
    .shipping-table td.pref {
        padding-top: 10px;
        font-weight: bold;
    }
    .shipping-unit {
        font-size: 12px;
        color: #666;
    }
    .shipping-error {
        margin: 4px 0 0;
        color: red;
        font-size: 12px;
        white-space: normal;
    }
    @media only screen and (max-width: 678px) {
        .shipping-fields input[type="text"] {
            width: 70px;
        }
        .shipping-table th,
        .shipping-table td {
            padding-right: 6px;
        }
    }
</style>
@endpush

@section('content')
<section class="lma-content flex">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>設定</h2>
        </div>
    </div>
    <div class="lma-content_block store_edit">
        @if(session('success'))
            <div class="alert alert-success" style="background: #d4edda; padding: 10px; margin-bottom: 15px; border-radius: 4px;">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('admin.settings.update_shipping') }}" method="POST">
            @csrf
            <dl class="lma-form_box">
                <dt>
                    @include('admin.settings._nav', ['active' => 'shipping'])
                </dt>
                <dd>
                    <div class="shipping-fields">
                        <div>
                            <div class="shipping-threshold">
                                <span class="label">送料設定金額</span>
                                <input type="text" name="threshold" inputmode="numeric" value="{{ old('threshold', $threshold) }}">
                                <span class="shipping-unit">円（税込）</span>
                            </div>
                            @error('threshold')
                                <p class="shipping-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <table class="shipping-table">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th colspan="2">送料設定</th>
                                    <th colspan="2">送料設定金額を超えた場合</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($prefectures as $pref)
                                    <tr>
                                        <td class="pref">{{ $pref }}</td>
                                        <td>
                                            <input type="text" name="fees[{{ $pref }}][under]" inputmode="numeric" value="{{ old('fees.' . $pref . '.under', $fees[$pref]['under']) }}">
                                            @error('fees.' . $pref . '.under')
                                                <p class="shipping-error">{{ $message }}</p>
                                            @enderror
                                        </td>
                                        <td class="pref"><span class="shipping-unit">円（税込）</span></td>
                                        <td>
                                            <input type="text" name="fees[{{ $pref }}][over]" inputmode="numeric" value="{{ old('fees.' . $pref . '.over', $fees[$pref]['over']) }}">
                                            @error('fees.' . $pref . '.over')
                                                <p class="shipping-error">{{ $message }}</p>
                                            @enderror
                                        </td>
                                        <td class="pref"><span class="shipping-unit">円（税込）</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </dd>
            </dl>

            <p class="lma-btn_box">
                <button type="submit" class="btn btn-primary">保存</button>
            </p>
        </form>
    </div>
</section>
@endsection
```

- [ ] **Step 5: ページが開くことを確認する**

```bash
curl -s -b /tmp/goon_cookie.txt -o /tmp/shipping.html -w "%{http_code}\n" http://localhost:8884/admin/settings/shipping
```

期待: `200`。`500` が返る場合は `storage/logs/laravel.log` の末尾を見る。

- [ ] **Step 6: 47 行 × 2 列の入力欄が出ていることを確認する**

```bash
grep -c 'name="fees\[' /tmp/shipping.html
grep -c 'name="fees\[[^]]*\]\[under\]"' /tmp/shipping.html
grep -o 'name="fees\[北海道\]\[under\]"\|name="fees\[沖縄県\]\[over\]"\|name="threshold"' /tmp/shipping.html
```

期待: 順に `94`、`47`、3 行（`threshold` / 北海道 under / 沖縄県 over）。

- [ ] **Step 7: 見出しとナビの選択状態を確認する**

ナビのリンクも `@if ... @endif>` の形なので、属性のあとに空白が入る。`>` を含めずに探すこと。

```bash
grep -c '送料設定金額を超えた場合' /tmp/shipping.html
grep -o 'class="is-active"' /tmp/shipping.html | wc -l
grep -o 'カスタムCSS\|プライバシーポリシー\|ご利用ガイド\|特定商取引法\|カート画面のお知らせ\|会社情報\|請求書LINE通知' /tmp/shipping.html | sort -u | wc -l
```

期待: 順に `1`、`1`（送料設定のリンクだけがアクティブ）、`7`（他の設定ページのリンクが全部残っている）。
`is-active` が付いているのが送料設定であることは、次で確認する。

```bash
grep -o 'settings/shipping"[^>]*class="is-active"' /tmp/shipping.html
```

期待: 1 行出力される。

- [ ] **Step 8: 保存できることを確認する**

フォームの CSRF トークンを取り出して POST する。

```bash
grep -o 'name="_token" value="[^"]*"' /tmp/shipping.html
```

取れた値を `<TOKEN>` に入れて実行する。

```bash
curl -s -b /tmp/goon_cookie.txt -c /tmp/goon_cookie.txt -X POST http://localhost:8884/admin/settings/shipping -d "_token=<TOKEN>" -d "threshold=20000" -d "fees[北海道][under]=1200" -d "fees[北海道][over]=0" -d "fees[東京都][under]=770" -d "fees[東京都][over]=0" -o /dev/null -w "%{http_code}\n"
```

期待: `302`。

- [ ] **Step 9: 保存された中身を確認する**

```bash
docker compose -f /Users/sawadakeisuke/workspace/Ykk08OK/docker-compose.yml exec -T php_ykk08ok php artisan tinker --execute="echo App\Models\Setting::getValue('shipping_threshold') . PHP_EOL; \$f = json_decode(App\Models\Setting::getValue('shipping_fees'), true); echo count(\$f) . PHP_EOL; echo json_encode([\$f['北海道'], \$f['東京都'], \$f['沖縄県']], JSON_UNESCAPED_UNICODE) . PHP_EOL;"
```

期待:
- `20000`
- `47`（送らなかった都道府県も 0 埋めで全件揃う）
- `[{"under":1200,"over":0},{"under":770,"over":0},{"under":0,"over":0}]`

- [ ] **Step 10: 再表示で値が保持されていることを確認する**

```bash
curl -s -b /tmp/goon_cookie.txt http://localhost:8884/admin/settings/shipping | grep -o 'name="threshold" inputmode="numeric" value="[^"]*"\|name="fees\[北海道\]\[under\]" inputmode="numeric" value="[^"]*"'
```

期待: `value="20000"` と `value="1200"` が出ること。

- [ ] **Step 11: バリデーションが効くことを確認する**

Step 8 と同じ手順でトークンを取り直し、不正な値を送る。

```bash
curl -s -b /tmp/goon_cookie.txt -c /tmp/goon_cookie.txt -X POST http://localhost:8884/admin/settings/shipping -d "_token=<TOKEN>" -d "threshold=-5" -d "fees[北海道][under]=abc" -o /dev/null -w "%{http_code}\n"
```

期待: `302`（バリデーションエラーで元の画面に戻る）。続けて画面を開き、エラーメッセージが出ていることを確認する。

```bash
curl -s -b /tmp/goon_cookie.txt http://localhost:8884/admin/settings/shipping | grep -o '送料設定金額は0以上で入力してください。\|半角数字で入力してください。'
```

期待: 両方のメッセージが出ること。さらに Step 9 のコマンドをもう一度実行し、
`shipping_threshold` が `20000` のまま（不正値で上書きされていない）ことを確認する。

- [ ] **Step 12: 他の設定ページが壊れていないことを確認する**

```bash
curl -s -b /tmp/goon_cookie.txt -o /dev/null -w "custom-css %{http_code}\n" http://localhost:8884/admin/settings/custom-css
curl -s -b /tmp/goon_cookie.txt -o /dev/null -w "company-info %{http_code}\n" http://localhost:8884/admin/settings/company-info
curl -s -b /tmp/goon_cookie.txt -o /dev/null -w "invoice-line %{http_code}\n" http://localhost:8884/admin/settings/invoice-line
```

期待: 3 つとも `200`。

- [ ] **Step 13: ブラウザで見た目を確認する**

http://localhost:8884/admin/settings/shipping を開き、次を目視する。

- 47 行が縦に並び、各行が「都道府県名・入力欄・円（税込）・入力欄・円（税込）」になっている
- 上部に「送料設定金額」の入力欄と「円（税込）」がある
- テーブルの見出しが左「送料設定」、右「送料設定金額を超えた場合」になっている
- 左のナビで「送料設定」がハイライトされている
- 保存ボタンを押すと「送料設定を保存しました。」が上部に出る
- 送料設定金額を空にして保存し、再読み込みしても空のまま（0 に変換されない）。未設定を表す状態として区別する必要があるため
- 都道府県の送料欄を空にして保存すると、再読み込みで `0` と表示される

`npm run build` は不要（Blade 内のインライン `<style>` のみで、`admin.css` を触っていないため）。

- [ ] **Step 14: コミット**

```bash
git add routes/web.php app/Http/Controllers/Admin/SettingController.php resources/views/admin/settings/_nav.blade.php resources/views/admin/settings/shipping.blade.php
git commit -m "管理画面に都道府県別の送料設定ページを追加"
```

---

## 完了後

- 本番デプロイは `main` への push で自動実行されるため、**ユーザーの確認を取るまで push しない**。
- 本計画のデプロイでは挙動は一切変わらない。保存した送料を参照する処理は後続フェーズ。
- マイグレーションを追加していないので、デプロイ時に自動実行される `php artisan migrate --force` は no-op。
- `public/build` に変更がないため FTP アップロードは不要。
