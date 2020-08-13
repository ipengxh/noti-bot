<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotSubscribe extends Model
{
    protected $fillable = [
        'bot_id',
        'to',
    ];

    public function bot()
    {
        return $this->belongsTo(Bot::class);
    }
}
