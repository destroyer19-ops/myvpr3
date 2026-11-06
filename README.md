# MyVPR — Virtual Praise Reports, Outreach & Streaming Platform

MyVPR is a Christian media, live broadcasting, and evangelism platform. It combines live streaming (Amazon IVS, WebRTC browser studio, Daily.co conferencing), automated subscription billing (Squad / HabariPay), KingsChat single sign-on, on-demand gospel video libraries, praise reports, and global outreach/crusade mobilization into a unified web application.

---

## 🚀 Key Features

- **Browser Streaming Studio**: Direct in-browser video broadcasting via WebRTC with camera/mic controls, screen sharing, and clean viewer links without requiring third-party software or OBS.
- **Amazon IVS Live TV**: Broadcast-grade, low-latency HLS video playback with dynamic viewer statistics and interactive live chat.
- **KingsChat OAuth 2.0**: Single sign-on with automatic user provisioning for LoveWorld / Christ Embassy community members.
- **Squad Payment Gateway**: Automated subscription billing (card, bank transfer, USSD) with cryptographic webhook signature verification (HMAC-SHA512).
- **Crusades & Outreaches**: Real-time event mobilization, referral link generation, attendee tracking, and altar-call salvation recordings.
- **On-Demand Video Library**: Categorized sermons, worship sessions, and multilingual ministry programs.
- **Interactive Testimonies & Offerings**: Community praise reports with administrative moderation and giving pipelines.
- **Full Administration Console**: Deep metrics for active streams, transaction ledgers, plan pricing, subscriber lifecycles, and content scheduling.

---

## 📁 Repository Structure

```
final_vpr/
├── api/                       # PHP REST API backend
│   ├── endpoints/             # Modular route controllers (admin, streaming, subscriptions, crusades, etc.)
│   ├── services/              # Decoupled business logic (SquadService, etc.)
│   ├── config.php             # Unified environment & constant loader
│   ├── helpers.php            # Security & response helpers (CSRF, JSON, sanitization)
│   ├── index.php              # Central API router & front controller
│   └── .env.example           # Backend environment template
├── docs/                      # Comprehensive technical documentation suite
│   ├── README.md              # Documentation master index
│   ├── PLATFORM_OVERVIEW.md   # Mission, workflows, modules
│   ├── ARCHITECTURE.md        # System architecture, data flow & security
│   ├── FRONTEND.md            # React 19, Tailwind v4, state, routing
│   ├── BACKEND.md             # PHP router, auth, database, uploads
│   ├── DATABASE.md            # MySQL schema, ER diagrams, tables catalog
│   ├── API_REFERENCE.md       # Complete REST API reference specification
│   ├── INTEGRATIONS.md        # Squad, KingsChat, IVS, VDO.Ninja, Daily, Brevo
│   ├── DEPLOYMENT.md          # Ubuntu / Nginx / PHP-FPM / MySQL setup guide
│   ├── ENVIRONMENT_VARIABLES.md # Complete env var reference
│   └── TROUBLESHOOTING.md     # Diagnostic & operational playbook
├── includes/                  # Core PHP libraries, database & session management
│   ├── config.php             # Database credentials loader (env-backed)
│   ├── db.php                 # MySQLi connection singleton
│   ├── session.php            # Secure cookie session handling
│   └── email_config.php       # Brevo email API client
├── migrations/                # Versioned SQL migration files
├── new/                       # Modern React 19 frontend application
│   ├── src/
│   │   ├── components/        # Reusable UI components & layouts
│   │   ├── context/           # Auth, theme, stream state contexts
│   │   ├── hooks/             # Custom React hooks
│   │   ├── pages/             # Route views (streaming, auth, admin, etc.)
│   │   └── services/          # Frontend API service layer
│   ├── package.json           # Frontend dependencies
│   ├── vite.config.js         # Vite 8 build & development proxy
│   └── .env.example           # Frontend environment template
├── uploads/                   # Media storage directory (.gitkeep tracked)
│   ├── offerings/
│   ├── thumbnails/
│   └── videos/
├── .env.example               # Root environment configuration template
├── .gitignore                 # Production ignore rules (secrets, caches, builds)
├── database.sql               # Base database schema
├── run_db_migrations.php      # Automated database migration runner
└── README.md                  # This file
```

---

## 🛠️ Technology Stack

- **Frontend**: React 19, Vite 8, Tailwind CSS v4, Lucide React, Video.js, React Router v7.
- **Backend**: PHP 8.2+, MySQLi with prepared statements, modular REST architecture.
- **Database**: MySQL 8.0+ / MariaDB 10.6+ (InnoDB, `utf8mb4_unicode_ci`).
- **Integrations**: Squad (GTCO), KingsChat OAuth 2.0, Amazon IVS, Daily.co, Brevo.

---

## ⚡ Quick Start (Local Development)

### Prerequisites
- PHP 8.2+ with `pdo`, `mysqli`, `curl`, `mbstring`
- MySQL 8.0+
- Node.js 20+ and npm

### 1. Database Setup
```bash
# Log in to MySQL and create the database
mysql -u root -p -e "CREATE DATABASE myvpr_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import initial schema and migrations
mysql -u root -p myvpr_db < database.sql
php run_db_migrations.php
```

### 2. Backend Configuration & Server
```bash
# Copy environment configuration
cp .env.example .env
cp api/.env.example api/.env

# Start PHP built-in server (port 8000)
cd api
php -S localhost:8000
```

### 3. Frontend Setup
```bash
cd new
cp .env.example .env
npm install
npm run dev
```

Visit `http://localhost:5173` in your browser. All API requests (`/api/*`) are automatically proxied to `http://localhost:8000`.

---

## 📦 Building for Production

Compile the optimized client bundle:
```bash
cd new
npm run build
```
The static assets will be output to `new/dist/`, ready to be served by Nginx.

---

## 📚 Complete Documentation Suite

For detailed architecture, configuration, and API specifications, consult the documents in [`docs/`](docs/):

1. [Platform Overview](docs/PLATFORM_OVERVIEW.md) — Mission, modules, core workflows.
2. [System Architecture](docs/ARCHITECTURE.md) — System design, data flow, Mermaid diagrams.
3. [Frontend Documentation](docs/FRONTEND.md) — React 19 structure, routes, state management.
4. [Backend Documentation](docs/BACKEND.md) — PHP REST architecture, session security, cron jobs.
5. [Database Documentation](docs/DATABASE.md) — ER diagram, schema catalog, migration guide.
6. [API Reference](docs/API_REFERENCE.md) — Full endpoint specifications with request/response schemas.
7. [Third-Party Integrations](docs/INTEGRATIONS.md) — In-depth guide for Squad, KingsChat, IVS, and more.
8. [Production Deployment](docs/DEPLOYMENT.md) — Step-by-step Ubuntu, Nginx, PHP-FPM & SSL deployment.
9. [Environment Variables](docs/ENVIRONMENT_VARIABLES.md) — Exhaustive reference of all configuration keys.
10. [Troubleshooting Guide](docs/TROUBLESHOOTING.md) — Error resolutions, logs, diagnostic checklists.

---

## 📄 License
Proprietary & Confidential. All rights reserved by MyVPR.
