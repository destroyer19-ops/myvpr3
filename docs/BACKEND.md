# Backend Documentation — MyVPR PHP REST API

This document details the backend architecture, directory organization, controllers, middleware, and operational procedures of the MyVPR API located in the `api/` directory.

---

## 1. Runtime & Technology Stack

- **Runtime**: PHP 7.4 / 8.0 / 8.1 / 8.2 / 8.3
- **Database Driver**: `mysqli` (with prepared statements and parameterized queries)
- **External SDKs**: `aws/aws-sdk-php` (^3.200) for Amazon IVS channel provisioning
- **Networking**: PHP cURL with SSL peer verification (`CURLOPT_SSL_VERIFYPEER = true`)
- **Crypto & Hashing**: OpenSSL, `password_hash()` (Argon2id / Bcrypt), `hash_hmac()` with SHA-512 for Squad webhook validation

---

## 2. Directory Structure

```text
api/
├── composer.json               # PHP dependency definition (AWS SDK)
├── composer.lock               # Deterministic dependency lockfile
├── config.php                  # Environment loader & global constants
├── helpers.php                 # Global response, request, and utility functions
├── check_db.php                # Database health check and runtime schema checks
├── delete_crusade.php          # Dedicated cascade crusade deletion helper
├── espees_callback.php         # Espees payment notification webhook
├── espees_proxy.php            # Espees merchant API gateway proxy
├── store_pending_plan.php      # Session-stored checkout plan handler
├── endpoints/                  # Modular REST endpoint controllers
│   ├── admin.php               # Admin dashboard statistics, user & CMS management
│   ├── crusades.php            # Digital crusade campaigns, attendance, live chat
│   ├── meetings.php            # Praise meeting creation & Daily.co room token setup
│   ├── metadata.php            # Platform constants (zones, countries, currencies)
│   ├── offerings.php           # Financial giving records & proof of transfer upload
│   ├── programs.php            # 24/7 TV schedule, video CMS, and watch progress
│   ├── realtime.php            # Amazon IVS RealTime WebRTC stage provisioning
│   ├── salvations.php          # Salvation decision intake & discipleship dispatch
│   ├── streaming.php           # AWS IVS channels, RTMP keys, and streaming plans
│   ├── subscriptions.php       # Squad payment flow, verification, webhook, plans
│   ├── testimonies.php         # Praise report submission and approval workflow
│   ├── user.php                # Profile fetching, updates, and username claims
│   └── videos.php              # Video-on-demand search, catalog, and language filter
├── services/
│   └── SquadService.php        # Reusable HabariPay / GTCO Squad payment gateway client
└── index.php                   # Central front controller & routing engine
```

---

## 3. Core Architecture & Routing Engine (`api/index.php`)

The API uses a **Front Controller** pattern. All incoming requests are routed through `api/index.php` (via Nginx `try_files` or Vite's dev proxy).

### Request Path Normalization
```php
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path   = get_request_path();
$path   = rtrim($path, '/');
if ($path === '') $path = '/';
```
`get_request_path()` cleanly extracts the relative endpoint path from `$_SERVER['PATH_INFO']`, `$_SERVER['REQUEST_URI']`, or query parameters, stripping the `/api/index.php` prefix.

### HTTP Response Standardization
All responses exit through `json_response($data, $statusCode)` defined in `api/helpers.php`:
```php
function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
```

---

## 4. Authentication, Sessions & Middleware

### A. Authentication Verification (`require_auth()`)
Any endpoint requiring user authentication invokes `require_auth()`:
```php
function require_auth() {
    _ensureSession();
    if (empty($_SESSION['user_id'])) {
        json_response(['success' => false, 'message' => 'Authentication required.'], 401);
    }
}
```

### B. Admin Authorization Verification (`require_admin()`)
Protected administrative endpoints invoke `require_admin()` in `api/endpoints/admin.php`:
```php
function require_admin() {
    _ensureSession();
    if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        json_response(['success' => false, 'message' => 'Admin access required.'], 403);
    }
}
```

### C. Persistent "Remember-Me" Authentication
If a user session expires but the browser carries a `remember_me` cookie, `api/index.php` validates the split token:
1. Splits cookie value into `$selector` and `$authenticator`.
2. Queries `praise_users` by `remember_selector` where `token_expiry > NOW()`.
3. Verifies the authenticator using `password_verify($authenticator, $row['remember_token'])`.
4. If valid, reconstitutes `$_SESSION['user_id']`, generates a fresh split-token pair, updates the database, and issues a refreshed cookie (30-day rolling expiration).

---

## 5. File & Media Upload Handling

The platform supports direct uploads for:
1. **Offering Proof of Payment** (`/offerings`): Stored in `uploads/offerings/`.
2. **Video CMS Thumbnails** (`/admin/videos`): Stored in `uploads/videos/`.
3. **Crusade Banners & Art** (`/crusades`): Stored in `uploads/thumbnails/`.

### Upload Security Rules
- **Type Whitelisting**: Strictly restricts MIME types (e.g. `image/jpeg`, `image/png`, `image/webp`, `application/pdf`).
- **File Size Caps**: Enforces strict payload ceilings (e.g., 5MB for payment receipts, 10MB for video posters).
- **Name Sanitization**: Files are saved with cryptographically random identifiers:
  ```php
  $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
  $filename = uniqid('proof_') . '_' . time() . '.' . $ext;
  $destination = $upload_dir . $filename;
  move_uploaded_file($file['tmp_name'], $destination);
  ```

---

## 6. Background Jobs & Scheduled Processes

MyVPR includes automated CLI scripts designed for cron scheduling:

### 1. Starting Scheduled Crusades (`start_scheduled_crusades.php`)
- **Frequency**: Every minute (`* * * * *`).
- **Action**: Queries `praise_crusades` where `start_time <= NOW()` and `status = 'scheduled'` and transitions `status` to `'live'`.

### 2. Ending Concluded Crusades (`end_scheduled_crusades.php`)
- **Frequency**: Every 5 minutes (`*/5 * * * *`).
- **Action**: Queries `praise_crusades` where `end_time <= NOW()` and `status = 'live'` and updates `status` to `'ended'`.

### 3. Database Migration Runner (`run_db_migrations.php`)
- **Execution**: CLI only (`php run_db_migrations.php`).
- **Safety**: Inspects table existence with `SHOW TABLES` and column existence with `SHOW COLUMNS` before applying schema modifications.
