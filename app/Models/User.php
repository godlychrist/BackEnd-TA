<?php

namespace App\Models;

use Jenssegers\Mongodb\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'users';

    protected $fillable = [
        'cedula',
        'full_name', // Identidad Legal del Padrón
        'username',
        'password',
        'email',
        'phone', // REQUERIMIENTO: Número de teléfono para 2FA
        'status',
        'verification_token',
        'two_factor_code', // Código temporal para SMS
        'phone_verified', // Estado de verificación de SMS (Una sola vez)
    ];

    public $timestamps = false;

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_code',
    ];

    public function getJWTIdentifier()
    {
        return (string) $this->_id;
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}