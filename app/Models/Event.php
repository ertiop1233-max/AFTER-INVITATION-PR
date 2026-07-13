<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Event extends Model
{
    protected $fillable = [
        'title',
        'description',
        'event_type',
        'status',
        'upload_deadline',
        'max_submission_size_bytes',
        'allow_photos',
        'allow_videos',
        'allow_voice',
        'allow_messages',
        'upload_token',
        'upload_slug',
        'storage_root_folder_id',
        'total_submissions',
        'total_photos',
        'total_videos',
        'total_voice_recordings',
        'total_messages',
        'total_storage_bytes',
    ];

    protected $casts = [
        'upload_deadline' => 'datetime',
        'allow_photos' => 'boolean',
        'allow_videos' => 'boolean',
        'allow_voice' => 'boolean',
        'allow_messages' => 'boolean',
        'max_submission_size_bytes' => 'integer',
        'total_submissions' => 'integer',
        'total_photos' => 'integer',
        'total_videos' => 'integer',
        'total_voice_recordings' => 'integer',
        'total_messages' => 'integer',
        'total_storage_bytes' => 'integer',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CLOSED = 'closed';

    public function client(): HasOne
    {
        return $this->hasOne(Client::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isUploadDeadlinePassed(): bool
    {
        return $this->upload_deadline !== null && now()->gt($this->upload_deadline);
    }

    public static function generateUploadToken(): string
    {
        return Str::random(64);
    }

    public static function generateUploadSlug(string $title): string
    {
        return Str::slug($title) . '-' . Str::random(8);
    }}
