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
        if($response->failed()) {
            return response()->json(['error' => 'La cedula no existe en el padrón'], 422);
        }

        $datosUsuario = $response->json();

        try {
            // FUSION: Cédula y Nombre de Cris + Email y Activación de Brian
            $user = User::create([
                'cedula' => $request->cedula,
                'username' => $request->username, // O $datosUsuario['nombre'] si prefieren el legal
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
}
