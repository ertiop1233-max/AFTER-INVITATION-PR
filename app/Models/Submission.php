<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Submission extends Model
{
    protected $fillable = [
        'event_id',
        'upload_session_key',
        'contributor_name',
        'written_message',
        'voice_storage_id',
        'voice_storage_path',
        'voice_duration_seconds',
        'voice_size_bytes',
        'status',
        'storage_folder_id',
        'total_photos',
        'total_videos',
        'total_size_bytes',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'voice_duration_seconds' => 'integer',
        'voice_size_bytes' => 'integer',
        'total_photos' => 'integer',
        'total_videos' => 'integer',
        'total_size_bytes' => 'integer',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_COMPLETED = 'completed';

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function hasVoice(): bool
    {
        return $this->voice_storage_id !== null;
    }

    public function hasMessage(): bool
    {
        return $this->written_message !== null && trim($this->written_message) !== '';
    }
}
