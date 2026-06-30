<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\OtpCode;
use App\Models\LoginHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class AuthController extends Controller
{
    // Étape 1 : Login
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            if ($user) {
                LoginHistory::create([
                    'user_id'    => $user->id,
                    'ip_address' => $request->ip(),
                    'status'     => 'failed',
                ]);
            }
            return response()->json(['message' => 'Identifiants incorrects'], 401);
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        OtpCode::where('user_id', $user->id)->delete();

        OtpCode::create([
            'user_id'    => $user->id,
            'code'       => $code,
            'expires_at' => Carbon::now()->addMinutes(10),
            'used'       => false,
        ]);

        Mail::raw("Votre code de vérification est : $code", function ($message) use ($user) {
            $message->to($user->email)->subject('Code de vérification MFA');
        });

        return response()->json([
            'message' => 'Code envoyé par mail',
            'user_id' => $user->id,
        ]);
    }

    // Inscription
    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string',
            'prenom'   => 'required|string',
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:6',
        ]);

        $user = User::create([
            'name'     => $request->name . ' ' . $request->prenom,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Compte créé avec succès',
            'user'    => $user,
        ], 201);
    }

    // Étape 2 : Vérifier OTP
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'code'    => 'required',
        ]);

        $otp = OtpCode::where('user_id', $request->user_id)
            ->where('code', $request->code)
            ->where('used', false)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$otp) {
            return response()->json(['message' => 'Code invalide ou expiré'], 401);
        }

        $otp->update(['used' => true]);

        $user = User::find($request->user_id);

        LoginHistory::create([
            'user_id'    => $user->id,
            'ip_address' => $request->ip(),
            'status'     => 'success',
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie',
            'token'   => $token,
            'user'    => $user,
        ]);
    }

    // Renvoyer OTP
    public function resendOtp(Request $request)
    {
        $request->validate(['user_id' => 'required']);

        $user = User::find($request->user_id);

        if (!$user) {
            return response()->json(['message' => 'Utilisateur non trouvé'], 404);
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        OtpCode::where('user_id', $user->id)->delete();

        OtpCode::create([
            'user_id'    => $user->id,
            'code'       => $code,
            'expires_at' => Carbon::now()->addMinutes(10),
            'used'       => false,
        ]);

        Mail::raw("Votre code de vérification est : $code", function ($message) use ($user) {
            $message->to($user->email)->subject('Code de vérification MFA');
        });

        return response()->json(['message' => 'Code renvoyé avec succès']);
    }

    // Historique des connexions
    public function history(Request $request)
    {
        $history = LoginHistory::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($history);
    }

    // Déconnexion
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Déconnecté']);
    }
}