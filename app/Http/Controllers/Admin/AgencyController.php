<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AgencyController extends Controller
{
    public function create()
    {
        return view('admin.agencies.create');
    }

    /** 入力チェックのエラーを日本語で出すための項目名とメッセージ */
    private const ATTRIBUTES = [
        'agency_code' => '代理店コード',
        'name' => '名前',
        'postal_code1' => '郵便番号1（前半3桁）',
        'postal_code2' => '郵便番号2（後半4桁）',
        'address' => '住所',
        'phone' => '電話番号',
        'email' => 'メールアドレス',
        'password' => 'ログインパスワード',
        'is_test' => 'テストデータ',
    ];

    private const MESSAGES = [
        'required' => ':attributeを入力してください。',
        'max' => ':attributeは:max文字以内で入力してください。',
        'min' => ':attributeは:min文字以上で入力してください。',
        'email' => ':attributeの形式が正しくありません。',
        'unique' => 'この:attributeはすでに登録されています。',
        'postal_code1.digits' => '郵便番号1（前半）は数字3桁で入力してください。',
        'postal_code2.digits' => '郵便番号2（後半）は数字4桁で入力してください。',
    ];

    /**
     * よくある入力の揺れを整える：全角→半角、郵便番号のハイフン、
     * 郵便番号1に7桁まとめて入れた場合は前後に分ける
     */
    private function normalize(Request $request): void
    {
        $toHalf = function ($v) {
            return is_string($v) ? trim(mb_convert_kana($v, 'as')) : $v;
        };
        $p1 = preg_replace('/\D/', '', (string) $toHalf($request->input('postal_code1')));
        $p2 = preg_replace('/\D/', '', (string) $toHalf($request->input('postal_code2')));
        if (strlen($p1) === 7 && $p2 === '') {
            [$p1, $p2] = [substr($p1, 0, 3), substr($p1, 3)];
        }
        $request->merge([
            'agency_code' => $toHalf($request->input('agency_code')),
            'postal_code1' => $p1,
            'postal_code2' => $p2,
            'phone' => $toHalf($request->input('phone')),
            'email' => $toHalf($request->input('email')),
        ]);
    }

    public function store(Request $request)
    {
        $this->normalize($request);
        $request->validate([
            'agency_code' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'postal_code1' => 'required|digits:3',
            'postal_code2' => 'required|digits:4',
            'address' => 'required|string|max:255',
            'phone' => 'required|string|max:15',
            'email' => 'required|email|max:255|unique:agencies,email',
            'password' => 'required|string|min:8',
        ], self::MESSAGES, self::ATTRIBUTES);

        Agency::create([
            'agency_code' => $request->agency_code,
            'name' => $request->name,
            'postal_code1' => $request->postal_code1,
            'postal_code2' => $request->postal_code2,
            'address' => $request->address,
            'phone' => $request->phone,
            'email' => $request->email,
            'contact_person' => '',
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('admin.agencies.index')->with('success', '代理店を登録しました。');
    }

    public function index()
    {
        $agencies = Agency::get(); // ページネーションで10件ずつ表示
        return view('admin.agencies.index', compact('agencies'));
    }

    public function show($id)
    {
        $agency = Agency::findOrFail($id);
        return view('admin.agencies.show', compact('agency'));
    }

    public function editPassword(Agency $agency)
    {
        return view('admin.agencies.edit-password', compact('agency'));
    }

    /**
     * パスワードを更新
     */
    public function updatePassword(Request $request, Agency $agency)
    {
        $request->validate([
            'password' => 'required|min:8',
        ], self::MESSAGES, self::ATTRIBUTES);

        $agency->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('admin.agencies.edit-password', $agency)
            ->with('success', 'パスワードを更新しました。');
    }

    public function edit($id)
    {
        $agency = Agency::findOrFail($id);
        return view('admin.agencies.edit', compact('agency'));
    }

    public function update(Request $request, $id)
    {
        $this->normalize($request);
        $request->validate([
            'agency_code' => 'required|string|max:255',
            'is_test' => 'required|boolean',
            'name' => 'required|string|max:255',
            'postal_code1' => 'required|digits:3',
            'postal_code2' => 'required|digits:4',
            'address' => 'required|string|max:255',
            'phone' => 'required|string|max:15',
            'email' => 'required|email|max:255|unique:agencies,email,' . $id,
            'password' => 'nullable|string|min:8', // パスワードはオプション
        ], self::MESSAGES, self::ATTRIBUTES);

        $agency = Agency::findOrFail($id);

        $agency->update([
            'agency_code' => $request->agency_code,
            'is_test' => $request->boolean('is_test'),
            'name' => $request->name,
            'postal_code1' => $request->postal_code1,
            'postal_code2' => $request->postal_code2,
            'address' => $request->address,
            'phone' => $request->phone,
            'contact_person' => '',
            'email' => $request->email,
            'password' => $request->password ? Hash::make($request->password) : $agency->password,
        ]);

        return redirect()->route('admin.agencies.index')->with('success', '代理店情報を更新しました。');
    }

    public function destroy($id)
    {
        $agency = Agency::findOrFail($id);
        $agency->delete();
        return redirect()->route('admin.agencies.index')->with('success', '代理店を削除しました。');
    }
}