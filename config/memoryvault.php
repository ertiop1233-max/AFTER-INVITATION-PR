<?php

return [

    'upload_strategy' => env('MEMORYVAULT_UPLOAD_STRATEGY', 'plan_b'),

    'max_submission_bytes' => (int) env('MEMORYVAULT_MAX_SUBMISSION_BYTES', 2147483648),

    'upload_max_concurrent' => (int) env('MEMORYVAULT_UPLOAD_MAX_CONCURRENT', 3),

    'upload_max_retries' => (int) env('MEMORYVAULT_UPLOAD_MAX_RETRIES', 3),

    'upload_chunk_size' => (int) env('MEMORYVAULT_UPLOAD_CHUNK_SIZE', 8388608),

    'upload_nonce_ttl_hours' => (int) env('MEMORYVAULT_UPLOAD_NONCE_TTL_HOURS', 24),

    'drive_quota_warning_percent' => (int) env('MEMORYVAULT_DRIVE_QUOTA_WARNING_PERCENT', 80),

    'client_key' => env('MEMORYVAULT_CLIENT_KEY'),

    'google' => [
        'service_account_key' => env('GOOGLE_SERVICE_ACCOUNT_KEY'),
        'service_account_key_file' => env('GOOGLE_SERVICE_ACCOUNT_KEY_FILE'),
        'shared_drive_id' => env('GOOGLE_SHARED_DRIVE_ID'),
        'storage_root_folder_id' => env('GOOGLE_STORAGE_ROOT_FOLDER_ID'),
    ],

    'admin' => [
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    'cleanup' => [
        'draft_max_age_hours' => 24,
        'media_max_age_hours' => 24,
        'max_retry_attempts' => 10,
        'retry_base_delay_seconds' => 300,
    ],

    'voice' => [
        'max_duration_seconds' => 600,
        'max_file_size_bytes' => 15728640,
    ],

    'download' => [
        'max_event_zip_files' => 500,
        'max_event_zip_bytes' => 5368709120,
    ],

];
