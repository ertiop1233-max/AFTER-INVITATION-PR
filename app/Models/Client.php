<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Client extends Model
{
    protected $fillable = [
        'event_id',
        'name',
        'email',
        'password_encrypted',
    ];

    protected $hidden = ['password_encrypted'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
