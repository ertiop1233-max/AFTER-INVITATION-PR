<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Client extends Model
{
    protected $attributes = [
        'auth_version' => 1,
    ];

    protected $fillable = [
        'event_id',
        'name',
        'email',
        'password_encrypted',
        'auth_version',
    ];

    protected $casts = [
        'auth_version' => 'integer',
    ];

    protected $hidden = ['password_encrypted'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
