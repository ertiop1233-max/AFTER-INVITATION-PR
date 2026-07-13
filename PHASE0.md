# Phase 0 Validation Guide

This document describes how to run the Phase 0 validations (V1–V14) on the Hostinger environment to determine the final upload architecture.

## Prerequisites

1. Deploy the application to Hostinger following `DEPLOYMENT.md`.
2. Configure `.env` with real Google Service Account credentials.
3. Configure Google Drive storage (see below).
4. Set `MEMORYVAULT_UPLOAD_STRATEGY=plan_b` as the default (Plan B is the safe default).
5. Run migrations and seed the admin.

### Google Drive Storage Configuration

The application supports two storage modes. Set ONE of the following:

**Mode 1 — Google Shared Drive (Google Workspace)**:
- Set `GOOGLE_SHARED_DRIVE_ID` to the Shared Drive ID.
- Leave `GOOGLE_STORAGE_ROOT_FOLDER_ID` empty or unset.
- Event folders are created at the Shared Drive root.

**Mode 2 — Personal Google Drive (standard Google account)**:
- Leave `GOOGLE_SHARED_DRIVE_ID` empty.
- Set `GOOGLE_STORAGE_ROOT_FOLDER_ID` to an existing folder ID in My Drive.
- Create the root folder manually in Google Drive first.
- Event folders are created inside that root folder.

The mode is selected automatically based on whether `GOOGLE_SHARED_DRIVE_ID` is set.

## Validation Procedures

### V1: Google Drive CORS
**Goal**: Determine if Plan A (direct browser→Drive upload) is viable.

1. Open the upload page for any active event.
2. Open browser DevTools → Console.
3. The upload page logs the current strategy. If `plan_a` is configured, it will attempt a direct Drive upload.
4. To test Plan A manually, set `MEMORYVAULT_UPLOAD_STRATEGY=plan_a` in `.env`, clear config cache, and try uploading a small file.
5. Check if the browser can PUT directly to the Google Drive resumable URI without CORS errors.

**Pass**: No CORS errors; upload completes. → Plan A eligible.
**Fail**: CORS errors; upload fails. → Plan B required.

### V2: Hostinger PHP Version
```bash
/opt/alt/php83/usr/bin/php -v
```
Must show PHP 8.3.x.

### V3: Composer
```bash
composer --version
```
If unavailable, deploy `vendor/` directory built locally.

### V4: SSH Access
```bash
ssh user@your-hostinger-server
```
If unavailable, use SFTP deployment with local builds.

### V5: Google Drive Storage Setup
1. Ensure `.env` is configured for either Shared Drive mode or Personal Drive mode (see Prerequisites above).
2. Create a test event via the admin dashboard.
3. Check that the event folder appears in the correct location:
   - Shared Drive mode: at the Shared Drive root.
   - Personal Drive mode: inside the configured `GOOGLE_STORAGE_ROOT_FOLDER_ID` folder.
4. Verify via admin → Storage page that Drive health is "Connected".

### V6: PHP Upload Limits
```bash
php -i | grep -E "upload_max_filesize|post_max_size"
```
If Plan B: chunk size (8MB) must be below both values. If too low, reduce `MEMORYVAULT_UPLOAD_CHUNK_SIZE`.

### V7: Cron Support
Add a test cron job:
```
* * * * * /opt/alt/php83/usr/bin/php ~/memoryvault/artisan schedule:run >> /dev/null 2>&1
```
Check that scheduled tasks execute by monitoring `storage/logs/laravel-*.log`.

### V8: Laravel Install
```bash
/opt/alt/php83/usr/bin/php artisan migrate --force
```
Must complete without errors.

### V9: set_time_limit(0)
Create a temporary route or use `php artisan tinker`:
```php
set_time_limit(0);
sleep(10);
echo "OK";
```
If the script is killed before completing, `set_time_limit(0)` is ignored.

### V10: Drive Quota Readable
```bash
/opt/alt/php83/usr/bin/php artisan tinker --execute="echo json_encode(app(App\Services\StorageService::class)->getQuotaInfo());"
```
If null, remove quota alert and document limitation.

### V11: Large Plan A Upload
1. Set `MEMORYVAULT_UPLOAD_STRATEGY=plan_a`.
2. Upload a ~50MB file from the browser.
3. Monitor progress bar and completion.
4. If progress/completion unreliable, use Plan B.

### V12: ZIP Streaming Endurance
1. Create an event with 50–100 mixed files.
2. Download the full event ZIP from the client dashboard.
3. Record max safe file count and total bytes.
4. Update `config/memoryvault.php` → `download.max_event_zip_files` and `download.max_event_zip_bytes`.

### V13: Output Buffering/Compression
1. Start a ZIP download.
2. Check that the download starts within 2 seconds (not buffered).
3. Monitor server memory — should stay low.
4. If buffered, verify the PHP-side `Content-Encoding: identity` safeguard works.

### V14: Refresh Recovery
1. Open the upload page, enter name, select a file.
2. Wait for upload to start.
3. Refresh the page.
4. Verify the same `submission_id` is reused (check localStorage and network tab).
5. Verify no duplicate draft submission is created.

## After Phase 0

1. Record the selected upload architecture (Plan A or Plan B).
2. Record measured ZIP limits.
3. Record output buffering behavior.
4. Set the final `MEMORYVAULT_UPLOAD_STRATEGY` in production `.env`.
5. The unused upload path (Plan A or Plan B) must be completely removed from:
   - Controllers (`UploadController` methods)
   - Routes (`api.php`)
   - Services (`UploadService` strategy branches)
   - JavaScript (`upload-queue.js` strategy handling)
   - Configuration (`upload_strategy` setting)
   - Database (`resumable_uri` column if Plan A selected; folder-lookup logic if Plan B)
   - Tests
   - Documentation
