# MyVPR — Environment Variables Specification

This document provides a complete reference for all environment configuration variables used across the MyVPR platform backend (`api/.env` or root `.env`) and frontend (`new/.env`).

---

## 1. Backend Environment Variables (`.env` & `api/.env`)

The backend automatically loads variables from either `/api/.env` or the root `/.env` file.

### Platform & Server
| Variable | Required | Default | Description | Example |
| :--- | :---: | :--- | :--- | :--- |
| `APP_ENV` | **Yes** | `development` | Environment mode (`development` or `production`). In production, PHP display_errors is turned off and debug traces are hidden. | `production` |
| `APP_URL` | **Yes** | `http://localhost:8000` | Canonical base URL of the backend API. | `https://myvpr.org` |
| `REACT_APP_URL` | **Yes** | `http://localhost:5173` | Allowed origin for CORS headers and OAuth redirects back to the SPA. | `https://myvpr.org` |
| `SESSION_SECRET` | **Yes** | Auto-fallback | Cryptographic secret for signing sessions and CSRF tokens. | `e9b8f2c1...64_hex_chars` |

---

### Database Configuration (MySQL)
| Variable | Required | Default | Description | Example |
| :--- | :---: | :--- | :--- | :--- |
| `DB_HOST` | **Yes** | `localhost` | Database server hostname or IP address. | `127.0.0.1` |
| `DB_USER` | **Yes** | `root` | MySQL user with read/write access. | `myvpr_user` |
| `DB_PASS` | **Yes** | `""` | MySQL user password. | `Secr3tP@ssw0rd!` |
| `DB_NAME` | **Yes** | `myvpr_db` | Target database schema name. | `myvpr_db` |
| `DB_PORT` | No | `3306` | MySQL TCP port. | `3306` |

---

### Squad Payment Gateway (HabariPay / GTCO)
| Variable | Required | Default | Description | Example |
| :--- | :---: | :--- | :--- | :--- |
| `SQUAD_ENV` | **Yes** | `sandbox` | Squad gateway mode: `sandbox` or `production`. | `production` |
| `SQUAD_PUBLIC_KEY` | **Yes** | `""` | Squad merchant public key used for frontend checkout initialization. | `sandbox_pk_sample_key...` |
| `SQUAD_SECRET_KEY` | **Yes** | `""` | Squad secret API key for backend initiation and payment verification. | `sandbox_sk_sample_key...` |
| `SQUAD_WEBHOOK_SECRET` | **Yes** | Matches secret key | Secret used to verify HMAC-SHA512 header in incoming webhooks. | `live_webhook_secret_...` |

---

### KingsChat OAuth 2.0 Single Sign-On
| Variable | Required | Default | Description | Example |
| :--- | :---: | :--- | :--- | :--- |
| `KC_CLIENT_ID` | **Yes** | `""` | KingsChat registered developer client ID. | `vpr_kc_app_id` |
| `KC_CLIENT_SECRET` | **Yes** | `""` | KingsChat application secret key. | `vpr_kc_app_secret` |
| `KC_REDIRECT_URI` | **Yes** | `""` | Whitelisted callback URL registered on KingsChat developer portal. | `https://myvpr.org/api/auth/kingschat/callback` |
| `KC_AUTH_URL` | No | `https://accounts.kingsch.at` | KingsChat login authorization endpoint. | `https://accounts.kingsch.at` |
| `KC_TOKEN_URL` | No | `https://connect.kingsch.at/developer/api/token` | KingsChat OAuth token exchange endpoint. | `https://connect.kingsch.at/developer/api/token` |
| `KC_PROFILE_URL` | No | `https://connect.kingsch.at/developer/api/profile` | KingsChat user profile endpoint. | `https://connect.kingsch.at/developer/api/profile` |

---

### Amazon IVS & S3 (Streaming & Storage)
| Variable | Required | Default | Description | Example |
| :--- | :---: | :--- | :--- | :--- |
| `AWS_ACCESS_KEY_ID` | Optional | `""` | AWS IAM access key with permissions for IVS and S3. | `AKIAIOSFODNN7EXAMPLE` |
| `AWS_SECRET_ACCESS_KEY` | Optional | `""` | AWS IAM secret access key. | `wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY` |
| `AWS_REGION` | Optional | `eu-west-1` | AWS geographic region where IVS channels are hosted. | `eu-west-1` |
| `AWS_IVS_CHANNEL_ARN` | Optional | `""` | Amazon IVS Channel ARN. | `arn:aws:ivs:eu-west-1:123456789012:channel/abc` |
| `AWS_IVS_STREAM_KEY` | Optional | `""` | Broadcaster RTMP stream key. | `sk_eu-west-1_abcdef123456` |
| `AWS_IVS_INGEST_ENDPOINT` | Optional | `""` | Ingest RTMPS server endpoint for OBS / vMix. | `rtmps://a1b2c3.global-contribute.live-video.net:443/app/` |
| `AWS_IVS_PLAYBACK_URL` | Optional | `""` | Low-latency HLS stream URL (`master.m3u8`). | `https://a1b2c3.eu-west-1.playback.live-video.net/api/video/v1/master.m3u8` |
| `AWS_S3_BUCKET` | Optional | `""` | S3 bucket name for direct video uploads and recordings. | `myvpr-video-storage` |

---

### Brevo (Sendinblue) Transactional Email
| Variable | Required | Default | Description | Example |
| :--- | :---: | :--- | :--- | :--- |
| `BREVO_API_KEY` | **Yes** | `""` | API key from Brevo dashboard for transactional emails. | `xkeysib-abc123xyz...` |
| `MAIL_FROM_EMAIL` | No | `noreply@myvpr.org` | Outgoing "From" email address. | `noreply@myvpr.org` |
| `MAIL_FROM_NAME` | No | `MyVPR` | Outgoing "From" display name. | `MyVPR Platform` |

---

### Daily.co Video Conferencing
| Variable | Required | Default | Description | Example |
| :--- | :---: | :--- | :--- | :--- |
| `DAILY_API_KEY` | Optional | `""` | Daily.co REST API key for managing video rooms. | `daily_api_sec_...` |
| `DAILY_DOMAIN` | Optional | `""` | Daily.co team subdomain. | `myvpr.daily.co` |

---

## 2. Frontend Environment Variables (`new/.env`)

These variables are prefixed with `VITE_` and are bundled into the client application at build time.

| Variable | Required | Default | Description | Example |
| :--- | :---: | :--- | :--- | :--- |
| `VITE_API_URL` | No | `""` (Empty uses relative `/api` proxy) | Target API origin if hosting frontend and backend on separate domains. | `https://myvpr.org/api` |
| `VITE_APP_NAME` | No | `MyVPR` | Branding name displayed across navigation and document title. | `MyVPR` |
| `VITE_SQUAD_PUBLIC_KEY` | No | `""` | Fallback Squad public key for frontend inline widget. | `sandbox_pk_sample_key...` |
| `VITE_VDO_STUDIO_URL` | No | `https://vdo.ninja` | Base URL of WebRTC signaling service for Browser Studio. | `https://vdo.ninja` |
