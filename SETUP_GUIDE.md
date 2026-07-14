# Memory Vault — Complete Setup Guide

> **Read this entire document once before starting.** It is written as a step-by-step playbook. Do not skip any section, even if it seems obvious. Every step matters.

---

## Table of Contents

1. Prerequisites
2. Hostinger Setup
3. Uploading Project
4. Environment Configuration
5. APP_KEY
6. MEMORYVAULT_CLIENT_KEY
7. Google Cloud Setup
8. SMTP Setup
9. Database Migration
10. Seeder
11. Build Assets
12. Caching
13. Health Checks
14. Phase 0 Validation
15. Choosing Plan A vs Plan B
16. First Production Event
17. Backup
18. Updating the Application
19. Troubleshooting
20. Final Production Checklist

---

# 1. Prerequisites

## 1.1 Accounts Required

| Account | Purpose | How to Get |
|---|---|---|
| Hostinger hosting account | Web server, PHP, MySQL, cron | https://www.hostinger.com |
| Google account | Google Drive storage | Any Gmail or Google Workspace account |
| Google Cloud Console | Service account for Drive API access | https://console.cloud.google.com |

## 1.2 Software Required on Your Local Machine

| Software | Version | Purpose | Download |
|---|---|---|---|
| Node.js | 18+ | Build frontend assets | https://nodejs.org |
| npm | 9+ | Package manager (comes with Node.js) | Included with Node.js |
| PHP CLI | 8.3 | Run artisan commands locally | https://www.php.net or `choco install php --version=8.3` |
| Composer | 2.5+ | PHP dependency management | https://getcomposer.org |
| SFTP client | Any | Upload files to Hostinger | FileZilla, WinSCP, or Hostinger File Manager |
| Text editor | Any | Edit `.env` file | VS Code, Notepad++, etc. |

## 1.3 Credentials Required

Have these ready before you begin:

- [ ] Hostinger control panel login (hPanel)
- [ ] Hostinger SSH/SFTP credentials (hostname, username, password)
- [ ] Google account email and password
- [ ] A secure location to store secrets (password manager, encrypted USB, etc.)

## 1.4 Files Required

- [ ] The `memory-vault/` project folder (the codebase)
- [ ] An empty `.env` file (copied from `.env.example` — covered in Section 4)

## 1.5 What You Do NOT Need

- You do NOT need a Google Workspace account. A standard free Gmail account works.
- You do NOT need Docker.
- You do NOT need a local web server (we build locally and deploy to Hostinger).
- You do NOT need Redis or any external cache server.

---

# 2. Hostinger Setup

## 2.1 Log Into Hostinger hPanel

1. Go to https://hpanel.hostinger.com
2. Enter your Hostinger email and password.
3. Select your hosting plan if you have multiple.

> **Screenshot: Hostinger hPanel Dashboard**

## 2.2 Set PHP Version

1. In hPanel, click **Advanced** → **PHP Configuration** (or **MultiPHP Manager** in some themes).
2. Set the PHP version to **8.3**.
3. Click **Save**.

> **Screenshot: PHP Version Selection**

**Verify**: Open a terminal on Hostinger (SSH) or use the **Terminal** tool in hPanel:
```bash
/opt/alt/php83/usr/bin/php -v
```
Expected output: `PHP 8.3.x (cli)`

If you cannot use SSH, you can skip this verification and check later via the web app's health endpoint.

## 2.3 Enable Required PHP Extensions

1. In hPanel, go to **Advanced** → **PHP Options** (or **Select PHP Version** → **Options** in some themes).
2. Scroll down to the **Extensions** list.
3. Make sure the following extensions are enabled (checked):
   - `pdo_mysql`
   - `openssl`
   - `mbstring`
   - `fileinfo`
   - `gd`
   - `curl`
   - `zip`
   - `intl`
   - `bcmath`
4. Click **Save** or **Apply**.

> **Screenshot: PHP Extensions Checklist**

## 2.4 Create MySQL Database

1. In hPanel, go to **Databases** → **MySQL Databases**.
2. Click **Create New Database**.
3. Fill in:
   - **Database name**: `memoryvault` (or any name you prefer)
   - **Database username**: `mvuser` (or any username you prefer)
   - **Database password**: Generate a strong password — **write it down**.
   - **Password (confirm)**: Same as above.
4. Click **Create**.

> **Screenshot: MySQL Database Creation**

**Record these credentials immediately**:
```
DB_DATABASE  = your_Hostinger_database_name     (e.g., u123456789_memoryvault)
DB_USERNAME  = your_Hostinger_database_username (e.g., u123456789_mvuser)
DB_PASSWORD  = your_Hostinger_database_password
DB_HOST      = localhost
DB_PORT      = 3306
```

> **Important**: Hostinger prefixes database names and usernames with your account ID. For example, if your account is `u123456789`, the database name becomes `u123456789_memoryvault`. Use the FULL name including the prefix in your `.env`.

## 2.5 Verify Database

1. In hPanel, go to **Databases** → **phpMyAdmin**.
2. Select your newly created database from the left sidebar.
3. Confirm it is empty (no tables yet).

> **Screenshot: phpMyAdmin Empty Database**

## 2.6 Configure Cron Jobs

1. In hPanel, go to **Advanced** → **Cron Jobs**.
2. Click **Add New Cron Job**.
3. Set:
   - **Minute**: `*/5`
   - **Hour**: `*`
   - **Day**: `*`
   - **Month**: `*`
   - **Weekday**: `*`
   - If your hPanel has a common presets dropdown, select **Every 5 minutes**.
4. In the **Command** field, enter:
   ```
   /opt/alt/php83/usr/bin/php ~/memoryvault/artisan schedule:run >> /dev/null 2>&1
   ```
5. Click **Save** or **Add**.

> **Screenshot: Cron Job Configuration**

**What this does**: Runs Laravel's scheduler every 5 minutes. The scheduler triggers cleanup commands, Drive cleanup queue processing, and health checks automatically.

## 2.7 Enable SSL (HTTPS)

1. In hPanel, go to **Security** → **SSL**.
2. If you don't have an SSL certificate, click **Get Free SSL** or **Issue SSL** (Let's Encrypt).
3. Verify that SSL is active for your domain.
4. Open **Advanced** → **Force HTTPS** (if available) and enable it to redirect all HTTP traffic to HTTPS.

> **Screenshot: SSL Configuration**

## 2.8 Domain/Subdomain

If you are deploying to your main domain (e.g., `https://yourdomain.com/`), no additional configuration is needed for the domain itself.

If you are deploying to a subdomain (e.g., `https://memories.yourdomain.com/`):
1. In hPanel, go to **Domains** → **Subdomains**.
2. Click **Create New Subdomain**.
3. Enter the subdomain name (e.g., `memories`).
4. The document root will be set automatically (e.g., `public_html/memories`).
5. Click **Create**.

## 2.9 Storage Link

The `storage:link` artisan command creates a symlink from `public/storage/` to `storage/app/public/`. This must be run after deployment:

```bash
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan storage:link
```

This is covered in Section 9 (Database Migration) as part of the post-deployment commands. Do not run it yet — it must be run on the server after files are uploaded.

---

# 3. Uploading Project

## 3.1 What to Upload

**Upload these** (the entire `memory-vault/` folder contents):
- `app/`
- `bootstrap/`
- `config/`
- `database/` (migrations and seeders only, NOT the SQLite file)
- `public/` (including `public/build/` — the compiled assets)
- `resources/` (views, CSS source, JS source)
- `routes/`
- `storage/` (only the folder structure; logs and cache should be empty)
- `vendor/` (if Hostinger has no Composer — otherwise skip and run `composer install` on server)
- `artisan`
- `composer.json`
- `composer.lock`
- `package.json`
- `vite.config.js`
- `.env.example` (as a template)
- `DEPLOYMENT.md`
- `PHASE0.md`
- `RELEASE_CHECKLIST.md`
- `SETUP_GUIDE.md`

**Do NOT upload these**:
- `node_modules/` (200+ MB of NPM packages — not needed on server)
- `database/database.sqlite` (local dev database)
- `.env` (will be created manually on the server — contains secrets)
- `.git/` (version control data — not needed on server)
- `tests/` (not needed in production; harmless but unnecessary)

## 3.2 How to Upload

### Option A: Hostinger File Manager

1. In hPanel, go to **Files** → **File Manager**.
2. Navigate to your home directory (usually `~/` or `/home/u123456789/`).
3. Create a folder named `memoryvault`.
4. Open the `memoryvault` folder.
5. Click **Upload** and upload the project files.

> **Screenshot: Hostinger File Manager Upload**

### Option B: SFTP (Recommended for Large Projects)

1. Download and install FileZilla or WinSCP.
2. Connect to your Hostinger server:
   - **Host**: `sftp://your-domain.com` or your server IP
   - **Username**: Your Hostinger SSH/SFTP username
   - **Password**: Your Hostinger SSH/SFTP password
   - **Port**: 65022 (Hostinger default, or check hPanel → SSH Access)
3. Navigate to your home directory.
4. Create a folder named `memoryvault`.
5. Upload all project files into `~/memoryvault/`.

> **Screenshot: FileZilla SFTP Connection**

### Option C: SSH + Git Clone (Advanced)

