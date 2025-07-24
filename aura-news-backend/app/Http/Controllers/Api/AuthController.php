<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($request->only('email', 'password'))) {
            $user = Auth::user();
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'user' => $user,
                'token' => $token,
                'message' => '登入成功'
            ]);
        }

        throw ValidationException::withMessages([
            'email' => ['提供的憑證不正確。'],
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }
        return response()->json(['message' => '已成功登出']);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    public function register(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'nickname' => 'required|string|max:40',
        ]);
        $user = User::create([
            'name' => $request->nickname,
            'nickname' => $request->nickname,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user',
        ]);
        $token = $user->createToken('auth-token')->plainTextToken;
        return response()->json([
            'user' => $user,
            'token' => $token,
            'message' => '註冊成功'
        ], 201);
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'nickname' => 'required|string|max:40',
        ]);
        $user = $request->user();
        $user->nickname = $request->nickname;
        $user->name = $request->nickname;
        $user->save();
        return response()->json(['message' => '暱稱已更新', 'user' => $user]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string|min:6',
        ]);
        $user = $request->user();
        $user->password = Hash::make($request->password);
        $user->save();
        return response()->json(['message' => '密碼已更新']);
    }

    // Google OAuth 登入：取得 Google 授權網址
    public function redirectToGoogle()
    {
        return response()->json([
            'url' => Socialite::driver('google')->stateless()->redirect()->getTargetUrl()
        ]);
    }

    // Google OAuth callback：處理 Google 回傳
    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Google 認證失敗', 'error' => $e->getMessage()], 401);
        }

        $user = User::where('email', $googleUser->getEmail())->first();
        if (!$user) {
            // 建立新用戶
            $user = User::create([
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'GoogleUser',
                'nickname' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'GoogleUser',
                'email' => $googleUser->getEmail(),
                'password' => bcrypt(Str::random(16)),
                'role' => 'user',
                // 你可以根據 User model 結構補 avatar 等欄位
            ]);
        }
        // 登入並產生 Sanctum token
        $token = $user->createToken('auth-token')->plainTextToken;
        return response()->json([
            'user' => $user,
            'token' => $token,
            'message' => 'Google 登入成功'
        ]);
    }
} 