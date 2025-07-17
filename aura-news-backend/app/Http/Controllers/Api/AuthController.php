<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

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
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => '已成功登出'
        ]);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }
} 