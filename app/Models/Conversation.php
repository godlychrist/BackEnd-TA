<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class Conversation extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'conversations';
    public $timestamps = false;


    use HasFactory;

    protected $fillable = [
        'buyer_id',
        'seller_id',
        'vehicle_id',
        'last_message',
        'last_message_at'
    ];

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }
}