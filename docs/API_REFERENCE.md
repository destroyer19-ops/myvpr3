# MyVPR — REST API Reference Specification

This document provides complete, accurate documentation of every REST API endpoint exposed by the MyVPR PHP backend (`/api`), its HTTP method, URL pattern, authentication requirements, parameters, and response structures based directly on the implementation in [`api/index.php`](file:///Users/home/Documents/myvprNew/final_vpr/api/index.php) and [`api/endpoints/*.php`](file:///Users/home/Documents/myvprNew/final_vpr/api/endpoints/).

---

## 1. Global Conventions & Standards

- **Base URL**: `/api` (e.g., `https://myvpr.org/api` in production, `http://localhost:5173/api` via Vite proxy in development).
- **Format**: All requests accepting data use `application/json` (or `multipart/form-data` for file uploads). All responses return `application/json`.
- **Response Format**:
  - Success: `{"success": true, ...}`
  - Failure: `{"success": false, "message": "Reason for error", ...}`
- **Authentication**: Session-based cookie authentication (`PHPSESSID` cookie with `HttpOnly` and `SameSite=Lax`). Protected endpoints require an active session (`$_SESSION['user_id']`).
- **Authorization**: Admin routes require `$_SESSION['account_type'] === 'admin'`.
- **CSRF Protection**: State-changing POST/PUT/DELETE requests validate the `X-CSRF-Token` header or `csrf_token` body parameter against `$_SESSION['csrf_token']`.
- **Webhook Authentication**: Squad webhook requests are validated using the `x-squad-encrypted-body` signature header via HMAC-SHA512.

---

## 2. Authentication & Session Endpoints

### `GET /api/csrf`
Fetch a valid CSRF token for state-modifying requests.
- **Auth**: None
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "csrf_token": "a1b2c3d4e5f6..."
  }
  ```

### `GET /api/me`
Retrieve currently authenticated user session state.
- **Auth**: None (returns `authenticated: false` if not logged in)
- **Response (200 OK - Authenticated)**:
  ```json
  {
    "success": true,
    "authenticated": true,
    "user": {
      "id": 14,
      "username": "pastordavid",
      "account_type": "admin"
    }
  }
  ```
- **Response (200 OK - Guest)**:
  ```json
  {
    "success": true,
    "authenticated": false,
    "user": null
  }
  ```

### `POST /api/auth/login`
Authenticate a user via username/email and password. Supports "Remember Me" 30-day token.
- **Auth**: None
- **Request Body**:
  ```json
  {
    "login": "pastordavid",
    "password": "SecretPassword123!",
    "remember_me": true,
    "csrf_token": "..."
  }
  ```
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Login successful",
    "user": {
      "id": 14,
      "username": "pastordavid",
      "account_type": "admin"
    }
  }
  ```
- **Errors**: `400 Bad Request` (Missing credentials), `401 Unauthorized` (Invalid credentials), `403 Forbidden` (Account suspended).

### `POST /api/auth/register`
Register a new member or viewer account.
- **Auth**: None
- **Request Body**:
  ```json
  {
    "username": "brother_john",
    "email": "john@example.com",
    "password": "Password123!",
    "confirm_password": "Password123!",
    "church_center": "Christ Embassy Lagos",
    "phone": "+2348012345678",
    "csrf_token": "..."
  }
  ```
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Registration successful"
  }
  ```

### `POST /api/auth/logout`
Terminate the current session and invalidate "remember_me" cookie.
- **Auth**: Logged In
- **Request Body**: `{ "csrf_token": "..." }`
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Logged out successfully"
  }
  ```

### `POST /api/auth/forgot`
Request a password reset link sent via Brevo email service.
- **Auth**: None
- **Request Body**: `{ "email": "john@example.com" }`
- **Response (200 OK)**: `{"success": true, "message": "Password reset instructions sent."}`

### `POST /api/auth/reset`
Reset password using token received via email.
- **Auth**: None
- **Request Body**:
  ```json
  {
    "token": "reset_token_hex_string",
    "password": "NewSecurePassword123!",
    "confirm_password": "NewSecurePassword123!"
  }
  ```
