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

    public function store(Request $request)
    {
        $request->validate([
            'agency_code' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'postal_code1' => 'required|string|max:3',
            'postal_code2' => 'required|string|max:4',
            'phone' => 'required|string|max:15',
            'email' => 'required|email|unique:agencies,email',
            'password' => 'required|string|min:8',
        ]);
        

        Agency::create([
            'agency_code' => $request->agency_code,
            'name' => $request->name,
            'postal_code1' => $request->postal_code1,
            'postal_code2' => $request->postal_code2,
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
        ]);

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
        $request->validate([
            'agency_code' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'postal_code1' => 'required|string|max:3',
            'postal_code2' => 'required|string|max:4',
            'address' => 'required|string',
            'phone' => 'required|string|max:15',
            'email' => 'required|email|unique:agencies,email,' . $id,
            'password' => 'nullable|string|min:8', // パスワードはオプション
        ]);

        $agency = Agency::findOrFail($id);

        $agency->update([
            'agency_code' => $request->agency_code,
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