If you have SSH access and the project is on GitHub/GitLab:
```bash
ssh u123456789@your-domain.com -p 65022
cd ~
git clone https://github.com/your-repo/memory-vault.git memoryvault
cd memoryvault
composer install --no-dev --optimize-autoloader
```

## 3.3 Expected Directory Structure on Hostinger

After upload, your home directory should look like:

```
/home/u123456789/
├── memoryvault/
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── public/
│   │   ├── build/
│   │   │   ├── assets/
│   │   │   └── manifest.json
│   │   ├── .htaccess
│   │   └── index.php
│   ├── resources/
│   │   ├── css/
│   │   ├── js/
│   │   └── views/
│   ├── routes/
│   ├── storage/
│   │   ├── app/
│   │   │   └── public/
│   │   ├── framework/
│   │   │   ├── cache/
│   │   │   ├── sessions/
│   │   │   └── views/
│   │   └── logs/
│   ├── vendor/
│   ├── artisan
│   ├── composer.json
│   ├── composer.lock
│   ├── .env.example
│   └── .env       ← created in Section 4
├── public_html/
│   └── .htaccess  ← created in Section 3.4
└── ...
```

## 3.4 Configure Public Folder (Nested Laravel Deployment)

Laravel's public directory must serve as the web root. Since the app is at `~/memoryvault/` (not `~/public_html/`), we need a rewrite rule.

