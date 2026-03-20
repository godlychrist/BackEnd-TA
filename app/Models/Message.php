<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class Message extends Model
{

    protected $connection = 'mongodb';
    protected $collection = 'messages';
    public $timestamps = false;
    
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'message',
        'created_at',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

}