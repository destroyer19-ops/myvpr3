# MyVPR — Troubleshooting & Operations Guide

This guide provides targeted diagnostics, log locations, and concrete solutions for common technical challenges encountered in development, testing, and production.

---

## 1. Important Log Locations

When troubleshooting errors, inspect logs in this sequence:

| Service | Log Path | Purpose |
| :--- | :--- | :--- |
| **Nginx Web Server** | `/var/log/nginx/error.log`<br>`/var/log/nginx/access.log` | Gateway errors, 502 Bad Gateway, 404 routing issues, SSL handshakes |
| **PHP-FPM Engine** | `/var/log/php8.2-fpm.log` | PHP worker crashes, pool saturation, fatal runtime exceptions |
| **MySQL Server** | `/var/log/mysql/error.log` | Query errors, connection timeouts, collation/charset warnings |
| **Systemd Services** | `sudo journalctl -u nginx -e`<br>`sudo journalctl -u php8.2-fpm -e` | Startup failures, configuration parsing errors |

---

## 2. Authentication & Session Issues

### Error: "401 Unauthorized / Not logged in" on protected endpoints
- **Symptoms**: User logs in successfully, but subsequent requests to `/api/me`, `/api/user/profile`, or `/api/subscriptions/squad/initiate` return `401 Unauthorized`.
- **Root Causes**:
  1. The frontend HTTP client omitted session cookies (`credentials: 'include'`).
  2. Nginx or proxy is stripping the `Set-Cookie` header.
  3. The `SameSite` cookie policy is blocking cookies across different port or domain configurations.
- **Resolution**:
  - In Vite development, always use the Vite proxy (`/api` routed to `http://localhost:8000`) so requests are same-origin.
  - In production, ensure both frontend and backend share the domain (`https://myvpr.org` and `https://myvpr.org/api`).
  - Verify `php.ini` has `session.cookie_httponly = 1` and `session.cookie_samesite = "Lax"`.

### Error: "Invalid CSRF token" (403 Forbidden)
- **Symptoms**: State-modifying requests (`POST`, `PUT`, `DELETE`) fail with a CSRF error.
- **Root Causes**:
  1. The user's session expired while the page was open.
  2. The frontend failed to fetch a fresh token from `/api/csrf` or read `window.csrfToken`.
- **Resolution**:
  - Check browser console network tab for the `X-CSRF-Token` header.
  - Ensure the client calls `await fetch('/api/csrf')` on initialization and passes the returned token in header `X-CSRF-Token`.

### KingsChat SSO: "Invalid or expired login state"
- **Symptoms**: User clicks KingsChat login, approves the popup, but gets redirected to `/login?error=Invalid+or+expired+login+state`.
- **Root Causes**:
  1. More than 10 minutes elapsed between clicking login and KingsChat redirecting (TTL expired in `oauth_states`).
  2. The database table `oauth_states` has clock skew with `NOW()`.
- **Resolution**:
  - Verify server time synchronization (`sudo timedatectl set-ntp true`).
  - Ensure `KC_REDIRECT_URI` matches exactly between `.env` and KingsChat Developer Dashboard.

---

## 3. Payments & Squad Webhook Issues

### Issue: Payment completes on Squad, but subscription remains inactive
- **Symptoms**: Customer's bank account is debited, but user account is not upgraded to paid tier.
- **Diagnostic Steps**:
  1. Check the transactions table:
     ```sql
     SELECT * FROM transactions ORDER BY id DESC LIMIT 5;
     ```
  2. Check if the transaction exists with status `pending`.
  3. Inspect Nginx access log for `/api/subscriptions/squad/webhook`.
- **Root Causes & Solutions**:
  - **Missing Webhook Delivery**: If Squad cannot reach your server (e.g. localhost or firewall block), the webhook never fires. Use Squad client callback verification: the frontend calls `/api/subscriptions/squad/verify/{reference}` upon redirect.
  - **Signature Mismatch**: Squad sends HMAC-SHA512 in header `x-squad-encrypted-body`. If `SQUAD_WEBHOOK_SECRET` in `.env` doesn't match the Squad dashboard secret, the webhook returns `401 Unauthorized`.
  - **Amount Verification**: Squad amounts are transmitted in **Kobo** (lowest currency unit). ₦5,000 = `500000` Kobo. Always use `SquadService::fromKobo()` to avoid dividing twice.

---

## 4. Video Streaming & Browser Studio Issues

### Issue: Browser Studio Watch Link does not display broadcaster's video
- **Symptoms**: Broadcaster is live in studio, shares watch link `https://myvpr.org/watch/vdo?room=XYZ&view=STREAM`, but viewer sees a loading screen or black frame.
- **Diagnostic Steps**:
  1. Verify the broadcaster has clicked **"Start Broadcast"** (the WebRTC peer connection is only established after starting).
  2. Inspect the query parameters:
     - Broadcaster URL MUST contain `push=STREAM_ID`.
     - Viewer URL MUST contain `view=STREAM_ID`.
     - Both URLs MUST share the exact same `room=ROOM_ID`.
  3. Check WebRTC STUN/TURN connection in browser dev tools console:
     - If NAT traversal fails (e.g. restrictive corporate firewall), STUN cannot connect directly.

### Issue: "Camera or Microphone Permission Denied"
- **Root Causes**:
  1. Browser denied media permissions.
  2. Page is running on insecure HTTP (browsers require HTTPS or `localhost` for `getUserMedia`).
- **Resolution**:
  - Ensure platform is accessed via `https://` (or `http://localhost:5173`).
  - In browser address bar, click the site lock/permissions icon and reset Camera and Microphone permissions to "Allow".

### Issue: Amazon IVS Stream fails to play (HLS Error)
- **Symptoms**: Live player displays "Stream offline" or 404 manifest error.
- **Diagnostic Steps**:
  1. Check OBS / encoder status: Is OBS currently streaming green to `rtmps://...`?
  2. Verify stream key matches between OBS and `AWS_IVS_STREAM_KEY`.
  3. Test playback URL directly in VLC or browser: `https://...master.m3u8`. Note: IVS manifests only return 200 OK while actively transmitting frames.

---

## 5. Database & API Connectivity Issues

### Error: `SQLSTATE[HY000] [2002] Connection refused`
- **Root Causes**: MySQL service is stopped or listening on a different socket/port.
- **Resolution**:
  ```bash
  sudo systemctl status mysql
  sudo systemctl restart mysql
  ```
  Ensure `.env` has `DB_HOST=127.0.0.1` or `localhost`.

### Error: `Table 'myvpr_db.praise_users' doesn't exist`
- **Root Causes**: Database schema has not been imported.
- **Resolution**:
  Run the automated schema runner from project root:
  ```bash
  php run_db_migrations.php
  ```

---

## 6. CORS & Web Server Configuration

### Error: `No 'Access-Control-Allow-Origin' header is present on the requested resource`
- **Root Causes**:
  - The client origin is not registered in the backend CORS whitelist.
- **Resolution**:
  - Inspect [`api/index.php`](file:///Users/home/Documents/myvprNew/final_vpr/api/index.php#L20-L42).
  - Add your frontend origin (e.g. `https://myvpr.org`) to `REACT_APP_URL` in `.env`.