1. In hPanel, go to **Files** → **File Manager**.
2. Navigate to `~/public_html/`.
3. Find the `.htaccess` file (create one if it doesn't exist).
4. Replace its content with:

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

5. Click **Save**.

> **Screenshot: public_html .htaccess Configuration**

**What this does**: All web traffic hitting `https://yourdomain.com/` is routed to `memoryvault/public/index.php`, which is Laravel's entry point. The last three blocks prevent access to sensitive files.

## 3.5 Set Folder Permissions

If you get "500 Internal Server Error" after deployment, the issue is usually storage folder permissions.

Via SSH:
```bash
chmod -R 775 ~/memoryvault/storage
chmod -R 775 ~/memoryvault/bootstrap/cache
chmod -R 755 ~/memoryvault/public
```

Via File Manager:
1. Right-click the `storage` folder → **Permissions** → Set to `775`.
2. Check "Apply to subdirectories recursively".
3. Click **Save**.
4. Repeat for `bootstrap/cache`.

> **Screenshot: Folder Permissions in File Manager**

---

# 4. Environment Configuration

## 4.1 Create the .env File

1. Via File Manager or SSH, navigate to `~/memoryvault/`.
2. Copy `.env.example` to `.env`.
   - In File Manager: right-click `.env.example` → **Copy** → name the copy `.env`.
   - Via SSH: `cp .env.example .env`
3. Open `.env` for editing.

## 4.2 Complete .env Variable Reference

Below is every variable in the `.env` file. Go through each one.

### APP_NAME

| | |
|---|---|
| What it does | Sets the application name shown in the browser title and emails. |
| How to obtain | Choose any name. |
| Example value | `"Memory Vault"` |
| Common mistakes | Forgetting the quotes if the name contains spaces. |
| Required | Yes |

### APP_ENV

| | |
|---|---|
| What it does | Sets the application environment. `production` disables debug output. |
| How to obtain | Always use `production` for live deployment. |
| Example value | `production` |
| Common mistakes | Setting this to `local` in production — visitors will see full error details. |
| Required | Yes |

### APP_KEY

| | |
|---|---|
| What it does | Encrypts session data and sensitive values. |
| How to obtain | Generated in Section 5. |
| Example value | `base64:CUUANwl3YWHwUTUvStDqtN8jvYY5FLUn6v4PyI4Q3Ws=` |
| Common mistakes | Leaving this empty — the app will crash. Changing it after data exists — sessions become invalid. |
| Required | Yes |

### APP_DEBUG

| | |
|---|---|
| What it does | When `true`, full error details are shown to visitors. When `false`, a friendly error page is shown. |
| How to obtain | Always `false` in production. |
| Example value | `false` |
| Common mistakes | Setting to `true` in production — exposes code, file paths, and database structure to visitors. |
| Required | Yes |

### APP_URL

| | |
|---|---|
| What it does | The base URL of the application. Used for generating links, QR codes, and redirects. |
| How to obtain | Your domain URL (with https and no trailing slash). |
| Example value | `https://yourdomain.com` |
| Common mistakes | Using `http://` instead of `https://` — QR codes will point to insecure URLs. Forgetting the protocol. Adding a trailing slash. |
| Required | Yes |

### APP_TIMEZONE

| | |
|---|---|
| What it does | Sets the timezone for all timestamps. The plan requires UTC. |
| How to obtain | Use `UTC` per the implementation plan. |
| Example value | `UTC` |
| Common mistakes | Using a local timezone — breaks consistent timestamp comparison for cleanup and deadlines. |
| Required | Yes |

### LOG_CHANNEL

| | |
|---|---|
| What it does | Sets how logs are written. `daily` creates a new file each day. |
| How to obtain | Use `daily`. |
| Example value | `daily` |
| Common mistakes | Using `single` — all logs go to one file that grows forever. |
| Required | Yes |

### LOG_LEVEL

| | |
|---|---|
| What it does | Minimum severity to log. `info` captures everything important without flooding. |
| How to obtain | Use `info`. |
| Example value | `info` |
| Common mistakes | Using `debug` in production — log files grow rapidly. |
| Required | Yes |

### LOG_DAYS

| | |
|---|---|
| What it does | Number of days to keep log files before automatic deletion. |
| How to obtain | Use `14` per the implementation plan. |
| Example value | `14` |
| Common mistakes | Setting too high — consumes storage on shared hosting. |
| Required | Yes |

### DB_CONNECTION

| | |
|---|---|
| What it does | Database type. |
| How to obtain | Always `mysql` on Hostinger. |
| Example value | `mysql` |
| Common mistakes | Leaving as `sqlite` — the app will look for a local SQLite file. |
| Required | Yes |

### DB_HOST

| | |
|---|---|
| What it does | Database server hostname. |
| How to obtain | Hostinger → Databases → MySQL Databases → look for "Host". Usually `localhost`. |
| Example value | `localhost` |
| Common mistakes | Using a remote host when the database is on the same server. |
| Required | Yes |

### DB_PORT

| | |
|---|---|
| What it does | Database server port. |
| How to obtain | MySQL default is 3306. Hostinger may show the port in the database page. |
| Example value | `3306` |
| Common mistakes | Using a non-standard port without checking. |
| Required | Yes |

### DB_DATABASE

| | |
|---|---|
| What it does | The name of the MySQL database. |
| How to obtain | Hostinger → Databases → MySQL Databases. Use the FULL name including your account prefix (e.g., `u123456789_memoryvault`). |
| Example value | `u123456789_memoryvault` |
| Common mistakes | Forgetting the Hostinger account prefix. |
| Required | Yes |

### DB_USERNAME

| | |
|---|---|
| What it does | The MySQL database username. |
| How to obtain | Same as the database — includes the Hostinger account prefix. |
| Example value | `u123456789_mvuser` |
| Common mistakes | Forgetting the prefix. Using the hPanel login instead of the database username. |
| Required | Yes |

### DB_PASSWORD

| | |
|---|---|
| What it does | The MySQL database password you created in Section 2.4. |
| How to obtain | The password you wrote down when creating the database. |
| Example value | `Tr0ub4dour&3` (your actual password) |
| Common mistakes | Including spaces or special characters without quotes. Copy-pasting with trailing whitespace. |
| Required | Yes |

### SESSION_DRIVER

| | |
|---|---|
| What it does | Where session data is stored. Per the plan, we use the database. |
| How to obtain | Use `database`. |
| Example value | `database` |
| Common mistakes | Using `file` — works but doesn't scale and is harder to clean up. |
| Required | Yes |

### SESSION_LIFETIME

| | |
|---|---|
| What it does | Session lifetime in minutes. 129600 = 90 days (per the plan). |
| How to obtain | Use `129600`. |
| Example value | `129600` |
| Common mistakes | Setting too low — client sessions expire prematurely. |
| Required | Yes |

### SESSION_ENCRYPT

| | |
|---|---|
| What it does | Encrypts session data at rest. |
| How to obtain | Use `true`. |
| Example value | `true` |
| Common mistakes | Setting to `false` — session data is readable if the database is compromised. |
| Required | Yes |

### SESSION_COOKIE

| | |
|---|---|
| What it does | Name of the session cookie in the browser. |
| How to obtain | Use `memoryvault_session`. |
| Example value | `memoryvault_session` |
| Common mistakes | Using the default Laravel name — conflicts if you run multiple Laravel apps. |
| Required | Yes |

### SESSION_SECURE_COOKIE

| | |
|---|---|
| What it does | Only send cookies over HTTPS. Set to `true` if you have SSL (you should). |
| How to obtain | Use `true`. |
| Example value | `true` |
| Common mistakes | Setting to `true` without SSL — the app becomes inaccessible because cookies are never sent. |
| Required | Yes |

### SESSION_EXPIRE_ON_CLOSE

| | |
|---|---|
| What it does | When `true`, the session is deleted when the browser is closed. When `false`, the session persists until `SESSION_LIFIFETIME` expires. |
| How to obtain | Use `false` (default in `.env.example`). |
| Example value | `false` |
| Common mistakes | Setting to `true` — client sessions are lost when they close their browser, even within the 90-day window. |
| Required | Yes |

### SESSION_PATH

| | |
|---|---|
| What it does | The URL path the session cookie is valid for. |
| How to obtain | Use `/` (default in `.env.example`). |
| Example value | `/` |
| Common mistakes | Setting to a sub-path — cookies are not sent for other paths. |
| Required | Yes |

### SESSION_DOMAIN

| | |
|---|---|
| What it does | The domain the session cookie is valid for. Leave empty for the default domain. |
| How to obtain | Leave empty (default in `.env.example`). Only set if deploying across multiple subdomains. |
| Example value | (empty) |
| Common mistakes | Setting a domain that doesn't match your deployment URL. |
| Required | Yes |

### SESSION_HTTP_ONLY

| | |
|---|---|
| What it does | When `true`, JavaScript cannot access the session cookie (prevents XSS theft). |
| How to obtain | Use `true` (default in `.env.example`). |
| Example value | `true` |
| Common mistakes | Setting to `false` — JavaScript can read the session cookie, creating a security risk. |
| Required | Yes |

### SESSION_SAME_SITE

| | |
|---|---|
| What it does | Controls when the session cookie is sent cross-site. `lax` is the most compatible secure setting. |
| How to obtain | Use `lax` (default in `.env.example`). |
| Example value | `lax` |
| Common mistakes | Setting to `strict` — breaks redirects from external links into the app. |
| Required | Yes |

### CACHE_STORE

| | |
|---|---|
| What it does | Where cache data is stored. |
| How to obtain | Use `file` (uses the filesystem). |
| Example value | `file` |
| Common mistakes | Using `database` — requires the cache table migration which we don't include. |
| Required | Yes |

### QUEUE_CONNECTION

| | |
|---|---|
| What it does | How queued jobs are processed. Per the plan, we use `sync` (synchronous — jobs run immediately). |
| How to obtain | Use `sync`. |
| Example value | `sync` |
| Common mistakes | Using `database` — requires queue table and a queue worker process (not available on shared hosting). |
| Required | Yes |

### MAIL_MAILER

| | |
|---|---|
| What it does | Email sending method. |
| How to obtain | Use `smtp` (covered in Section 8). Can use `log` for testing — emails are written to the log file instead of sent. |
| Example value | `smtp` |
| Common mistakes | Using `log` in production — no emails are actually sent. |
| Required | Yes |

### MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_ENCRYPTION

See Section 8 (SMTP Setup).

### MAIL_FROM_ADDRESS

| | |
|---|---|
| What it does | The email address shown as "From" in sent emails. |
| How to obtain | Use an email on your domain (e.g., `noreply@yourdomain.com`). |
| Example value | `noreply@yourdomain.com` |
| Common mistakes | Using a Gmail address — may be rejected by spam filters. |
| Required | Yes |

### MAIL_FROM_NAME

| | |
|---|---|
| What it does | The display name in sent emails. |
| How to obtain | Use "Memory Vault" or your business name. |
| Example value | `"Memory Vault"` |
| Common mistakes | Forgetting quotes if the name contains spaces. |
| Required | Yes |

### GOOGLE_SERVICE_ACCOUNT_KEY

| | |
|---|---|
| What it does | Base64-encoded Google service account JSON key. Used to authenticate with Google Drive API. |
| How to obtain | See Section 7 (Google Cloud Setup). |
| Example value | `base64:eyJ0eXBlIjoi...` (a very long string) |
| Common mistakes | Not properly base64-encoding the JSON. Using `APP_KEY` format instead. |
| Required | Yes (or use `GOOGLE_SERVICE_ACCOUNT_KEY_FILE` instead) |

### GOOGLE_SERVICE_ACCOUNT_KEY_FILE

| | |
|---|---|
| What it does | Direct path to the Google service account JSON file on the server. Alternative to base64 encoding. |
| How to obtain | Upload the JSON key to a secure location on the server (NOT in public_html). Put the absolute path here. |
| Example value | `/home/u123456789/keys/service-account.json` |
| Common mistakes | Placing the file inside the web root — it becomes downloadable. |
| Required | No (use this OR `GOOGLE_SERVICE_ACCOUNT_KEY`) |

### GOOGLE_SHARED_DRIVE_ID

| | |
|---|---|
| What it does | ID of the Google Shared Drive to use for storage. Only for Google Workspace accounts. |
| How to obtain | The Shared Drive ID from the Drive URL. See Section 7. |
| Example value | `0ABCD1234EFGHI` |
| Common mistakes | Setting this when using a personal Google account — the app will fail to find the Shared Drive. |
| Required | No (leave empty for personal Google Drive — see Section 7) |

### GOOGLE_STORAGE_ROOT_FOLDER_ID

| | |
|---|---|
| What it does | ID of the root folder in My Drive where all event folders will be created. Used when `GOOGLE_SHARED_DRIVE_ID` is empty. |
| How to obtain | The folder ID from the Google Drive URL. See Section 7. |
| Example value | `1aBcDeFgHiJkLmNoPqRsTuVwXyZ1234567` |
| Common mistakes | Using the folder name instead of the ID. Not sharing the folder with the service account. |
| Required | Yes (for personal Google Drive mode) |

### MEMORYVAULT_CLIENT_KEY

| | |
|---|---|
| What it does | Encryption key for client passwords. Separate from `APP_KEY` per the plan. |
| How to obtain | Generated in Section 6. |
| Example value | `base64:gEIYVTP8yYaPDiMP69fK4sfm4J7nUZuH55YXrJCOU4A=` |
| Common mistakes | Using the same value as `APP_KEY`. Losing this key — client passwords become unrecoverable. |
| Required | Yes |

### MEMORYVAULT_UPLOAD_NONCE_TTL_HOURS

| | |
|---|---|
| What it does | How long an upload nonce is valid (in hours). |
| How to obtain | Use `24` (default). |
| Example value | `24` |
| Common mistakes | Setting too low — guests must reload the page frequently. |
| Required | Yes |

### MEMORYVAULT_MAX_SUBMISSION_BYTES

| | |
|---|---|
| What it does | Maximum total size of a single submission in bytes. |
| How to obtain | Use `2147483648` (2 GB) per the plan. |
| Example value | `2147483648` |
| Common mistakes | Setting higher than server upload limits. |
| Required | Yes |

### MEMORYVAULT_UPLOAD_MAX_CONCURRENT

| | |
|---|---|
| What it does | Maximum number of files uploading simultaneously per guest. |
| How to obtain | Use `3` (default, recommended). |
| Example value | `3` |
| Common mistakes | Setting too high — may overwhelm shared hosting. |
| Required | Yes |

### MEMORYVAULT_UPLOAD_MAX_RETRIES

| | |
|---|---|
| What it does | Maximum retry attempts for a failed file upload. |
| How to obtain | Use `3` (default). |
| Example value | `3` |
| Required | Yes |

### MEMORYVAULT_UPLOAD_CHUNK_SIZE

| | |
|---|---|
| What it does | Chunk size in bytes for Plan B (chunked PHP proxy) uploads. |
| How to obtain | Use `8388608` (8 MB). Must be below PHP `upload_max_filesize` and `post_max_size`. |
| Example value | `8388608` |
| Common mistakes | Setting higher than PHP limits — chunks will be rejected. |
| Required | Yes |

### MEMORYVAULT_DRIVE_QUOTA_WARNING_PERCENT

| | |
|---|---|
| What it does | Percentage threshold for storage quota warning in admin dashboard. |
| How to obtain | Use `80` (default). |
| Example value | `80` |
| Required | Yes |

### MEMORYVAULT_UPLOAD_STRATEGY

| | |
|---|---|
| What it does | Selects the upload architecture. Either `plan_a` (direct browser→Drive) or `plan_b` (chunked PHP proxy). |
| How to obtain | Start with `plan_b`. Submit Phase 0 validations (Section 14) to determine the final choice. |
| Example value | `plan_b` |
| Common mistakes | Setting to `plan_a` without Phase 0 CORS validation — uploads may fail. |
| Required | Yes |

### ADMIN_EMAIL

| | |
|---|---|
| What it does | The admin account email. Used to log in to the admin dashboard. |
| How to obtain | Choose your admin email. |
| Example value | `admin@yourdomain.com` |
| Common mistakes | Using a disposable email. |
| Required | Yes |

### ADMIN_PASSWORD

| | |
|---|---|
| What it does | The admin account password. Must be at least 12 characters. |
| How to obtain | Create a strong password. |
| Example value | `SecureAdmin2026!` |
| Common mistakes | Using a weak password — the seeder will refuse to run. |
| Required | Yes |

---

# 5. APP_KEY

## 5.1 What It Is

`APP_KEY` is a 32-byte encryption key (base64-encoded as `base64:...`). Laravel uses it to:
- Encrypt session data before storing it in the database
- Sign cookies
- Encrypt queued job payloads

## 5.2 How to Generate It

### On Your Local Machine

If PHP is installed locally:
```bash
php -r "echo 'base64:' . base64_encode(random_bytes(32));"
```

Copy the output (including `base64:`) into your `.env` file:
```
APP_KEY=base64:CUUANwl3YWHwUTUvStDqtN8jvYY5FLUn6v4PyI4Q3Ws=
```

### On Hostinger

Via SSH:
```bash
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan key:generate
```

This will automatically update the `.env` file with a freshly generated key.

> **Important**: If you use `key:generate` on the server, make sure `.env` already exists (copied from `.env.example`).

## 5.3 What Happens If It Changes

- **All existing sessions become invalid** — everyone is logged out.
- **Encrypted session data becomes unreadable** — the `sessions` table should be truncated if this happens.
- **No business data is lost** — submissions, media, and events are NOT encrypted with `APP_KEY`.

## 5.4 Why It Must Never Change After Production Deployment

After deployment, changing `APP_KEY` will:
1. Log out every admin and client immediately.
2. Make the existing `sessions` table data useless (it will cause decryption errors).
3. Require running `TRUNCATE TABLE sessions;` in phpMyAdmin.

**Action**: Once set, **never change `APP_KEY`**. Back it up securely.

---

# 6. MEMORYVAULT_CLIENT_KEY

## 6.1 What It Is

`MEMORYVAULT_CLIENT_KEY` is a separate 32-byte encryption key used exclusively for encrypting and decrypting client passwords. It must NOT be the same as `APP_KEY`.

**Why it's separate**: Client passwords are reversible (the admin creates them and must be able to view/reset them). `APP_KEY` encrypts sessions, which are disposable. `MEMORYVAULT_CLIENT_KEY` encrypts permanent credential data. Keeping them separate means rotating one does not affect the other.

## 6.2 How to Generate It

```bash
php -r "echo 'base64:' . base64_encode(random_bytes(32));"
```

The output looks like:
```
base64:gEIYVTP8yYaPDiMP69fK4sfm4J7nUZuH55YXrJCOU4A=
```

## 6.3 Required Length and Format

- Must be exactly 32 bytes (256 bits) when decoded.
- Must be base64-encoded with the `base64:` prefix.
- AES-256-CBC encryption requires exactly 32 bytes.

**Common mistake**: Using a short string like `base64:dGVzdA==` — the `Encrypter` class will throw `Unsupported cipher or incorrect key length`.

## 6.4 Why It Must Be Backed Up Safely

**If you lose `MEMORYVAULT_CLIENT_KEY`:**
- All existing client passwords become permanently unrecoverable.
- You must reset every client's password manually through the admin dashboard.
- No data loss for submissions/media, but all client authentications break.

**Backup recommendation**: Store `MEMORYVAULT_CLIENT_KEY` and `APP_KEY` together in:
- A password manager (1Password, Bitwarden, KeePass)
- An encrypted file on a USB drive
- A physical safe (printed on paper)

**Warning**: Rotating `MEMORYVAULT_CLIENT_KEY` requires decrypting all client passwords with the old key and re-encrypting with the new key. There is no built-in rotation command — it must be done manually. Do not lose this key.

---

# 7. Google Cloud Setup

## 7.1 Create a Google Cloud Project

1. Go to https://console.cloud.google.com
2. Sign in with your Google account.
3. Click the project dropdown at the top → **New Project**.
4. Enter a project name (e.g., `Memory Vault Storage`).
5. Click **Create**.
6. Wait for the project to be created (a few seconds).
7. Select the project from the dropdown.

> **Screenshot: Google Cloud Console — New Project**

## 7.2 Enable Google Drive API

1. In the left sidebar, click **APIs & Services** → **Library**.
2. Search for "Google Drive API".
3. Click **Google Drive API** from the results.
4. Click **Enable**.
5. Wait for it to enable (a few seconds).

> **Screenshot: Enable Google Drive API**

## 7.3 Create a Service Account

1. In the left sidebar, click **APIs & Services** → **Credentials**.
2. Click **+ CREATE CREDENTIALS** → **Service account**.
3. Fill in:
   - **Service account name**: `memory-vault-storage`
   - **Service account ID**: Auto-generated (leave as-is)
   - **Description**: `Service account for Memory Vault Drive storage`
4. Click **Create and Continue**.
5. On the "Grant this service account access" step — skip (click **Continue**).
6. On the "Grant users access" step — skip (click **Done**).

> **Screenshot: Create Service Account**

## 7.4 Create a JSON Key

1. In **Credentials**, click the service account you just created (under "Service Accounts").
2. Go to the **Keys** tab.
3. Click **ADD KEY** → **Create new key**.
4. Select **JSON**.
5. Click **Create**.
6. A JSON file will download to your computer. **This file is your credential — protect it.**

> **Screenshot: Create JSON Key**

## 7.5 Find the Service Account Email

1. In the downloaded JSON file, find the `"client_email"` field.
2. It looks like: `memory-vault-storage@your-project.iam.gserviceaccount.com`
3. **Copy this email** — you need it in the next steps.

## 7.6 Mode A: Personal Google Drive (Standard Google Account)

Use this mode if you have a regular Gmail account (no Google Workspace).

### Create a Root Folder

1. Go to https://drive.google.com
2. Create a new folder (e.g., `Memory Vault`).
3. Name it whatever you want — this is the parent folder for all event folders.

### Share the Folder With the Service Account

1. Right-click the folder → **Share** → **Share**.
2. Paste the service account email from Section 7.5.
3. Set permission to **Editor**.
4. Uncheck "Notify people" (the service account has no inbox).
5. Click **Share**.

> **Screenshot: Share Drive Folder with Service Account**

**Important**: The service account will NOT receive an email invitation. Sharing is silent. The service account instantly gains access.

### Get the Folder ID

1. Open the folder in Google Drive.
2. Look at the URL in your browser:
   ```
   https://drive.google.com/drive/folders/1aBcDeFgHiJkLmNoPqRsTuVwXyZ1234567
   ```
3. The string after `/folders/` is the **Folder ID**.
4. Copy this ID.

### Set Environment Variables

```env
GOOGLE_SERVICE_ACCOUNT_KEY=<base64-encoded JSON from Section 7.8>
GOOGLE_SHARED_DRIVE_ID=
GOOGLE_STORAGE_ROOT_FOLDER_ID=1aBcDeFgHiJkLmNoPqRsTuVwXyZ1234567
```

**Key point**: Leave `GOOGLE_SHARED_DRIVE_ID` EMPTY for personal Drive mode.

## 7.7 Mode B: Google Shared Drive (Google Workspace Only)

Use this mode if you have a Google Workspace account with Shared Drives.

### Create a Shared Drive

1. Go to https://drive.google.com
2. Click **Shared drives** in the left sidebar.
3. Click **+ New** to create a Shared Drive.
4. Name it (e.g., `Memory Vault Storage`).
5. Click **Create**.

### Add the Service Account to the Shared Drive

1. Open the Shared Drive.
2. Click **Manage members** (or the people icon).
3. Click **Add members**.
4. Paste the service account email from Section 7.5.
5. Set role to **Content Manager** or higher.
6. Uncheck "Notify people".
7. Click **Send** / **Add**.

> **Screenshot: Add Service Account to Shared Drive**

### Get the Shared Drive ID

1. Open the Shared Drive in Google Drive.
2. The URL looks like:
   ```
   https://drive.google.com/drive/folders/0ABCD1234EFGHI
   ```
3. The string after `/folders/` is the **Shared Drive ID**.
4. Copy this ID.

### Set Environment Variables

```env
GOOGLE_SERVICE_ACCOUNT_KEY=<base64-encoded JSON from Section 7.8>
GOOGLE_SHARED_DRIVE_ID=0ABCD1234EFGHI
GOOGLE_STORAGE_ROOT_FOLDER_ID=
```

**Key point**: Leave `GOOGLE_STORAGE_ROOT_FOLDER_ID` EMPTY for Shared Drive mode. Event folders will be created at the Shared Drive root.

## 7.8 Encode the Service Account JSON

You must convert the downloaded JSON file into a base64 string.

### Method 1: Base64 Encoding (Recommended)

On Windows (PowerShell):
```powershell
[Convert]::ToBase64String([IO.File]::ReadAllBytes("C:\path\to\service-account.json"))
```

On Mac/Linux:
```bash
base64 -w 0 service-account.json
```

The output is a very long string. Put it in `.env` with the `base64:` prefix:
```env
GOOGLE_SERVICE_ACCOUNT_KEY=base64:eyJ0eXBlIjoi...
```

### Method 2: Direct File Path (Alternative)

1. Upload the JSON file to a secure location on Hostinger (NOT in `public_html` or `public/`):
   ```bash
   mkdir ~/keys
   ```
2. Upload the JSON file to `~/keys/service-account.json`.
3. In `.env`, set:
   ```env
   GOOGLE_SERVICE_ACCOUNT_KEY_FILE=/home/u123456789/keys/service-account.json
   ```
4. Leave `GOOGLE_SERVICE_ACCOUNT_KEY` empty.

**Important**: Never place the JSON file inside a web-accessible folder.

## 7.9 Common Mistakes

| Mistake | Symptom | Fix |
|---|---|---|
| Not sharing the folder with the service account | Drive health check fails | Share folder with service account email as Editor |
| Using the folder NAME instead of ID | Drive health check fails | Copy the ID from the URL, not the visible name |
| Setting both `SHARED_DRIVE_ID` and `ROOT_FOLDER_ID` | App uses Shared Drive mode (ignores root folder) | Leave one empty based on the mode you want |
| Not enabling Drive API | Authentication fails | Enable Google Drive API in Cloud Console |
| Putting JSON file in public_html | Security risk — file is downloadable | Place it outside web root or use base64 encoding |

## 7.10 Verify the Setup

After configuring `.env` (Section 4) and running migrations (Section 9), verify:

```bash
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan tinker --execute="echo json_encode(app(App\Services\StorageService::class)->checkHealth());"
```

Expected output: `true`

Or visit:
```
https://yourdomain.com/health
```

Expected: `"drive":true` in the JSON response.

---

# 8. SMTP Setup

## 8.1 Obtain SMTP Credentials

Hostinger provides email accounts. To use Hostinger's SMTP:

1. In hPanel, go to **Email** → **Email Accounts**.
2. Create an email account (e.g., `noreply@yourdomain.com`).
3. Note the SMTP settings (usually shown in hPanel):
   - **SMTP Host**: `smtp.hostinger.com` (check your hPanel for exact value)
   - **SMTP Port**: `465` (SSL) or `587` (TLS)
   - **Username**: `noreply@yourdomain.com`
   - **Password**: The email account password you set

Alternatively, use a third-party SMTP provider (SendGrid, Mailgun, Postmark, etc.) and follow their setup instructions.

## 8.2 Configure .env

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=your_email_password
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Memory Vault"
```

**Note**: Use `ssl` with port `465`, or `tls` with port `587`. Don't mix them.

## 8.3 Test Email

Via SSH:
```bash
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan tinker --execute="
Mail::raw('Test from Memory Vault', function(\$m) { \$m->to('your-personal-email@gmail.com')->from('noreply@yourdomain.com')->subject('SMTP Test'); });
echo 'Sent';
"
```

Expected output: `Sent`

Check your inbox. If you don't receive the email:
1. Check `storage/logs/laravel-*.log` for errors.
2. Try switching `ssl`/`tls` and port `465`/`587`.
3. Use `MAIL_MAILER=log` temporarily — emails are written to the log file instead of sent. This confirms the app works, and the issue is with SMTP.

## 8.4 Troubleshooting SMTP

| Symptom | Cause | Fix |
|---|---|---|
| `Connection refused` | Wrong port | Try 465 with ssl, or 587 with tls |
| `Authentication failed` | Wrong username/password | Use the full email address as username |
| `Sender rejected` | `MAIL_FROM_ADDRESS` not on this domain | Use an email on your own domain |
| Timeout | Firewall blocking SMTP port | Contact Hostinger support |

---

# 9. Database Migration

## 9.1 Run Migrations

Via SSH:
```bash
cd ~/memoryvault
/opt/alt/php83/usr/bin/php artisan migrate --force
```

**`--force`** is required because we are in production environment. Without it, Laravel asks for confirmation interactively.

## 9.2 Expected Output

```
INFO  Preparing database.

  0001_01_01_000001_create_admins_table .............................. DONE (x.xx ms)
  0001_01_01_000002_create_events_table ............................... DONE (x.xx ms)
  0001_01_01_000003_create_clients_table .............................. DONE (x.xx ms)
  0001_01_01_000004_create_submissions_table .......................... DONE (x.xx ms)
  0001_01_01_000005_create_media_table ................................ DONE (x.xx ms)
  0001_01_01_000006_create_drive_cleanup_jobs_table .................... DONE (x.xx ms)
  0001_01_01_000007_create_sessions_table .............................. DONE (x.xx ms)

  INFO  Running migrations.
```

## 9.3 Verify Migration Success

Via phpMyAdmin:
1. Go to **Databases** → select your database.
2. You should see 8 tables:
   - `admins`
   - `clients`
   - `drive_cleanup_jobs`
   - `events`
   - `media`
   - `migrations`
   - `sessions`
   - `submissions`

> **Screenshot: phpMyAdmin — Tables After Migration**

## 9.4 Storage Link

```bash
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan storage:link
```

Expected output:
```
   INFO  The [public/storage] link has been connected to [storage/app/public].
```

## 9.5 Rollback

**Warning**: Rollback deletes tables and all data. Only do this if the migration is broken and no data has been entered.

```bash
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan migrate:rollback --force
```

This drops the last batch of migrations. Run `migrate --force` again after fixing the issue.

---

# 10. Seeder

## 10.1 What Gets Created

The `AdminSeeder` creates a single admin user in the `admins` table:
- `email` = the value of `ADMIN_EMAIL` in `.env`
- `password` = bcrypt-hashed value of `ADMIN_PASSWORD` in `.env`

No other data is seeded. No test events, no fake submissions, no demo data.

## 10.2 Run the Seeder

```bash
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan db:seed --class=AdminSeeder --force
```

## 10.3 Expected Output

```
   INFO  Seeding database.
```

If `ADMIN_EMAIL` or `ADMIN_PASSWORD` is missing or the password is less than 12 characters, you will see:
```
RuntimeException: ADMIN_EMAIL and ADMIN_PASSWORD must be configured in .env before seeding.
```
or
```
RuntimeException: ADMIN_PASSWORD must be at least 12 characters long.
```

**Fix**: Set the variables in `.env` and try again.

## 10.4 How Admin Login Works

1. Go to `https://yourdomain.com/admin/login`
2. Enter the `ADMIN_EMAIL` and `ADMIN_PASSWORD` from your `.env`.
3. You are redirected to the admin dashboard at `https://yourdomain.com/admin`.

## 10.5 How to Change Admin Credentials Later

The admin password is stored as a bcrypt hash in the `admins` table. To change it:

Via SSH:
```bash
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan tinker --execute="
\App\Models\Admin::where('email', 'old@email.com')->update(['password' => bcrypt('NewPassword123!')]);
echo 'Updated';
"
```

Or change `ADMIN_EMAIL`/`ADMIN_PASSWORD` in `.env` and re-run the seeder:
```bash
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan db:seed --class=AdminSeeder --force
```

The seeder uses `updateOrCreate` — it updates the existing admin if the email matches, or creates a new one.

---

# 11. Build Assets

## 11.1 Install NPM Dependencies

**On your local machine** (before uploading to Hostinger):
```bash
cd memory-vault
npm install
```

This installs all packages defined in `package.json`.

## 11.2 npm ci vs npm install

| Command | When to Use |
|---|---|
| `npm install` | First time installing, or when adding new packages |
| `npm ci` | Clean install from `package-lock.json` — faster and more reproducible. Use when the lockfile exists and you want a clean install. |

**For production builds**: Use `npm ci` if `package-lock.json` exists. Otherwise use `npm install`.

## 11.3 Build Assets

```bash
npm run build
```

This compiles all JavaScript and CSS files and places them in `public/build/`.

## 11.4 Expected Output

```
  public/build/manifest.json               1.87 kB │ gzip:  0.43 kB
  public/build/assets/admin-XXX.js           0.07 kB │ gzip:  0.09 kB
  public/build/assets/app-XXX.js           593.36 kB │ gzip: 78.14 kB
  public/build/assets/upload-XXX.js         9.68 kB │ gzip:  3.66 kB
  ...
✓ built in 2.7s
```

The `manifest.json` file maps entry points to compiled filenames. **This entire `public/build/` directory must be uploaded to Hostinger.**

## 11.5 When to Rebuild

Rebuild assets only when you have modified JavaScript or CSS files. If you only changed PHP or Blade views, no rebuild is needed.

---

# 12. Caching

## 12.1 Cache Commands (Production)

After deploying or updating `.env`, run these commands to cache configuration for performance:

```bash
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan config:cache
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan route:cache
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan view:cache
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan optimize
```

## 12.2 What Each Command Does

| Command | What It Caches | Benefit |
|---|---|---|
| `config:cache` | All configuration files merged into one PHP file | Faster config loading |
| `route:cache` | All routes compiled into one PHP file | Faster route matching |
| `view:cache` | All Blade templates compiled to PHP | Faster view rendering |
| `optimize` | Runs all three above | Combined optimization |

## 12.3 When to Clear Caches

**Always clear caches before changing `.env` or updating code:**

```bash
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan optimize:clear
```

This clears:
- Config cache
- Route cache
- View cache
- Event cache
- Compiled classes

**After clearing and making changes, re-cache:**
```bash
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan optimize
```

## 12.4 Common Mistake

| Mistake | Symptom | Fix |
|---|---|---|
| Changing `.env` without clearing config cache | Old values are still used | `artisan config:clear` then `artisan config:cache` |
| Clearing cache but not re-caching | Slightly slower performance | Run `artisan optimize` |

---

# 13. Health Checks

After deployment and configuration, verify each system component.

## 13.1 Database

```bash
curl https://yourdomain.com/health
```

Expected JSON:
```json
{
  "status": "healthy",
  "database": true,
  "drive": true,
  "smtp": true
}
```

If `database` is `false`:
- Check `DB_*` credentials in `.env`
- Run `php artisan config:clear && php artisan config:cache`
- Verify the database user has permissions on the database

## 13.2 Google Drive

Check `"drive": true` in the health JSON.

If `false`:
- Verify `GOOGLE_SERVICE_ACCOUNT_KEY` or `GOOGLE_SERVICE_ACCOUNT_KEY_FILE`
- Verify `GOOGLE_SHARED_DRIVE_ID` or `GOOGLE_STORAGE_ROOT_FOLDER_ID`
- Verify the folder is shared with the service account email (Section 7)
- Check `storage/logs/laravel-*.log` for error details

## 13.3 SMTP

Check `"smtp": true` in the health JSON.

If `false`:
- Check `MAIL_*` settings in `.env`
- The app still works without SMTP — health warnings are non-blocking
- Consider setting `MAIL_MAILER=log` temporarily

## 13.4 Upload Page

1. As admin, create an event (Section 16).
2. Copy the upload link from the event detail page.
3. Open the link in a new browser tab (incognito mode recommended).
4. Verify:
   - The event title is displayed.
   - The name input is visible and active.
   - After entering a valid name (4+ characters), the upload area appears.
   - Selecting a file triggers the upload process (the file appears in the list).

## 13.5 Admin Login

1. Go to `https://yourdomain.com/admin/login`
2. Enter admin email and password.
3. Verify redirect to the dashboard.
4. Verify the dashboard shows event list (empty initially).

## 13.6 Client Login

1. Go to `https://yourdomain.com/client/login`
2. Enter the client email and password (created when you created the event).
3. Verify redirect to the client dashboard.
4. Verify the dashboard shows event stats.

## 13.7 QR Code Generation

1. Create an event (Section 16).
2. Go to the event detail page in the admin dashboard.
3. Check if the QR section shows "Download QR" button.
4. If QR was not generated during event creation, click "Generate QR Code".
5. If both fail, check `storage/logs/laravel-*.log` — the QR library or fallback API may have failed.

---

# 14. Phase 0 Validation

Phase 0 validates infrastructure assumptions before going live. Follow each test carefully.

## V1: Google Drive CORS

**Purpose**: Determine if Plan A (direct browser→Drive upload) is viable.

**Steps**:
1. Set `MEMORYVAULT_UPLOAD_STRATEGY=plan_a` in `.env`.
2. Run `php artisan config:clear && php artisan config:cache`.
3. Open the upload page for an existing event.
4. Select a small file (~1 MB) and observe the browser DevTools Console.
5. Check for CORS errors.

**Expected**:
- **Pass**: No CORS errors, file uploads successfully → Plan A is eligible.
- **Fail**: CORS errors in console → Plan A is not viable, use Plan B.

**If it fails**: Revert to `plan_b` in `.env`, clear and re-cache config.

## V2: Hostinger PHP Version

**Steps**:
```bash
/opt/alt/php83/usr/bin/php -v
```

**Expected**: `PHP 8.3.x`

**If it fails**: Change PHP version in hPanel → Advanced → PHP Configuration.

## V3: Composer

**Steps**:
```bash
composer --version
```

**Expected**: `Composer version 2.x`

**If it fails**: If Composer is not on Hostinger, upload `vendor/` directory from your local machine.

## V4: SSH Access

**Steps**:
```bash
ssh u123456789@your-domain.com -p 65022
```

**Expected**: Shell access works.

**If it fails**: Use Hostinger File Manager and Terminal tool instead.

## V5: Google Drive Storage Setup

**Steps**:
1. As admin, create a test event.
2. Open Google Drive (the shared folder or your shared folder).
3. Verify a folder named `evt_{slug}` was created.

**Expected**: Event folder exists in Drive.

**If it fails**: Verify service account credentials, folder sharing, and folder ID in `.env`.

## V6: PHP Upload Limits

**Steps**:
```bash
/opt/alt/php83/usr/bin/php -i | grep upload_max_filesize
/opt/alt/php83/usr/bin/php -i | grep post_max_size
```

**Expected**: `upload_max_filesize >= 20M`, `post_max_size >= 25M`

**If it fails**: Change limits in hPanel → Advanced → PHP Options. Or reduce `MEMORYVAULT_UPLOAD_CHUNK_SIZE`.

## V7: Cron Support

**Steps**:
1. Verify the cron job is configured (Section 2.6).
2. Wait 10 minutes.
3. Check `storage/logs/laravel-*.log` for entries from `memoryvault:cleanup`.

**Expected**: Log entries from scheduled commands appear every 5 minutes.

**If it fails**: Verify the cron job command path. Try running it manually: `/opt/alt/php83/usr/bin/php ~/memoryvault/artisan schedule:run`.

## V8: Laravel Install

**Steps**:
```bash
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan migrate --force
```

**Expected**: All 7 migrations run successfully.

**If it fails**: Check database credentials in `.env`.

## V9: set_time_limit(0)

**Steps**:
```bash
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan tinker --execute="set_time_limit(0); sleep(10); echo 'OK';"
```

**Expected**: `OK` (after 10 seconds)

**If it fails**: `set_time_limit(0)` is ignored. ZIP downloads for large events may fail. Reduce `max_event_zip_files` in `config/memoryvault.php`.

## V10: Drive Quota Readable

**Steps**:
```bash
/opt/alt/php83/usr/bin/php ~/memoryvault/artisan tinker --execute="echo json_encode(app(App\Services\StorageService::class)->getQuotaInfo());"
```

**Expected**: JSON with `limit`, `usage`, `usage_in_drive` fields, or `null`.

**If `null`**: The `about.get` API may be restricted. Remove quota alerts from the admin and document the limitation.

## V11: Large Plan A Upload

**Steps**:
1. Set `MEMORYVAULT_UPLOAD_STRATEGY=plan_a`.
2. Clear and re-cache config.
3. Upload a ~50 MB file from the browser.
4. Monitor the progress bar and completeness.

**Expected**: Progress bar updates smoothly, file completes upload, and the server verifies it.

**If it fails**: Use Plan B (`plan_b`).

## V12: ZIP Streaming Endurance

**Steps**:
1. Create an event with 50–100 mixed files (photos and videos).
2. From the client dashboard, click "Download All" (event ZIP).
3. Monitor: does the download start within 2 seconds? Does it complete? Does server memory stay low?

**Expected**: Download starts quickly and completes successfully.

**If it fails**: Reduce `max_event_zip_files` and `max_event_zip_bytes` in `config/memoryvault.php`. Recommend per-submission downloads.

## V13: Output Buffering

**Steps**:
1. Start a ZIP download (same as V12).
2. Check that the download starts within 2 seconds (not buffered).
3. Monitor server memory (if possible).

**Expected**: Immediate download start, low memory.

**If it fails**: The PHP-side `Content-Encoding: identity` safeguard should handle this. If not, check for Apache mod_deflate on the download route.

## V14: Refresh Recovery

**Steps**:
1. Open the upload page, enter name, select a file.
2. Wait for upload to start.
3. Refresh the page (F5).
4. Verify the same `submission_id` is reused (check Network tab for `/api/submissions/start` response).
5. Verify no duplicate draft submission is created (check the `submissions` table in phpMyAdmin).

**Expected**: Same submission ID, no duplicates.

**If it fails**: Verify `localStorage` is available (not blocked by browser settings).

---

# 15. Choosing Plan A vs Plan B

## 15.1 Evaluation Criteria

| Criterion | Plan A | Plan B |
|---|---|---|
| V1 (CORS) must pass | Required | Not needed |
| V11 (Large upload) must pass | Required | Required (for chunk reliability) |
| Hostinger bandwidth | Minimal (browser→Drive direct) | Full (browser→PHP→Drive) |
| Complexity | Lower | Higher |
| Security | Resumable URI exposed to browser | Resumable URI stays server-side |

## 15.2 Decision

| V1 Result | V11 Result | Decision |
|---|---|---|
| Pass | Pass | Plan A |
| Pass | Fail | Plan B |
| Fail | Any | Plan B |

## 15.3 How to Permanently Remove the Unused Strategy

After deciding:
1. Set the final strategy in `.env`: `MEMORYVAULT_UPLOAD_STRATEGY=plan_a` or `plan_b`.
2. Contact the developer to remove the unused code path:
   - Remove `upload/complete` route and controller method (if Plan B selected)
   - Remove `upload/chunk` route and controller method (if Plan A selected)
   - Remove the corresponding JavaScript upload path in `upload-queue.js`
   - Remove `resumable_uri` column usage (if Plan A selected) or folder-lookup logic (if Plan B selected)
   - Remove the `upload_strategy` config switch
3. After removal: clear caches, rebuild, and re-deploy.

---

# 16. First Production Event

## 16.1 Log In as Admin

1. Go to `https://yourdomain.com/admin/login`
2. Enter your admin credentials.
3. You should see the dashboard with overview stats (all zeros).

## 16.2 Create an Event

1. Click **Create Event** (top right).
2. Fill in the form:

| Field | What to Enter | Example |
|---|---|---|
| Event Title | The name of the event | `Wedding - Ahmed & Sara` |
| Description | Optional description for internal reference | `Summer wedding photos` |
| Event Type | The type of event | `Wedding` |
| Upload Deadline | Optional deadline after which uploads are blocked | `2026-08-15 23:59` |
| Allow Photos | Check if guests can upload photos | Checked |
| Allow Videos | Check if guests can upload videos | Checked |
| Allow Voice | Check if guests can record voice messages | Checked |
| Allow Messages | Check if guests can write text messages | Checked |
| Client Name | The name of the client who will view submissions | `Ahmed Khan` |
| Client Email | The email the client uses to log in | `ahmed@example.com` |
| Client Password | A password you share with the client (min 8 chars) | `ClientAccess2026!` |

3. Click **Create Event**.

## 16.3 What Happens Behind the Scenes

1. A Google Drive folder is created (e.g., `evt_wedding-ahmed-sara-abc12345`).
2. A database record is created in the `events` table.
3. A database record is created in the `clients` table with the encrypted password.
4. An upload token and slug are generated automatically.
5. A QR code is generated and uploaded to the Drive folder.

## 16.4 Verify the Event

1. On the event detail page, verify:
   - The upload link is displayed and can be copied.
   - The QR code section shows "Download QR" button.
   - Recent Submissions is empty (no submissions yet).
2. Open the upload link in a new tab — verify the upload page loads.
3. In a separate browser tab, go to `/client/login`:
   - Enter the client email and password.
   - Verify redirect to the client dashboard.
4. Check the Google Drive folder — verify the event folder and `qr_code.png` exist.

## 16.5 Test the Full Flow (Optional but Recommended)

1. Open the upload link on your phone.
2. Enter a test name.
3. Upload a test photo.
4. Write a test message.
5. Click Submit.
6. In the client dashboard, verify the submission appears with the photo and message.
7. Download the submission ZIP.
8. Verify the ZIP contains the photo, `message.txt`, and `_manifest.txt`.

---

# 17. Backup

## 17.1 What to Back Up

| Item | How | When |
|---|---|---|
| **MySQL database** | `mysqldump` or Hostinger backup tool | Before any update, weekly |
| **Google Drive files** | Google Drive retains data; ensure Google account is secure | Verify monthly |
| **`.env` file** | Copy to a secure offline location (password manager) | After any change |
| **`APP_KEY`** | Inside `.env` — back up separately too | Once after deployment |
| **`MEMORYVAULT_CLIENT_KEY`** | Inside `.env` — back up separately too | Once after deployment |
| **Application code** | Keep the Git repository available | After each release |
| **`public/build/`** | Compiled assets — keep with the code | After each build |

## 17.2 Database Backup via Hostinger

1. In hPanel, go to **Databases** → **phpMyAdmin**.
2. Select the database.
3. Click **Export**.
4. Select **Quick** → **Go**.
5. A `.sql` file downloads.

Alternatively via SSH:
```bash
mysqldump -u DB_USERNAME -p DB_DATABASE > backup_$(date +%Y%m%d).sql
```

## 17.3 Restore Procedure

1. Upload the backup `.sql` file to Hostinger.
2. Via phpMyAdmin: select database → **Import** → choose file → **Go**.
3. Or via SSH:
   ```bash
   mysql -u DB_USERNAME -p DB_DATABASE < backup_20260714.sql
   ```
4. Restore the `.env` file if it was lost.
5. Run `php artisan config:cache && php artisan route:cache && php artisan view:cache`.

## 17.4 What Happens If You Lose APP_KEY

- All sessions are invalidated (everyone is logged out).
- Truncate the `sessions` table: `TRUNCATE TABLE sessions;`
- Generate a new key and update `.env`.
- No business data is lost.

## 17.5 What Happens If You Lose MEMORYVAULT_CLIENT_KEY

- All client passwords become permanently unrecoverable.
- You must reset every client's password through the admin dashboard.
- No submissions or media are lost.

---

# 18. Updating the Application

## 18.1 Safe Update Procedure

1. **Back up the database** (Section 17.2).
2. **Back up `.env`** (copy it to a safe location).
3. Put the app in maintenance mode:
   ```bash
   /opt/alt/php83/usr/bin/php ~/memoryvault/artisan down
   ```
4. Upload the new code (overwrite existing files) OR `git pull` if using Git.
5. Upload fresh `public/build/` assets if JS/CSS changed.
6. Install/update dependencies if needed:
   ```bash
   cd ~/memoryvault
   /opt/alt/php83/usr/bin/php composer.phar install --no-dev --optimize-autoloader
   ```
   (Only if `composer.json` changed.)
7. Run migrations:
   ```bash
   /opt/alt/php83/usr/bin/php artisan migrate --force
   ```
8. Clear and re-cache:
   ```bash
   /opt/alt/php83/usr/bin/php artisan optimize:clear
   /opt/alt/php83/usr/bin/php artisan config:cache
   /opt/alt/php83/usr/bin/php artisan route:cache
   /opt/alt/php83/usr/bin/php artisan view:cache
   /opt/alt/php83/usr/bin/php artisan optimize
   ```
9. Bring the app back:
   ```bash
   /opt/alt/php83/usr/bin/php ~/memoryvault/artisan up
   ```
10. Verify: visit `/health`, admin login, and upload page.

## 18.2 What NOT to Do

- Do NOT run `migrate:rollback` unless you are certain no data will be lost.
- Do NOT delete the `storage/` folder — it contains logs and cached files.
- Do NOT delete the `vendor/` folder without running `composer install` afterward.
- Do NOT change `APP_KEY` or `MEMORYVAULT_CLIENT_KEY` unless absolutely necessary (Section 5, 6).
- Do NOT upload `node_modules/` to the server.

---

# 19. Troubleshooting

## White Screen (No Output)

| Symptom | Probable Causes | Solution |
|---|---|---|
| Completely blank page | PHP fatal error, display_errors off | Check `storage/logs/laravel-*.log`. Set `APP_DEBUG=true` temporarily to see the error, then set it back to `false`. |
| Blank admin or client page | Missing compiled views | Run `php artisan view:clear && php artisan view:cache` |
| Blank upload page | Missing Vite assets | Verify `public/build/manifest.json` exists. Rebuild assets: `npm run build` locally and upload `public/build/`. |

## 500 Internal Server Error

| Symptom | Probable Causes | Solution |
|---|---|---|
| 500 on every page | `.env` missing or misconfigured | Verify `.env` exists and all required values are set. Run `php artisan config:clear && php artisan config:cache`. |
| 500 on every page | Storage permissions | `chmod -R 775 storage bootstrap/cache` |
| 500 on every page | PHP version mismatch | Verify PHP 8.3 is active in hPanel. |
| 500 after changing `.env` | Config cache stale | `php artisan config:clear && php artisan config:cache` |
| 500 on admin login | `admins` table empty | Run `php artisan db:seed --class=AdminSeeder --force` |

## Google Drive Authentication Failed

| Symptom | Probable Causes | Solution |
|---|---|---|
| `"drive": false` in health | Wrong service account key | Verify `GOOGLE_SERVICE_ACCOUNT_KEY` is valid base64 of the JSON file. |
| `"drive": false` in health | Folder not shared with service account | Share the Drive folder with the service account email as Editor. |
| `"drive": false` in health | Wrong folder ID | Copy the ID from the Drive URL, not the folder name. |
| `"drive": false` in health | Drive API not enabled | Enable Google Drive API in Cloud Console. |
| Drive errors in log | Personal Drive mode but `GOOGLE_SHARED_DRIVE_ID` set | Leave `GOOGLE_SHARED_DRIVE_ID` empty for personal Drive. |
| `Unsupported cipher` | Invalid `MEMORYVAULT_CLIENT_KEY` | Generate a proper 32-byte key: `php -r "echo 'base64:' . base64_encode(random_bytes(32));"` |

## Upload Failed

| Symptom | Probable Causes | Solution |
|---|---|---|
| Upload init returns 422 | File type not allowed | Check event settings (allow photos/videos). |
| Upload init returns 403 | Missing nonce header | Verify the upload page loaded correctly and nonce is set. |
| Upload chunk returns 429 | Rate limit hit | Wait and retry. Check `throttle:upload-chunks` rate. |
| Upload never completes (Plan B) | PHP `upload_max_filesize` too low | Check V6. Increase in hPanel or reduce chunk size. |
| File stuck in "uploading" state | Network timeout or Drive API error | Check `storage/logs/laravel-*.log`. Manually run `php artisan memoryvault:cleanup` to clear stale drafts. |
| Upload page shows "closed" | Event is closed or deadline passed | Reopen the event in admin dashboard. |

## QR Not Generated

| Symptom | Probable Causes | Solution |
|---|---|---|
| No QR code on event page | QR generation failed during event creation | Click "Generate QR Code" on the event page. |
| "Regenerate QR" fails | GD extension missing or Drive API error | Verify `gd` is enabled in hPanel PHP Options. Check logs. |
| QR code downloads but is blank | QR library failed | Verify `chillerlan/php-qrcode` is installed: `composer show chillerlan/php-qrcode`. |

## Storage Full

| Symptom | Probable Causes | Solution |
|---|---|---|
| Admin storage shows warning | Drive quota >80% | Delete old events. Ask affected users to download what they need. |
| Uploads fail with quota error | Google Drive quota exhausted | Free up space in Google Drive or upgrade storage plan. |
| `getQuotaInfo` returns null | Quota API not readable | See V10. Use the fallback: manually check Drive storage in the Google Drive web interface. |

## SMTP Failed

| Symptom | Probable Causes | Solution |
|---|---|---|
| `"smtp": false` in health | SMTP credentials wrong | Verify `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`. |
| No emails sent | `MAIL_MAILER=log` | Change to `smtp`. |
| Emails not received | Spam filter | Check spam folder. Set `MAIL_FROM_ADDRESS` to an address on your domain. |
| `Connection refused` | Wrong port | Try 465 with ssl or 587 with tls. |

## Migration Failed

| Symptom | Probable Causes | Solution |
|---|---|---|
| `Access denied` | Wrong DB credentials | Verify `DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE` in `.env`. Include Hostinger username prefix. |
| `Table already exists` | Database not empty | Drop all tables (phpMyAdmin → select all → Drop) or use a new database. |
| `Unknown column` | Code/version mismatch | Ensure the uploaded code matches the latest release. |

## Cache Issues

| Symptom | Probable Causes | Solution |
|---|---|---|
| Old `.env` values used | Config cache stale | `php artisan config:clear && php artisan config:cache` |
| 404 on a valid route | Route cache stale | `php artisan route:clear && php artisan route:cache` |
| Old Blade output | View cache stale | `php artisan view:clear && php artisan view:cache` |
| Everything stale | All caches stale | `php artisan optimize:clear && php artisan optimize` |

## Permission Issues

| Symptom | Probable Causes | Solution |
|---|---|---|
| `500` with log "cannot write" | Storage not writable | `chmod -R 775 storage bootstrap/cache` |
| Sessions not persisting | `storage/framework/sessions` not writable | `chmod 775 storage/framework/sessions` |
| `storage:link` fails | Public folder already has a `storage` symlink | Remove `public/storage` (it's a symlink) then re-run `php artisan storage:link`. |

## Missing APP_KEY

| Symptom | Solution |
|---|---|
| "No application encryption key has been specified." | Run `php artisan key:generate`. Verify `APP_KEY` is set in `.env`. Run `php artisan config:clear && php artisan config:cache`. |

## Wrong CLIENT_KEY

| Symptom | Solution |
|---|---|
| "Unsupported cipher or incorrect key length" | Generate a proper 32-byte key: `php -r "echo 'base64:' . base64_encode(random_bytes(32));"`. Put it in `.env` as `MEMORYVAULT_CLIENT_KEY`. |
| "Unable to decrypt client password" | The `MEMORYVAULT_CLIENT_KEY` was changed. Reset all client passwords through the admin dashboard. |

## Drive Folder Not Found

| Symptom | Probable Causes | Solution |
|---|---|---|
| Event creation fails with "Unable to create storage folder" | Drive API can't create folder | Verify Drive health. Check service account permissions. Check `GOOGLE_STORAGE_ROOT_FOLDER_ID` is correct. |
| `findFileInFolderByName` returns null | File doesn't exist or wrong folder | Verify the file was uploaded to the correct Drive folder. |
| Event folders appear in wrong Drive | Both Shared Drive ID and Root Folder ID set | Leave one empty. Use only one storage mode. |

## File Manager / Upload Issues

| Symptom | Probable Causes | Solution |
|---|---|---|
| `artisan` command not found | Wrong working directory | `cd ~/memoryvault` before running `php artisan`. |
| `php` not found | Path to PHP differs | Use full path: `/opt/alt/php83/usr/bin/php`. |
| File upload size limit | Hostinger upload_max_filesize | Set in hPanel → Advanced → PHP Options. |

---

# 20. Final Production Checklist

Print this checklist. Do not consider deployment complete until every box is ticked.

## Pre-Deployment

- [ ] Node.js and npm installed on local machine
- [ ] PHP 8.3 CLI installed on local machine
- [ ] Composer installed on local machine
- [ ] Google account ready
- [ ] Hostinger account ready with hosting plan
- [ ] SFTP client installed (or File Manager access)

## Hostinger Configuration

- [ ] PHP version set to 8.3 in hPanel
- [ ] PHP extensions enabled: pdo_mysql, openssl, mbstring, fileinfo, gd, curl, zip, intl, bcmath
- [ ] MySQL database created
- [ ] MySQL database user created with permissions
- [ ] Database credentials recorded (name, username, password, host)
- [ ] Cron job configured (every 5 minutes)
- [ ] SSL enabled (Let's Encrypt)
- [ ] Force HTTPS enabled

## Google Cloud Configuration

- [ ] Google Cloud project created
- [ ] Google Drive API enabled
- [ ] Service account created
- [ ] JSON key downloaded and protected
- [ ] Service account email recorded
- [ ] Storage mode chosen (Personal Drive or Shared Drive)
- [ ] For Personal Drive: root folder created and shared with service account as Editor
- [ ] For Personal Drive: folder ID recorded
- [ ] For Shared Drive: Shared Drive created and service account added as Content Manager
- [ ] For Shared Drive: Shared Drive ID recorded
- [ ] JSON key encoded as base64 (or uploaded to secure server path)

## Build

- [ ] `npm ci` or `npm install` run locally
- [ ] `npm run build` run successfully
- [ ] `public/build/manifest.json` exists and contains 8 entries
- [ ] `composer install --no-dev --optimize-autoloader` run locally (if uploading vendor/)

## Upload

- [ ] All project folders uploaded to `~/memoryvault/`
- [ ] `node_modules/` NOT uploaded
- [ ] `database/database.sqlite` NOT uploaded
- [ ] `.env` NOT uploaded (created manually on server)
- [ ] `.git/` NOT uploaded
- [ ] `public/build/` uploaded with all compiled assets
- [ ] `~/public_html/.htaccess` configured with rewrite rules

## Environment

- [ ] `.env` file created from `.env.example`
- [ ] `APP_NAME` set
- [ ] `APP_ENV=production`
- [ ] `APP_KEY` generated and set
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL` set to production domain (https)
- [ ] `APP_TIMEZONE=UTC`
- [ ] `LOG_CHANNEL=daily`, `LOG_LEVEL=info`, `LOG_DAYS=14`
- [ ] `DB_CONNECTION=mysql` and all DB credentials set
- [ ] `SESSION_DRIVER=database`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`
- [ ] `CACHE_STORE=file`
- [ ] `QUEUE_CONNECTION=sync`
- [ ] All MAIL_* variables set
- [ ] `GOOGLE_SERVICE_ACCOUNT_KEY` or `GOOGLE_SERVICE_ACCOUNT_KEY_FILE` set
- [ ] `GOOGLE_SHARED_DRIVE_ID` or `GOOGLE_STORAGE_ROOT_FOLDER_ID` set (one only)
- [ ] `MEMORYVAULT_CLIENT_KEY` generated and set
- [ ] `MEMORYVAULT_UPLOAD_STRATEGY=plan_b` (temporary default)
- [ ] `ADMIN_EMAIL` set
- [ ] `ADMIN_PASSWORD` set (at least 12 characters)

## Database

- [ ] `php artisan migrate --force` run successfully
- [ ] 8 tables visible in phpMyAdmin (7 app tables + migrations table)
- [ ] `php artisan db:seed --class=AdminSeeder --force` run successfully
- [ ] `php artisan storage:link` run successfully
- [ ] Storage folder permissions set to 775

## Caching

- [ ] `php artisan config:cache` run
- [ ] `php artisan route:cache` run
- [ ] `php artisan view:cache` run
- [ ] `php artisan optimize` run

## Health Verification

- [ ] `/health` returns `"database": true`
- [ ] `/health` returns `"drive": true`
- [ ] `/health` returns `"smtp": true` (or documented as unavailable)
- [ ] Admin login page loads at `/admin/login`
- [ ] Admin can log in and see dashboard
- [ ] Client login page loads at `/client/login`

## Phase 0

- [ ] V1 (CORS) tested
- [ ] V2 (PHP version) confirmed 8.3
- [ ] V5 (Drive folder creation) verified
- [ ] V6 (PHP upload limits) verified
- [ ] V7 (Cron) verified
- [ ] V8 (Migrations) verified
- [ ] V9 (set_time_limit) verified
- [ ] V10 (Drive quota) verified
- [ ] V11 (Large upload) tested if Plan A
- [ ] V12 (ZIP streaming) tested
- [ ] V13 (Output buffering) verified
- [ ] V14 (Refresh recovery) verified
- [ ] Final upload strategy selected and set in `.env`
- [ ] ZIP limits measured and set in `config/memoryvault.php`
- [ ] Caches cleared and re-cached after final config changes
- [ ] Developer contacted to remove unused upload strategy

## First Event

- [ ] Test event created via admin dashboard
- [ ] Drive folder created and visible in Google Drive
- [ ] QR code generated successfully
- [ ] Upload link copied and opened in browser
- [ ] Test file uploaded successfully
- [ ] Test message submitted
- [ ] Submission visible in client dashboard
- [ ] Submission ZIP download works
- [ ] Client login works with event credentials
- [ ] Client can view gallery and submissions

## Security

- [ ] `X-Frame-Options: DENY` header sent (check with browser DevTools)
- [ ] `X-Content-Type-Options: nosniff` header sent
- [ ] `Content-Security-Policy` header sent
- [ ] `Strict-Transport-Security` header sent
- [ ] `.env` file not accessible via web (`curl https://yourdomain.com/.env` returns 403/404)
- [ ] `composer.json` not accessible via web
- [ ] `artisan` not accessible via web

## Backup

- [ ] Database backed up (`mysqldump` or phpMyAdmin Export)
- [ ] `.env` file copied to password manager or encrypted storage
- [ ] `APP_KEY` recorded separately
- [ ] `MEMORYVAULT_CLIENT_KEY` recorded separately
- [ ] Google service account JSON backed up securely
- [ ] Google Drive contents verified

---

**End of Setup Guide**

If every checkbox above is ticked, the deployment is complete. The application is production-ready.