- **Response (200 OK)**: `{"success": true, "message": "Password reset successfully."}`

### `GET /api/auth/kingschat/start`
Initiate KingsChat OAuth 2.0 flow. Generates cryptographic state and returns KingsChat auth URL.
- **Auth**: None
- **Query Params**: `redirect` (Optional post-login path, e.g. `/streaming`)
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "url": "https://accounts.kingsch.at?clientId=...&scopes=...&origin=...&state=..."
  }
  ```

### `GET /api/auth/kingschat/callback`
Handles OAuth 2.0 callback from KingsChat with authorization code exchange, profile retrieval, user provisioning, and redirect.
- **Query / Post Params**: `code`, `origin` (or `state`), `error`

---

## 3. User & Profile Endpoints

### `GET /api/user/profile`
Retrieve detailed profile information for the authenticated user, including active subscription status.
- **Auth**: Logged In
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "user": {
      "id": 14,
      "username": "pastordavid",
      "email": "david@myvpr.org",
      "church_center": "CE Accra",
      "phone": "+233201234567",
      "account_type": "user",
      "subscription": {
        "status": "active",
        "plan_name": "Pro Streamer",
        "expires_at": "2026-12-01 12:00:00"
      }
    }
  }
  ```

### `POST /api/user/update-username`
Update user's profile handle.
- **Auth**: Logged In
- **Request Body**: `{ "username": "new_handle", "csrf_token": "..." }`

---

## 4. Crusades Endpoints

### `GET /api/crusades`
List all published or active crusades/campaigns.
- **Auth**: None
- **Query Params**: `limit`, `offset`, `search`
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "crusades": [
      {
        "id": 1,
        "uuid": "CRU123456",
        "title": "Global Day of Praise 2026",
        "description": "24-hour praise marathon",
        "banner_url": "/uploads/banners/cru1.jpg",
        "status": "active",
        "attendees_count": 1420,
        "start_time": "2026-11-10 18:00:00"
      }
    ]
  }
  ```

### `POST /api/crusades`
Create a new crusade event.
- **Auth**: Logged In
- **Request Body**:
  ```json
  {
    "title": "Youth Praise Night",
    "description": "An evening of uninterrupted praise",
    "scheduled_date": "2026-11-20 19:00:00",
    "target_attendees": 500
  }
  ```

### `GET /api/crusades/:uuid`
Get single crusade details by its UUID.
- **Auth**: None

### `GET /api/crusades/:uuid/stats`
Get live statistics (view count, salvations, testimonies, offerings) for a crusade.
- **Auth**: None

### `POST /api/crusades/:uuid/share`
Generate or track a personal referral / sharing link for an outreach event.
- **Auth**: None (or Logged In)
- **Request Body**: `{ "share_channel": "kingschat" }`

### `POST /api/crusades/:uuid/end`
End an active crusade session.
- **Auth**: Crusade Creator or Admin

### `POST /api/shared/end`
Record an attendee departure from a shared session.

---

## 5. Live Streaming Endpoints

### `GET /api/streaming/plans`
Get available live streaming subscription/activation tiers.
- **Auth**: None
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "plans": [
      {
        "id": 1,
        "name": "Standard Streamer",
        "price": 5000,
        "currency": "NGN",
        "duration_days": 30,
        "features": ["720p HD", "Up to 500 viewers", "Standard latency"]
      }
    ]
  }
  ```

### `POST /api/streaming/provision`
Provision Amazon IVS channel and stream credentials for the logged-in user.
- **Auth**: Logged In
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "channel": {
      "channel_arn": "arn:aws:ivs:eu-west-1:...",
      "stream_key": "sk_eu-west-1_...",
      "ingest_endpoint": "rtmps://a1b2c3.global-contribute.live-video.net:443/app/",
      "playback_url": "https://a1b2c3.eu-west-1.playback.live-video.net/api/video/v1/master.m3u8"
    }
  }
  ```

### `GET /api/streaming/status`
Check broadcaster's active stream state and playback metrics.
- **Auth**: Logged In
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "is_live": true,
    "viewer_count": 348,
    "playback_url": "https://...master.m3u8"
  }
  ```

