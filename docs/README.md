# MyVPR Documentation Suite

Welcome to the comprehensive technical documentation for the **Virtual Praise Room (MyVPR)** platform.

This documentation suite covers every architectural layer, database schema, REST API endpoint, third-party integration, deployment strategy, and maintenance procedure required to run, develop, and scale MyVPR.

---

## Documentation Index

| Document | Focus Area | Description |
| :--- | :--- | :--- |
| **[Platform Overview](./PLATFORM_OVERVIEW.md)** | Core Overview | Platform mission, core user workflows, modules, and executive architecture summary. |
| **[System Architecture](./ARCHITECTURE.md)** | System Design | Detailed multi-tier architectural breakdown, data flow, component interactions, and security boundaries. |
| **[Frontend Guide](./FRONTEND.md)** | Frontend SPA | React 19, Vite 8, Tailwind CSS v4, directory layout, route tree, context stores, UI components, and theme. |
| **[Backend Guide](./BACKEND.md)** | Backend API | Custom PHP REST API, router, controllers, service layer, session security, and background workers. |
| **[Database & Schema](./DATABASE.md)** | Data Layer | Database engine, schema definitions, table catalog, entity relationships, ER diagram, and migration runner. |
| **[API Reference](./API_REFERENCE.md)** | REST Endpoints | Authoritative specification of all endpoints, HTTP methods, headers, request bodies, and response schemas. |
| **[Integrations Guide](./INTEGRATIONS.md)** | External Services | In-depth guides for Squad (GTCO) payments, KingsChat OAuth, Amazon IVS, VDO.Ninja WebRTC, Daily.co, and Brevo. |
| **[Deployment Guide](./DEPLOYMENT.md)** | DevOps & Ops | Production installation on Ubuntu/Debian, Nginx virtual host, PHP-FPM, MySQL, SSL with Certbot, and builds. |
| **[Environment Variables](./ENVIRONMENT_VARIABLES.md)** | Configuration | Exhaustive catalog of all environment variables across frontend, backend, and external service providers. |
| **[Troubleshooting Guide](./TROUBLESHOOTING.md)** | Maintenance | Step-by-step diagnostic workflows for authentication, payment webhooks, live streaming, and database errors. |

---

## Key Platform Specifications

- **Frontend**: React 19, React Router v7, Tailwind CSS v4, Lucide Icons, Vite 8
- **Backend**: PHP (>= 7.4 / 8.x), mysqli, AWS SDK for PHP, cURL, OpenSSL
- **Database**: MySQL 5.7+ / MariaDB 10.3+ with `utf8mb4` character set
- **Streaming Pipeline**:
  - *Browser Studio*: Real-time peer-to-peer WebRTC via clean VDO.Ninja integration
  - *Cloud Studio / Production Switcher*: Amazon Interactive Video Service (IVS) low-latency HLS
  - *Group Video Meetings*: Daily.co WebRTC mesh/SFU video calling
- **Payment Processing**: Squad (HabariPay / GTCO) inline modal and hosted checkout with HMAC-SHA512 webhook signature verification
- **Authentication**: KingsChat OAuth 2.0 and native session-based auth with argon2id / bcrypt password hashing and persistent remember-me tokens

---

## Getting Started

To get the application up and running locally in minutes:

1. **Configure Environment**:
   ```bash
   cp .env.example .env
   cp api/.env.example api/.env
   cp new/.env.example new/.env
   ```
2. **Start Backend API**:
   ```bash
   php -S localhost:8000
   ```
3. **Start Frontend SPA**:
   ```bash
   cd new
   npm install
   npm run dev
   ```
4. Access the studio at `http://localhost:5173`.
