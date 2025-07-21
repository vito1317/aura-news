<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

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
} 