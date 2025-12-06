<?php
namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('agencies.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::guard('agencies')->attempt($credentials)) {
            $request->session()->regenerate();
            return redirect()->intended(route('agencies.orders.index'));
        }

        return back()->withErrors([
            'email' => 'ログインに失敗しました。',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('agencies')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect(route('agencies.login'));
    }
}