<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class Vehicle extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'vehicles';

    protected $fillable = [
        'brand',
        'model',
        'year',
        'price',
        'status',
        'image',
        'user_id'
    ];

    protected $casts = [
        'price' => 'float',
        'year' => 'integer',
        'user_id' => 'string'
    ];

    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
