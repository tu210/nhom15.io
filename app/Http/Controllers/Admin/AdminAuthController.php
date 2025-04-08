<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthController extends Controller
{
    public function ShowLoginForm()
    {
        if (Auth::check() && Auth::user()->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {

        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');
        // Log::info('Admin login attempt', ['credentials' => $credentials]);
        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            // Log::info('Admin login attempt', ['user' => $user]);
            if ($user->role === 'admin') {
                return redirect()->route('admin.dashboard')->with('success', 'Dang Nhap Thanh Cong ');
            } else {
                Auth::logout();
                return redirect()->route('admin.login')->withErrors(['email' => 'Bạn không có quyền truy cập admin']);
            }
        }
        return redirect()->route('admin.login')->withErrors(['email' => 'Email hoặc mật khẩu không đúng']);
    }

 
