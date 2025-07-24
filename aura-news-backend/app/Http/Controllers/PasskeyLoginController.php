<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyAuthenticationOptionsAction;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction;
use Spatie\LaravelPasskeys\Models\Passkey;

class PasskeyLoginController extends Controller
{
    // 產生登入 challenge
    public function options(Request $request)
    {
        // 不指定 user，讓瀏覽器列出所有 credential
        $options = [
            'challenge' => bin2hex(random_bytes(32)),
            'timeout' => 60000,
            'rpId' => config('passkeys.relying_party.id'),
            'allowCredentials' => [], // 空陣列
            'userVerification' => 'preferred',
        ];
        session(['passkey_login_options' => json_encode($options)]);
        return response()->json($options);
    }

    // 驗證登入 assertion
    public function verify(Request $request)
    {
        $optionsJson = session('passkey_login_options');
        $credential = $request->input('credential');
        // 支援 publicKeyCredentialId
        $credentialId = $credential['publicKeyCredentialId'] ?? $credential['id'] ?? null;

        // 查找 passkey（從 data 欄位的 publicKeyCredentialId）
        $passkey = Passkey::whereRaw(
            "JSON_UNQUOTE(JSON_EXTRACT(data, '$.publicKeyCredentialId')) = ?",
            [$credentialId]
        )->first();
        if (!$passkey) {
            return response()->json(['error' => '找不到對應的 Passkey'], 404);
        }
        $user = $passkey->authenticatable; // 關聯 user
        if (!$user) {
            return response()->json(['error' => '找不到對應的 User'], 404);
        }

        // 驗證 assertion
        $passkeyModel = app(\Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction::class)
            ->execute(json_encode($credential), $optionsJson);

        if ($passkeyModel) {
            $token = $user->createToken('passkey-login')->plainTextToken;
            return response()->json(['token' => $token, 'user' => $user]);
        } else {
            return response()->json(['error' => 'Invalid credential'], 401);
        }
    }
}
