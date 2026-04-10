<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controller as BaseController;
use Tymon\JWTAuth\Facades\JWTAuth;

use App\Mail\VerifyUserAccount;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends BaseController
{
    public function register(Request $request)
    {
        // 1. Validamos los datos (Integrando Cédula + Email)
        $validator = Validator::make($request->all(), [
            'cedula' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'phone' => $request->is_google ? 'nullable|string|max:20' : 'required|string|max:20',
            'password' => $request->is_google ? 'nullable' : 'required|string|min:6'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // CODIGO DE CRIS: Validar con API de Identidad
        $response = Http::get("http://localhost:3000/api/user/{$request->cedula}");
        if ($response->failed()) {
            return response()->json(['error' => 'La cedula no existe en el padrón'], 422);
        }

        $datosUsuario = $response->json();

        try {
            $username = $request->username;

            // Si es Google y el username ya existe, le agregamos algo aleatorio para no fallar
            if ($request->is_google) {
                while (User::where('username', $username)->exists()) {
                    $username = $request->username . rand(10, 99);
                }
            }

            // FUSION FINAL:
            // Google -> Nace 'active', sin correo.
            // Manual -> Nace 'pending', requiere correo de activación.
            $user = User::create([
                'cedula' => $request->cedula,
                'full_name' => $datosUsuario['nombre'], // IDENTIDAD LEGAL DEL PADRÓN
                'username' => $username,
                'email' => $request->email,
                'phone' => $request->phone ?? '',
                'password' => Hash::make($request->password ?? Str::random(16)),
                'status' => $request->is_google ? 'active' : 'pending',
                'verification_token' => $request->is_google ? null : Str::random(64)
            ]);

            // Solo enviamos correo si NO es Google
            if (!$request->is_google) {
                Mail::to($user->email)->send(new VerifyUserAccount($user));
            }

            // REQUERIMIENTO: Si es Google, loguear de un solo
            $jwtToken = $request->is_google ? JWTAuth::fromUser($user) : null;

            return response()->json([
                'message' => $request->is_google
                    ? '¡Bienvenido/a a TicoAutos!'
                    : '¡Registro exitoso! Revisa tu correo para activar tu cuenta.',
                'token' => $jwtToken, // Regresamos token si es Google
                'user' => $request->is_google ? [
                    'username' => $user->username,
                    'id' => (string) $user->_id
                ] : null,
                'user_id' => $user->_id
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $credentials = $request->only('username', 'password');

        try {
            // 1. Buscamos al usuario
            $user = User::where('username', $credentials['username'])->first();

            // 2. Verificamos credenciales
            if (!$user || !Hash::check($credentials['password'], $user->password)) {
                return response()->json(['error' => 'Credenciales inválidas'], 401);
            }

            // 3. REQUERIMIENTO: Validar que la cuenta esté activa
            if ($user->status !== 'active') {
                return response()->json([
                    'error' => 'Cuenta pendiente de activación',
                    'message' => 'Por favor, revisa tu correo electrónico para activar tu cuenta.'
                ], 403);
            }

            // ---  IMPLEMENTACIÓN 2FA REAL (Twilio) ---

            // 1. Generamos código de 6 dígitos
            $code = rand(100000, 999999);
            $user->two_factor_code = (string) $code;
            $user->save();

            // 2. Enviamos el SMS REAL mediante Twilio
            try {
                $sid = env('TWILIO_SID');
                $token = env('TWILIO_AUTH_TOKEN');
                $serviceSid = env('TWILIO_SERVICE_SID');

                $response = Http::withBasicAuth($sid, $token)
                    ->asForm()
                    ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                        'To' => $user->phone,
                        'MessagingServiceSid' => $serviceSid,
                        'Body' => "Tu código de seguridad para TicoAutos es: {$code}. No lo compartas con nadie."
                    ]);

                if ($response->failed()) {
                    \Log::error('Error de Twilio', ['response' => $response->json()]);
                    // Opcional: podrías retornar el código en el log si falla el envío real para no trabar el desarrollo
                }

            } catch (\Exception $e) {
                \Log::error('Fallo crítico enviando SMS', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'requires_2fa' => true,
                'message' => "Código de seguridad enviado a tu teléfono finalizado en " . substr($user->phone, -4),
                'username' => $user->username
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error crítico en el login',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Paso 2 del Login: Verificar el código 2FA y dar el Token JWT.
     */
    public function verify2FA(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'code' => 'required|string|size:6'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('username', $request->username)
            ->where('two_factor_code', $request->code)
            ->first();

        if (!$user) {
            return response()->json(['error' => 'Código de verificación incorrecto'], 401);
        }

        // 1. Código correcto, limpiamos para el futuro
        $user->two_factor_code = null;
        $user->save();

        // 2. Generamos el token JWT final
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'Login exitoso (2FA validado)',
            'token' => $token,
            'user' => [
                'username' => $user->username,
                'id' => (string) $user->_id
            ]
        ]);
    }

    /**
     * Endpoint para activar la cuenta mediante el token del correo
     */
    public function verifyEmail(Request $request)
    {
        $token = $request->query('email_token');

        if (!$token) {
            return response()->json(['message' => 'Token de verificación faltante'], 400);
        }

        $user = User::where('verification_token', $token)->first();

        if (!$user) {
            return response()->json(['message' => 'El enlace ya no es válido o ha expirado'], 404);
        }

        // ACTIVAMOS LA CUENTA
        $user->status = 'active';
        $user->verification_token = null; 
        $user->save();

        // --- ENVIAR SMS POST-ACTIVACIÓN (Usando lógica funcional del Login) ---
        $code = rand(100000, 999999);
        $user->two_factor_code = (string) $code;
        $user->save();

        try {
            $sid = env('TWILIO_SID');
            $token = env('TWILIO_AUTH_TOKEN');
            $serviceSid = env('TWILIO_SERVICE_SID');

            Http::withBasicAuth($sid, $token)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'To' => $user->phone,
                    'MessagingServiceSid' => $serviceSid,
                    'Body' => "TicoAutos: Tu cuenta ha sido activada. Tu código de acceso final es: {$code}"
                ]);
        } catch (\Exception $e) {
            \Log::error('Fallo enviando SMS en activación', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'message' => '¡Correo verificado! Te hemos enviado un código SMS para finalizar la seguridad de tu cuenta.',
            'requires_2fa' => true,
            'user_id' => (string) $user->_id,
            'username' => $user->username
        ], 200);
    }

    /**
     * Paso 1: Generar el URL de Google
     */
    public function redirectToGoogle()
    {
        $query = http_build_query([
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'redirect_uri' => env('GOOGLE_REDIRECT_URL'),
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'prompt' => 'select_account',
        ]);

        return response()->json([
            'url' => "https://accounts.google.com/o/oauth2/v2/auth?{$query}"
        ]);
    }

    /**
     * Paso 2: Recibir la respuesta de Google
     */
    public function handleGoogleCallback(Request $request)
    {
        $code = $request->query('code');

        if (!$code) {
            return redirect(env('FRONTEND_URL') . '/login?error=no_code');
        }

        // 1. Intercambiamos el código por un token de acceso
        $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'redirect_uri' => env('GOOGLE_REDIRECT_URL'),
            'code' => $code,
            'grant_type' => 'authorization_code',
        ]);

        if ($tokenResponse->failed()) {
            return redirect(env('FRONTEND_URL') . '/login?error=token_failed');
        }

        $accessToken = $tokenResponse->json()['access_token'];

        // 2. Obtenemos la información real del usuario
        $userResponse = Http::withToken($accessToken)->get('https://www.googleapis.com/oauth2/v3/userinfo');

        if ($userResponse->failed()) {
            return redirect(env('FRONTEND_URL') . '/login?error=user_info_failed');
        }

        $googleUser = $userResponse->json();
        $email = $googleUser['email'];
        $name = $googleUser['name'];

        // 3. Verificamos si ya existe alguien con ese email
        $user = User::where('email', $email)->first();

        if ($user) {
            // El usuario ya existe, lo logueamos directamente (¡Súper Rápido!)
            $token = JWTAuth::fromUser($user);
            return redirect(env('FRONTEND_URL') . "/login?token={$token}&username={$user->username}&id={$user->_id}");
        } else {
            // REQUERIMIENTO: Es usuario nuevo, debe dar la CÉDULA.
            // Lo enviamos al registro con los datos pre-llenados.
            $params = http_build_query([
                'google_email' => $email,
                'google_name' => $name,
                'is_google' => 'true'
            ]);
            return redirect(env('FRONTEND_URL') . "/login?{$params}");
        }
    }

    /**
     * Autocompletar: Consultar cédula sin registrarse
     */
    public function checkCedula($cedula)
    {
        try {
            $response = Http::get("http://localhost:3000/api/user/{$cedula}");

            if ($response->failed()) {
                return response()->json(['error' => 'Cédula no encontrada'], 404);
            }

            return response()->json($response->json());
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al conectar con el Padrón'], 500);
        }
    }
}