### `GET /api/streaming/watch/:channel_id`
Viewer watch endpoint returning HLS playback URL and metadata for viewer.
- **Auth**: None
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "title": "Evening Service Live",
    "broadcaster": "Pastor David",
    "playback_url": "https://...master.m3u8",
    "is_live": true
  }
  ```

### `POST /api/streaming/channel/reset-key`
Regenerate broadcaster's IVS stream key.
- **Auth**: Logged In

### `POST /api/streaming/broadcast/start` & `POST /api/streaming/broadcast/stop`
Signal broadcast start or stop state to update database records and viewers.
- **Auth**: Broadcaster Session

---

## 6. Realtime & WebRTC Sessions

### `POST /api/realtime/provision`
Create or configure a WebRTC real-time room for browser broadcasting or peer-to-peer conferencing.
- **Auth**: Logged In
- **Request Body**:
  ```json
  {
    "title": "Daily Devotional Live",
    "room_type": "studio"
  }
  ```
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "room_id": "vpr_abc12345",
    "stream_id": "strm_xyz98765"
  }
  ```

### `POST /api/realtime/end`
Terminate active real-time room session and notify connected viewers.
- **Auth**: Room Host

### `POST /api/realtime/cleanup`
Server-side cleanup routine for stale/orphaned WebRTC sessions.

---

## 7. Video Meetings Endpoints

### `POST /api/meetings`
Schedule a new video meeting (Daily.co integration).
- **Auth**: Logged In
- **Request Body**:
  ```json
  {
    "title": "Prayer Committee Meeting",
    "scheduled_time": "2026-11-15 10:00:00",
    "duration_minutes": 60
  }
  ```

### `POST /api/meetings/start`
Start an instant meeting or enter scheduled room. Returns room join URL and token.

### `POST /api/meetings/end`
End meeting session and close room.

---

## 8. Subscriptions & Payments (Squad Integration)

### `GET /api/subscriptions/plans`
Get all active subscription plans.
- **Auth**: None
- **Response (200 OK)**: List of plans with ID, name, code, duration, price, currency, and features.

### `GET /api/user/subscription` (alias `/api/subscriptions/status`)
Get user's current subscription tier, activation date, and expiry date.
- **Auth**: Logged In

### `POST /api/subscriptions/squad/initiate`
Initiate payment transaction via Squad (GTCO) gateway.
- **Auth**: Logged In
- **Request Body**:
  ```json
  {
    "plan_id": 2,
    "currency": "NGN"
  }
  ```
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "checkout_url": "https://checkout.squadco.com/...",
    "transaction_ref": "vpr_sq_1730000000_abc"
  }
  ```

### `GET /api/subscriptions/squad/verify/:ref`
Verify payment status with Squad API using transaction reference.
- **Auth**: Logged In
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "payment_status": "success",
    "plan_activated": true,
    "expires_at": "2026-12-06 14:00:00"
  }
  ```

### `POST /api/subscriptions/squad/webhook`
Squad asynchronous webhook notification handler. Validates HMAC-SHA512 signature in `x-squad-encrypted-body`.
- **Auth**: HMAC Signature Header
- **Response**: `200 OK`

### `POST /api/subscriptions/manual`
Submit bank transfer proof / receipt for offline approval.
- **Auth**: Logged In
- **Request**: `multipart/form-data` with `plan_id`, `bank_name`, `receipt` file.

---

## 9. Interactive Live Feeds (Comments & Real-time Updates)

### `GET /api/comments?crusade_id=:id`
Fetch recent comments for a crusade or live stream.
- **Auth**: None
- **Query Params**: `crusade_id`, `limit`, `last_id`

### `GET /api/comments/updates?crusade_id=:id&last_id=:id`
Long-poll or poll for new comments posted since `last_id`.
- **Auth**: None

### `POST /api/comments`
Submit a new live chat comment.
- **Auth**: None (guest nickname or authenticated user)
- **Request Body**:
  ```json
  {
    "crusade_id": 12,
    "name": "Sister Grace",
    "comment": "Praise God! The worship is powerful!"
  }
  ```

---

## 10. Programs & Recorded Videos

### `GET /api/programs`
List scheduled TV/video programs.
- **Auth**: None

