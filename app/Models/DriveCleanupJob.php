<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriveCleanupJob extends Model
{
    protected $fillable = [
        'drive_resource_id',
        'resource_type',
        'status',
        'attempts',
        'next_retry_at',
        'last_error',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'next_retry_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const RESOURCE_FOLDER = 'folder';

    public const RESOURCE_FILE = 'file';

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
