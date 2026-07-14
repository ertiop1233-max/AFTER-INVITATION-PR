# Memory Vault — Release & Deployment Checklist

Follow these steps in order when deploying to Hostinger.

## 1. Pre-Deployment (Local)

- [ ] `npm ci` — install NPM dependencies
- [ ] `npm run build` — compile frontend assets
- [ ] `composer install --no-dev --optimize-autoloader` — install PHP dependencies
- [ ] Verify `public/build/manifest.json` exists and contains 8 entries
- [ ] Verify `.env` is NOT included in the upload package
- [ ] Verify no secrets in `composer.json`, `package.json`, or any committed file

## 2. Upload to Hostinger

- [ ] Upload the entire `memory-vault/` directory to `~/memoryvault/` on Hostinger (via SFTP or Git)
- [ ] Upload includes `vendor/` if Hostinger lacks Composer; otherwise omit and run `composer install --no-dev --optimize-autoloader` on server
- [ ] Upload includes `public/build/` (compiled assets must be on the server)

## 3. Configure Environment

- [ ] Create `~/memoryvault/.env` from `.env.example`
- [ ] Set `APP_KEY` — generate with `php artisan key:generate` if not set
- [ ] Set `APP_URL` to the production domain (https)
- [ ] Set `APP_DEBUG=false`
- [ ] Set database credentials (`DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`)
- [ ] Set `MEMORYVAULT_CLIENT_KEY` — generate with: `php -r "echo 'base64:' . base64_encode(random_bytes(32));"`
- [ ] Set `GOOGLE_SERVICE_ACCOUNT_KEY` (or `GOOGLE_SERVICE_ACCOUNT_KEY_FILE`)
- [ ] Set `GOOGLE_STORAGE_ROOT_FOLDER_ID` (for personal Drive) OR `GOOGLE_SHARED_DRIVE_ID` (for Shared Drive)
- [ ] Set `ADMIN_EMAIL` and `ADMIN_PASSWORD` (at least 12 characters)
- [ ] Set `MAIL_*` credentials for SMTP
- [ ] Set `MEMORYVAULT_UPLOAD_STRATEGY=plan_b` (default; will be confirmed by Phase 0)

## 4. Set Up Database

- [ ] Create a MySQL/MariaDB database on Hostinger
- [ ] Run: `php artisan migrate --force`
- [ ] Run: `php artisan db:seed --class=AdminSeeder --force`
- [ ] Run: `php artisan storage:link`

## 5. Configure Apache

- [ ] Place `public/root-htaccess.example` content into `public_html/.htaccess` if app is nested
- [ ] Verify rewrite rules point to `memoryvault/public/$1`
- [ ] Verify `.env` is denied via `<Files .env> Require all denied</Files>`

## 6. Cache & Optimize

- [ ] Run: `php artisan config:cache`
- [ ] Run: `php artisan route:cache`
- [ ] Run: `php artisan view:cache`
- [ ] Run: `php artisan optimize`

## 7. Configure Cron

- [ ] Add cron job on Hostinger:
  ```
  */5 * * * * /opt/alt/php83/usr/bin/php ~/memoryvault/artisan schedule:run >> /dev/null 2>&1
  ```
- [ ] Verify cron is active by monitoring `storage/logs/laravel-*.log`

## 8. Phase 0 Validation (Hostinger)

Follow `PHASE0.md` for full details. Key validations:

- [ ] V2: `php -v` on Hostinger shows PHP 8.3
- [ ] V5: Create test event → Drive folder appears in correct location
- [ ] V7: Cron executes scheduled commands
- [ ] V10: `php artisan tinker --execute="echo json_encode(app(App\Services\StorageService::class)->getQuotaInfo());"` returns quota data
- [ ] V12: ZIP download of 50-100 files succeeds with low memory
- [ ] V13: Download starts within 2 seconds (no buffering)
- [ ] V14: Refresh page during upload → submission_id is reused
- [ ] V1: Test Plan A CORS — if pass, set `MEMORYVAULT_UPLOAD_STRATEGY=plan_a`; if fail, keep `plan_b`
- [ ] Update `config/memoryvault.php` → `download.max_event_zip_files` and `download.max_event_zip_bytes` to measured values
- [ ] Report Phase 0 results — the unused upload path will be removed based on results

## 9. First Smoke Tests (Post-Deployment)

- [ ] Visit `https://yourdomain.com/health` — JSON shows `database: true`, `drive: true`
- [ ] Visit `https://yourdomain.com/admin/login` — admin login page loads
- [ ] Log in with `ADMIN_EMAIL` and `ADMIN_PASSWORD`
- [ ] Create a test event — Drive folder is created
- [ ] Open the upload page — guest interface loads correctly
- [ ] Test client login at `/client/login` with client credentials
- [ ] Verify admin → Storage page shows Drive health and quota
- [ ] Configure and verify SMTP works (or document it as unavailable)

## 10. Production Monitoring

- [ ] Monitor `storage/logs/laravel-*.log` for 24 hours after deployment
- [ ] Verify cleanup cron runs successfully every 5 minutes
- [ ] Test end-to-end upload flow: visit upload page → name → upload file → submit → see in client dashboard
- [ ] Test ZIP download from client dashboard
- [ ] Test event deletion with type-to-confirm

## 11. Security Verification

- [ ] Verify `X-Frame-Options: DENY` header sent by the server
- [ ] Verify `X-Content-Type-Options: nosniff` header sent
- [ ] Verify `Content-Security-Policy` header sent
- [ ] Verify `.env` file is not accessible via web (`curl https://yourdomain.com/.env` returns 403/404)
- [ ] Verify `composer.json` is not accessible via web
- [ ] Verify `artisan` script is not accessible via web
- [ ] Verify HTTPS redirect works (HTTP → HTTPS)

## 12. Phase 0 Completion

- [ ] Record selected upload architecture (Plan A or Plan B)
- [ ] Record measured ZIP streaming limits
- [ ] Set final `MEMORYVAULT_UPLOAD_STRATEGY` in production `.env`
- [ ] Update `config/memoryvault.php` with measured limits
- [ ] Rebuild caches: `php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan optimize`
- [ ] Contact developer to permanently remove the unused upload path from the codebase