### `GET /api/programs/live`
Get the currently broadcasting scheduled program.
- **Auth**: None

### `GET /api/videos`
List on-demand video archive with filters.
- **Auth**: None
- **Query Params**: `language`, `category`, `search`, `page`, `limit`

### `GET /api/videos/:id`
Get single video item details and streamable MP4/HLS URL.
- **Auth**: None

### `GET /api/videos/languages`
Get list of available audio/caption languages.

---

## 11. Testimonies, Salvations & Offerings

### `GET /api/testimonies`
Get approved testimonies.
- **Auth**: None

### `POST /api/testimonies`
Submit a praise report or testimony.
- **Auth**: None (name, church, content, optional media)

### `POST /api/salvations`
Record a decision for Christ during a live crusade or outreach.
- **Auth**: None
- **Request Body**:
  ```json
  {
    "crusade_id": 12,
    "full_name": "Samuel Okon",
    "email": "samuel@example.com",
    "phone": "+234...",
    "country": "Nigeria",
    "notes": "Accepted Christ during altar call"
  }
  ```

### `POST /api/offerings/submit`
Submit offering / donation record (supports online or bank transfer proof).

---

## 12. Administration Endpoints (`/api/admin/*`)

All admin routes require `$_SESSION['account_type'] === 'admin'`.

### Analytics & System Overview
- `GET /api/admin/stats` — High-level counts: active streams, users, crusades, total revenue.
- `GET /api/admin/payments/overview` — Financial summary: daily/monthly revenue, transaction count.
- `GET /api/admin/payments/transactions` — Paginated transaction ledger with filters.
- `GET /api/admin/payments/transactions/:id/audit` — Detailed webhook and verification audit trail.
- `GET /api/admin/payments/config` — Squad and payment gateway configuration status.

### User & Subscription Management
- `GET /api/admin/users` — Paginated user directory.
- `GET /api/admin/subscriptions` — List all user subscriptions (active, pending, expired).
- `POST /api/admin/subscriptions/:id/approve` — Approve manual bank transfer subscription.
- `POST /api/admin/subscriptions/:id/reject` — Reject manual bank transfer subscription.
- `POST /api/admin/subscriptions/:id/cancel` — Cancel active subscription.
- `GET /api/admin/subscription-plans` — Manage subscription pricing tiers.
- `POST /api/admin/subscription-plans` — Create subscription plan.
- `PUT /api/admin/subscription-plans/:id` — Update subscription plan.
- `POST /api/admin/subscription-plans/:id/toggle` — Activate/deactivate plan.

### Broadcasts & Media Management
- `GET /api/admin/streams` — Monitor all active and configured IVS/WebRTC streams.
- `POST /api/admin/streams` — Create stream channel.
- `PUT /api/admin/streams/:id` — Update stream parameters.
- `DELETE /api/admin/streams/:id` — Remove stream channel.
- `POST /api/admin/streams/:id/toggle` — Enable/disable live stream.
- `GET /api/admin/programs` — List scheduled programs.
- `POST /api/admin/programs` — Create scheduled program.
- `PUT /api/admin/programs/:id` — Update program schedule.
- `DELETE /api/admin/programs/:id` — Delete program.
- `POST /api/admin/programs/:id/set-live` — Force program as active broadcast.
- `GET /api/admin/recordings` — List recorded broadcasts.
- `POST /api/admin/recordings` — Register new recording.
- `DELETE /api/admin/recordings/:id` — Delete recording.
- `GET /api/admin/upload/s3-config` & `GET /api/admin/upload/s3-presigned` — AWS S3 direct upload presigning.
- `POST /api/admin/upload/video` — Server-side video file upload.

### Moderation
- `GET /api/admin/testimonies` — List submitted testimonies (pending, approved, rejected).
- `PATCH /api/admin/testimonies/:id` — Approve or reject testimony.
- `DELETE /api/admin/testimonies/:id` — Delete testimony.
- `GET /api/admin/salvations` — View outreach salvation records.
- `GET /api/admin/crusades` — Manage outreach campaigns.
- `GET /api/admin/cms/banners` / `POST` / `DELETE` — Manage promotional home banners.
