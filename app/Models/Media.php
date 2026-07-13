<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    protected $fillable = [
        'submission_id',
        'event_id',
        'media_type',
        'original_filename',
        'stored_filename',
        'mime_type',
        'extension',
        'file_size_bytes',
        'width',
        'height',
        'duration_seconds',
        'storage_id',
        'storage_path',
        'thumbnail_storage_id',
        'thumbnail_path',
        'status',
        'uploaded_at',
        'resumable_uri',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'duration_seconds' => 'integer',
        'uploaded_at' => 'datetime',
    ];

    public const STATUS_UPLOADING = 'uploading';
    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_FAILED = 'failed';

    public const TYPE_PHOTO = 'photo';
    public const TYPE_VIDEO = 'video';

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function isUploaded(): bool
    {
        return $this->status === self::STATUS_UPLOADED;
    }

    public function isPhoto(): bool
    {
        return $this->media_type === self::TYPE_PHOTO;
    }

    public function isVideo(): bool
    {
        return $this->media_type === self::TYPE_VIDEO;
    }
}
