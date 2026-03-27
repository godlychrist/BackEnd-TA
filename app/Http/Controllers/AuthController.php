<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB; // Usaremos DB directamente
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
        // 1. Validamos los datos (Integrando Cédula de Cris + Email de Brian)
        $validator = Validator::make($request->all(), [
            'cedula' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6'
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
            // FUSION: Cédula y Nombre de Cris + Email y Activación de Brian
            $user = User::create([
                'cedula' => $request->cedula,
                'full_name' => $datosUsuario['nombre'], // IDENTIDAD LEGAL DEL PADRÓN ✅
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'status' => 'pending',
                'verification_token' => Str::random(64)
            ]);

            // TU PARTE: Enviar correo real
            Mail::to($user->email)->send(new VerifyUserAccount($user));

            return response()->json([
                'message' => '¡Usuario registrado! Revisa tu correo real para activar tu cuenta.',
                'user_id' => $user->_id
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar usuario integrado',
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

            // 4. Generamos el token JWT
            $token = JWTAuth::fromUser($user);

            return response()->json([
                'message' => 'Login exitoso',
                'token' => $token,
                'user' => [
                    'username' => $user->username,
                    'id' => (string) $user->_id
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error crítico en el login',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint para activar la cuenta mediante el token del correo
     */
    public function verifyEmail(Request $request)
    {
        $token = $request->query('token');

        if (!$token) {
            return response()->json(['message' => 'Token de verificación faltante'], 400);
        }

        $user = User::where('verification_token', $token)->first();

        if (!$user) {
            return response()->json(['message' => 'El enlace ya no es válido o ha expirado'], 404);
        }

        // ACTIVAMOS LA CUENTA
        $user->status = 'active';
        $user->verification_token = null; // Limpiamos el token por seguridad
        $user->save();

        return response()->json([
            'message' => '¡Cuenta activada con éxito! Ya puedes iniciar sesión.',
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
