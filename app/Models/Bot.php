<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bot extends Model
{
    protected $fillable = [
        'type',
        'user_id',
        'name',
        'config'
    ];

    const TYPE = [
        'URL' => 1,
        'PING' => 2,
        'TIMER' => 3
    ];

    protected $casts = [
        'config' => 'json'
    ];

    public function subscribes()
    {
        return $this->hasMany(BotSubscribe::class);
    }

    public function getConfigAttribute()
    {
        return json_decode($this->attributes['config']);
    }
}
