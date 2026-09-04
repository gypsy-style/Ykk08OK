# 加盟店のふりがな欄追加 設計

## 背景と目的

`merchants` テーブルにはサロン名（`name`）はあるが読み仮名を持つカラムがない。
最終的には加盟店一覧を五十音順に並べ替えたいが、並び替えはデータが埋まっていないと
実用にならないため、本作業では入力欄とカラムの用意までを行う。

## スコープ

対象は本部の管理画面と代理店画面の、加盟店の新規登録・編集の計4画面。

含むもの:

- `merchants.name_kana` カラムの追加
- 管理画面 create / edit への入力欄追加
- 代理店画面 create / edit への入力欄追加
- 上記4経路のバリデーション

含まないもの:

- 加盟店本人の LIFF 画面（`resources/views/merchants/`）への入力欄追加
- 一覧のふりがな順ソート
- 一覧・詳細へのふりがな表示

## 設計

### データベース

マイグレーションで `merchants` に以下を追加する。

| カラム | 型 | NULL | 位置 |
| --- | --- | --- | --- |
| `name_kana` | `string(255)` | 可 | `name` の直後 |

既存レコードは NULL のままとする。デプロイ時に CI が `php artisan migrate --force` を
実行するため、追加の手作業は不要。

### モデル

`App\Models\Merchant` の `$fillable` に `name_kana` を追加する。
保存処理が `Merchant::create($request->all())` および `$merchant->update($request->all())`
であるため、`$fillable` に入れないと値が保存されない。

### バリデーション

以下の4メソッドに同一のルールを追加する。

- `App\Http\Controllers\Admin\MerchantController::store`
- `App\Http\Controllers\Admin\MerchantController::update`
- `App\Http\Controllers\Agency\MerchantController::store`
- `App\Http\Controllers\Agency\MerchantController::update`

```php
'name_kana' => ['nullable', 'string', 'max:255', 'regex:/\A[ぁ-ゖ]/u'],
```

任意入力とする。既存の加盟店はふりがなが空であり、必須にすると他の項目だけを
修正したいときにも入力を強制されるため。

制約は先頭1文字がひらがなであることだけとし、2文字目以降は問わない。
用途が五十音順の並べ替えである以上、判定が必要なのは先頭だけであり、
全体をひらがなに限ると「さろん・ど・ぼーて」のような中黒や英数字を含む
自然な表記まで弾いてしまうため。

`ぁ-ゖ` は U+3041〜U+3096 で、`ゔ` を含むひらがな全体を指す。

エラーメッセージは各 `validate()` の第2引数で指定する。

```php
['name_kana.regex' => 'ふりがなはひらがなで始めてください。']
```

### 画面

対象は次の4ファイル。いずれも「サロン名」の `<dd>` の直後に挿入する。

- `resources/views/admin/merchants/create.blade.php`
- `resources/views/admin/merchants/edit.blade.php`
- `resources/views/agencies/merchants/create.blade.php`
- `resources/views/agencies/merchants/edit.blade.php`

新規登録画面:

```blade
<dt><label for="name_kana">ふりがな</label></dt>
<dd><input type="text" class="form-control" id="name_kana" name="name_kana" value="{{ old('name_kana') }}" placeholder="ひらがなで入力"></dd>
```

編集画面は `value` を `{{ old('name_kana', $merchant->name_kana) }}` とする。

`required` は付けない。周囲の既存要素の記法にそろえる。

## 影響範囲

加盟店本人の LIFF 編集画面には入力欄を置かないため `name_kana` は送信されない。
`$fillable` 経由の更新は送信されたキーのみを対象とするので、加盟店が自分で情報を
更新してもふりがなは消えない。

## 完了条件

- 管理画面・代理店画面の登録／編集の4経路でふりがなを保存・再表示できる
- 先頭がひらがなでない値を入力するとエラーメッセージが表示され、保存されない
- 空欄のまま保存できる
- 加盟店本人の LIFF 編集で情報を更新してもふりがなが保持される
