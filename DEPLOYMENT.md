# Memory Vault — Deployment Guide

## Overview

Memory Vault is a Laravel 11 application designed for Hostinger shared hosting. It uses Google Drive for file storage and MySQL/MariaDB for the database.

## Google Drive Storage Configuration

The application supports two storage modes. The mode is selected automatically based on whether `GOOGLE_SHARED_DRIVE_ID` is configured.

### Mode 1 — Google Shared Drive (Google Workspace)

Use this mode if you have a Google Workspace account with Shared Drives.

**Setup**:
1. Create a Google Cloud project.
2. Enable the Google Drive API.
3. Create a service account and download the JSON key.
4. Create a Shared Drive (e.g., `Memory Vault Storage`).
5. Add the service account to the Shared Drive as Content Manager or higher.
6. Configure `.env`:
   ```env
   GOOGLE_SERVICE_ACCOUNT_KEY=<base64 of service account JSON>
   GOOGLE_SHARED_DRIVE_ID=<Shared Drive ID>
   ```
7. Leave `GOOGLE_STORAGE_ROOT_FOLDER_ID` empty.

Event folders are created at the Shared Drive root.

### Mode 2 — Personal Google Drive (standard Google account)

Use this mode if you are using a standard Google account without Google Workspace.

**Setup**:
1. Create a Google Cloud project.
2. Enable the Google Drive API.
3. Create a service account and download the JSON key.
4. Create a root folder in My Drive (e.g., `Memory Vault`).
5. Share the root folder with the service account email (Editor access).
6. Get the folder ID from the Drive URL.
7. Configure `.env`:
   ```env
   GOOGLE_SERVICE_ACCOUNT_KEY=<base64 of service account JSON>
   GOOGLE_STORAGE_ROOT_FOLDER_ID=<folder ID of the root folder>
   ```
8. Leave `GOOGLE_SHARED_DRIVE_ID` empty.

Event folders are created inside the configured root folder.

### Finding a Folder ID

Open the folder in Google Drive. The URL looks like:
```
https://drive.google.com/drive/folders/XXXXXXXXXXXXXXXXXXXXXXXXXXXXX
```
The folder ID is the string after `/folders/`.

### Encoding the Service Account Key

```powershell
[Convert]::ToBase64String([IO.File]::ReadAllBytes("C:\path\to\service-account.json"))
```

```bash
base64 -w 0 service-account.json
```

Alternatively, set `GOOGLE_SERVICE_ACCOUNT_KEY_FILE` to the path of the JSON file instead of using base64 encoding.

## First Deployment

### 1. Build Assets Locally

```bash
npm ci
npm run build
composer install --no-dev --optimize-autoloader
```

### 2. Upload Code to Hostinger

Upload the entire project to `~/memoryvault/` on Hostinger via SFTP or Git.

### 3. Configure Environment

Create `~/memoryvault/.env` from `.env.example`:

```env
APP_NAME="Memory Vault"
APP_ENV=production
APP_KEY=<generate with: php artisan key:generate>
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_TIMEZONE=UTC

LOG_CHANNEL=daily
LOG_LEVEL=info
LOG_DAYS=14

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

SESSION_DRIVER=database
SESSION_LIFETIME=129600
SESSION_EXPIRE_ON_CLOSE=false
SESSION_ENCRYPT=true
SESSION_COOKIE=memoryvault_session
SESSION_PATH=/
SESSION_DOMAIN=
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

CACHE_STORE=file
QUEUE_CONNECTION=sync

MAIL_MAILER=smtp
MAIL_HOST=your_smtp_host
MAIL_PORT=587
MAIL_USERNAME=your_smtp_user
MAIL_PASSWORD=your_smtp_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Memory Vault"

GOOGLE_SERVICE_ACCOUNT_KEY=<base64 key>
GOOGLE_SHARED_DRIVE_ID=<or leave empty for Personal Drive>
GOOGLE_STORAGE_ROOT_FOLDER_ID=<root folder ID>

MEMORYVAULT_CLIENT_KEY=<generate with: php -r "echo 'base64:' . base64_encode(random_bytes(32));">
MEMORYVAULT_UPLOAD_STRATEGY=plan_b

ADMIN_EMAIL=admin@yourdomain.com
ADMIN_PASSWORD=<at least 12 characters>
```

### 4. Run Setup Commands

```bash
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan migrate --force
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan db:seed --class=AdminSeeder --force
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan storage:link
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan config:cache
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan route:cache
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan view:cache
```

### 5. Configure Apache Rewrite

If the app is installed at `public_html/memoryvault/`, place this in `public_html/.htaccess`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ memoryvault/public/$1 [L,QSA]
</IfModule>

<Files .env>
    Require all denied
</Files>
<Files composer.json>
    Require all denied
</Files>
<Files artisan>
    Require all denied
</Files>
```

### 6. Configure Cron

```bash
*/5 * * * * /opt/alt/php83/usr/bin/php ~/memoryvault/artisan schedule:run >> /dev/null 2>&1
```

### 7. Verify Deployment

- Visit `/health` — should return JSON with `database: true`, `drive: true`.
- Visit `/admin/login` — should show the admin login page.
- Log in with `ADMIN_EMAIL` and `ADMIN_PASSWORD`.
- Create a test event and verify the Google Drive folder is created.
- Visit `/memories/{slug}/{token}` — should show the guest upload page.

## Subsequent Deployments

```bash
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan down
```

Upload new code and fresh `public/build/` assets.

```bash
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan migrate --force
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan config:clear
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan route:clear
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan view:clear
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan config:cache
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan route:cache
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan view:cache
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan up
```

## Rollback

1. Restore previous code release.
2. Restore previous `public/build/`.
3. Clear and rebuild caches.
4. Only run `migrate:rollback` when the migration is confirmed reversible and no production data will be lost.

## Backup Guidance

Backups are outside the application. The administrator is responsible for:

- **Database**: Export with `mysqldump` or Hostinger backup tooling.
- **Uploaded media**: Ensure Google Drive contents are retained or covered by Google's backup.
- **Application code**: Keep the Git repository available.
- **Environment**: Securely store a copy of production `.env`, especially `APP_KEY`, `MEMORYVAULT_CLIENT_KEY`, Google credentials, and database credentials.

**Warning**: Losing `MEMORYVAULT_CLIENT_KEY` makes encrypted client passwords unrecoverable. Losing `APP_KEY` can invalidate encrypted Laravel data and sessions.

## PHP Configuration

| Setting | Target |
|---|---|
| PHP | 8.3 |
| `upload_max_filesize` | >= 20M |
| `post_max_size` | >= 25M |
| `max_execution_time` | 300 or measured safe value |
| `memory_limit` | >= 256M |
