# Platform Overview — Virtual Praise Room (MyVPR)

## 1. What is MyVPR?

**Virtual Praise Room (MyVPR)** is a specialized Christian digital broadcasting, praise worship, and evangelism platform. It connects churches, ministries, pastors, and individual believers across nations into a single unified space for live church services, global digital crusades, interactive prayer meetings, video-on-demand ministration archives, and online giving.

The platform eliminates the need for expensive dedicated broadcasting infrastructure for everyday ministers by providing a browser-native studio, while also supporting professional high-capacity broadcasts through production switchers (OBS Studio, vMix, Wirecast) and Amazon Web Services (AWS) media pipelines.

---

## 2. Core User Workflows

### A. Broadcaster / Minister Workflow
1. **Studio Setup**: The minister navigates to the **MyVPR Browser Studio** (`/studio`).
2. **Camera & Hardware Check**: The studio activates local device preview, allowing the broadcaster to adjust lighting, camera selection, and test microphone levels using a visual VU meter.
3. **Live Broadcasting**: With one click, the broadcast goes live via sub-second WebRTC.
4. **Congregation Sharing**: The broadcaster copies their clean, branded MyVPR watch link (`https://myvpr.org/watch/vdo/vpr_username_xxxxx`) or shares it directly to WhatsApp with one click.
5. **Audience Interaction**: Viewers watch in real-time, submit prayer requests, make salvation decisions, and give offerings directly from the watch page.

### B. Congregation / Viewer Workflow
1. **Access**: Viewers click a shared broadcast link or open the MyVPR home page (`/`).
2. **Instant Playback**: Video playback begins immediately without delays. Browser autoplay restrictions are gracefully handled with an interactive 1-click unmute banner.
3. **Ministry Engagement**:
   - **Tithe & Offerings**: Viewers can submit financial gifts via Squad (cards, bank transfers, USSD) or upload transfer proofs (`/give`).
   - **Salvation Decisions**: Believers accepting Christ can record their decision (`/salvation`) to receive pastoral follow-up and discipleship literature.
   - **Testimonies**: Users submit written praise reports (`/testimonies`), which are reviewed by administrators and published.

### C. Digital Crusade Host Workflow
1. **Creation**: Pastors create custom digital crusade campaigns (`/crusades`), specifying title, description, time zone, start/end dates, and banner art.
2. **Audience Outreach**: Hosts share their unique crusade code link (`/crusade/:code`).
3. **Real-time Analytics**: Hosts track unique visitors, view counts, salvation clicks, and geographical zone distributions via real-time analytics dashboards (`/crusades/:code/analytics`).

### D. Group Praise Meeting Workflow
1. **Room Creation**: Users create private or public prayer meetings (`/meeting/create`).
2. **Interactive Video Calling**: Up to 200 believers join video watch parties powered by Daily.co WebRTC mesh/SFU infrastructure (`/meeting/:code`).

---

## 3. Major Platform Modules

```
┌────────────────────────────────────────────────────────────────────────┐
│                        MyVPR Application Suite                         │
├──────────────────┬──────────────────┬─────────────────┬────────────────┤
│ 1. Studio &      │ 2. Digital       │ 3. Media & VOD  │ 4. Ministry    │
│    Broadcasting  │    Crusades      │    Library      │    Engagement  │
│ ──────────────── │ ──────────────── │ ─────────────── │ ────────────── │
│ • Browser Studio │ • Crusade Rooms  │ • Live TV 24/7  │ • Offerings    │
│ • Amazon IVS Hub │ • Real-time Stats│ • Ministration  │ • Salvations   │
│ • OBS Connect    │ • Geolocation    │ • Program CMS   │ • Testimonies  │
│ • Watch Pages    │ • Live Chat      │ • Recordings    │ • Subscriptions│
└──────────────────┴──────────────────┴─────────────────┴────────────────┘
```

1. **Studio & Live Broadcasting Module**:
   - **Browser Studio**: Zero-install broadcasting from webcams and USB capture cards.
   - **Cloud Studio (AWS IVS)**: Enterprise low-latency RTMP/HLS ingestion for large Sunday services.
   - **Public Watch Pages**: Responsive player with fullscreen, PiP, and ministry sidebar.

2. **Digital Crusades Module**:
   - Creation and scheduling of evangelism campaigns.
   - Real-time attendee counter and salvation tracking.
   - Live interactive commenting and moderation.

3. **Media & Program Library Module**:
   - 24/7 continuous Live TV channel stream.
   - Structured program recording catalog with categories (Children Outreach, Movie Outreach, Ministrations).
   - "Continue Watching" user viewing history tracking.

4. **Monetization & Ministry Engagement Module**:
   - Dynamic tier-based subscriptions (Member, Partner, Ministry).
   - Squad (GTCO) payment gateway integration with recurring billing.
   - Offline proof-of-payment upload workflow.
   - Evangelism decision logs and discipleship follow-up queues.

---

## 4. High-Level Architectural Summary

MyVPR utilizes a decoupled Single Page Application (SPA) frontend architecture paired with a stateless, secure PHP REST backend:

- **Frontend Tier**: Client-side React 19 application built with Vite, utilizing modern CSS variables and Tailwind utility tokens. It communicates with the backend solely over JSON REST APIs with session cookies.
- **Backend Tier**: Custom, dependency-light PHP REST API with single-entry-point routing (`api/index.php`), modular endpoint controllers, and decoupled services.
- **Data Tier**: Relational MySQL database holding relational schemas for users, meetings, crusades, analytics, programs, recordings, subscriptions, and transactions.
- **Third-Party Services Tier**: Managed media and authentication clouds (AWS IVS, VDO.Ninja, Daily.co, KingsChat OAuth, Squad GTCO, Brevo).
