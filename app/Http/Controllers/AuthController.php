<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->merge(['phone' => self::phone($request->input('phone'))]);
        $data = $request->validate([
            'phone' => ['required', 'regex:/^09\d{9}$/', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'phone.regex' => 'شماره‌ی موبایل باید ۱۱ رقم و با ۰۹ شروع شود.',
            'phone.unique' => 'این شماره قبلاً ثبت‌نام کرده است.',
            'password.min' => 'رمز عبور حداقل ۸ کاراکتر باشد.',
            'password.confirmed' => 'تکرار رمز عبور یکسان نیست.',
        ]);

        $user = User::create($data);
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('ok', 'خوش آمدید! حالا حساب تلگرام خود را وصل کنید.');
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $phone = self::phone($request->input('phone'));
        $request->validate(['phone' => 'required', 'password' => 'required']);

        $key = 'login:'.$phone.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'phone' => 'تلاش‌های ناموفق زیاد بود؛ '.RateLimiter::availableIn($key).' ثانیه‌ی دیگر دوباره تلاش کنید.',
            ]);
        }
        if (! Auth::attempt(['phone' => $phone, 'password' => $request->input('password')], $request->boolean('remember'))) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages(['phone' => 'شماره یا رمز عبور اشتباه است.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /** تبدیل ارقام فارسی و حذف فاصله‌ها */
    public static function phone(?string $v): string
    {
        $v = strtr((string) $v, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']);
        $v = preg_replace('/[^\d+]/', '', $v);
        if (str_starts_with($v, '+98')) {
            $v = '0'.substr($v, 3);
        }

        return $v;
    }
}
