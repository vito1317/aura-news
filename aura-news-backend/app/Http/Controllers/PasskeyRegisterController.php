<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;
use Spatie\LaravelPasskeys\Models\Passkey;

class PasskeyRegisterController extends Controller
{
    // 產生註冊 challenge
    public function options(Request $request)
    {
        $user = User::where('email', $request->input('email'))->first();
        if ($user) {
            // 已有帳號，禁止直接註冊 Passkey
            return response()->json([
                'message' => '此帳號已註冊，請登入後至用戶設定綁定 Passkey'
            ], 409);
        }
        // 沒有帳號才建立新 user
        $user = User::create([
            'name' => $request->input('name', $request->input('email')),
            'email' => $request->input('email'),
            'password' => '',
        ]);
        // 檢查是否已註冊過 Passkey
        $hasPasskey = Passkey::where('authenticatable_id', $user->id)->exists();
        if ($hasPasskey) {
            return response()->json([
                'message' => '此帳號已註冊過 Passkey，請至用戶設定綁定新 Passkey'
            ], 409);
        }

        $options = app(GeneratePasskeyRegisterOptionsAction::class)->execute($user, false);

        // 強制轉成陣列
        if (is_object($options)) {
            $optionsArr = json_decode(json_encode($options), true);
        } elseif (is_string($options)) {
            $optionsArr = json_decode($options, true);
        } else {
            $optionsArr = $options;
        }

        $optionsArr['pubKeyCredParams'] = [
            ['type' => 'public-key', 'alg' => -7],
            ['type' => 'public-key', 'alg' => -257],
        ];

        // 產生 16 bytes 隨機 user id（base64url 編碼）
        function base64url_encode($data) {
            return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        }
        $optionsArr['user']['id'] = base64url_encode(random_bytes(16));

        session(['passkey_register_user_id' => $user->id]);
        session(['passkey_register_options' => json_encode($optionsArr)]);

        return response()->json($optionsArr);
    }

    // 驗證註冊 credential
    public function verify(Request $request)
    {
        $userId = session('passkey_register_user_id');
        $optionsJson = session('passkey_register_options');
        $user = User::findOrFail($userId);
        // 檢查是否已註冊過 Passkey
        $hasPasskey = Passkey::where('authenticatable_id', $user->id)->exists();
        if ($hasPasskey) {
            return response()->json([
                'message' => '此帳號已註冊過 Passkey，請至用戶設定綁定新 Passkey'
            ], 409);
        }

        $credentialJson = json_encode($request->input('credential'));
        $email = $request->input('email', $user->email); // 優先用 request 的 email

        // 先用 action 建立 passkey
        $passkey = app(StorePasskeyAction::class)->execute(
            $user,
            $credentialJson,
            $optionsJson,
            parse_url(config('app.url'), PHP_URL_HOST)
        );
        // 再補寫 email 欄位
        if ($passkey) {
            $passkey->email = $email;
            $passkey->save();
        }

        return response()->json(['success' => true]);
    }
}
