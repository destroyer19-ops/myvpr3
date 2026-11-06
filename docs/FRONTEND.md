# Frontend Documentation — MyVPR Single Page Application

This document provides a comprehensive technical overview of the MyVPR frontend, located in the `new/` directory.

---

## 1. Technologies & Frameworks

- **Core Library**: React 19 (`react` ^19.2.8, `react-dom` ^19.2.8)
- **Build System**: Vite 8 (`vite` ^8.3.0) with `@vitejs/plugin-react`
- **Routing**: React Router v7 (`react-router-dom` ^7.18.4)
- **Styling**: Tailwind CSS v4 (`tailwindcss` ^4.3.3, `@tailwindcss/vite` ^4.3.3)
- **Iconography**: Lucide React (`lucide-react` ^1.48.0)
- **Streaming SDK**: Amazon IVS Web Broadcast (`amazon-ivs-web-broadcast` ^1.40.0)
- **Utilities**: `i18n-iso-countries` for geographical country standards and ISO codes.

---

## 2. Directory Structure

```text
new/
├── public/                 # Static assets (logo.png, icons.svg, favicon.svg)
├── src/
│   ├── assets/             # Bundled visual assets (hero.png, svgs)
│   ├── components/         # Reusable UI component catalog
│   │   ├── layout/         # Shell layouts, navbar, mobile bottom navigation, guards
│   │   │   ├── AppLayout.jsx        # Master application shell
│   │   │   ├── AdminLayout.jsx      # Admin management shell
│   │   │   ├── AuthLayout.jsx       # Guest authentication shell
│   │   │   ├── Navbar.jsx           # Global responsive navigation header
│   │   │   ├── MobileBottomNav.jsx  # Fixed mobile dock navigation
│   │   │   └── RouteGuards.jsx      # ProtectedRoute, GuestRoute, AdminRoute
│   │   ├── onboarding/     # First-time user welcome and guidance modals
│   │   ├── streaming/      # Stream setup modals and username claim dialogs
│   │   └── ui/             # Core form controls, buttons, cards, pills
│   │       └── FormElements.jsx     # Button, Input, Select, Badge, Card
│   ├── constants/          # Static configuration constants (countries, church zones)
│   ├── context/            # React Context state providers
│   │   ├── AuthContext.jsx          # Session state, user profile, login/logout
│   │   └── ThemeContext.jsx         # Dark / Light mode token state
│   ├── data/               # Country lists, full ISO metadata, zone mappings
│   ├── pages/              # Application views mapped to routes
│   │   ├── admin/          # Admin back-office views (CMS, Users, Videos, Subscriptions)
│   │   ├── auth/           # Login, Register, Forgot Password, Reset Password
│   │   ├── streaming/      # Broadcast Studio, Viewer, Cloud Setup, Plans
│   │   │   ├── VdoStudioPage.jsx          # Native MyVPR Browser Studio
│   │   │   ├── VdoViewerPage.jsx          # Real-time WebRTC Audience Watch Page
│   │   │   ├── StreamingDashboardPage.jsx # Stream hub (Browser vs Software)
│   │   │   ├── StreamingSetupPage.jsx     # OBS credentials & Remote Guest setup
│   │   │   ├── StreamingPlansPage.jsx     # Self-service streaming subscriptions
│   │   │   ├── BrowserStudioPage.jsx      # AWS IVS Browser Broadcast Studio
│   │   │   └── PublicStreamPage.jsx       # Dedicated user stream channel
│   │   ├── LandingPage.jsx         # Cinematic public homepage
│   │   ├── DashboardPage.jsx       # User account hub and quick actions
│   │   ├── LiveTvPage.jsx          # 24/7 continuous TV station stream
│   │   ├── VideosPage.jsx          # On-demand video library & search
│   │   ├── WatchPage.jsx           # VOD ministration player
│   │   ├── CrusadesPage.jsx        # Digital crusade campaign manager
│   │   ├── CrusadeRoomPage.jsx     # Interactive live crusade auditorium
│   │   ├── CrusadeAnalyticsPage.jsx# Geolocation and view stats
│   │   ├── MeetingRoomPage.jsx     # Multi-party video watch party
│   │   ├── GivePage.jsx            # Tithes, offerings, and proof uploads
│   │   ├── SalvationPage.jsx       # Evangelism decision intake form
│   │   └── TestimoniesPage.jsx     # Community praise reports & submission
│   ├── services/
│   │   └── api.js          # Centralized asynchronous API client
│   ├── App.jsx             # Route definitions and lazy-loading boundaries
│   ├── main.jsx            # Root application mount point
│   └── index.css           # Global theme foundation and Tailwind v4 setup
├── index.html              # HTML5 entry template
├── vite.config.js          # Vite build configuration, proxy, and aliases
└── package.json            # Dependencies and npm scripts
```

---

## 3. Routing Architecture & Route Guards

Routes are defined in `src/App.jsx` using `react-router-dom` (v7). Every view is asynchronously loaded using `React.lazy()` with `<Suspense fallback={<PageLoader />}>` to ensure sub-3-second initial page load performance.

### Route Guard Enforcement (`src/components/layout/RouteGuards.jsx`)

1. **`ProtectedRoute`**:
   - Inspects `user` from `useAuth()`.
   - If user is not authenticated and session check has completed, redirects to `/login` while preserving the intended destination via `location.pathname`.
2. **`GuestRoute`**:
   - For `/login`, `/register`, `/forgot-password`, `/reset-password`.
   - If an active user session is found, immediately redirects to `/dashboard` to prevent duplicate logins.
3. **`AdminRoute`**:
   - Inspects `user?.is_admin` or `user?.account_type === 'admin'`.
   - Unauthorized users are redirected to `/dashboard` with an access denied message.

---

## 4. State Management

The frontend avoids monolithic third-party state stores (such as Redux) in favor of lightweight, native React Context:

### `AuthContext.jsx`
- Manages `user` object (`id`, `username`, `email`, `account_type`, `has_active_plan`, `is_admin`).
- Exposes async actions: `login(credentials)`, `register(data)`, `logout()`, `refreshUser()`.
- Automatically calls `GET /user/profile` on application mount to validate session cookies.
- Synchronizes authentication state across tabs using `sessionStorage` / cookie headers.

### `ThemeContext.jsx`
- Manages theme mode (`'light'` or `'dark'`).
- Toggles the `dark` class and `data-theme="dark"` attribute on `<html>`.
- Persists user preferences to `localStorage`.

---

## 5. API Client Layer (`src/services/api.js`)

All communication with the PHP backend is centralized through the `request(endpoint, options)` wrapper:

```javascript
// Automatic CSRF Injection for state-changing calls:
if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method.toUpperCase())) {
  if (!csrfToken) {
    const csrfRes = await fetch('/api/index.php/csrf', { credentials: 'include' });
    const csrfData = await csrfRes.json();
    if (csrfData.success) csrfToken = csrfData.csrf_token;
  }
  if (csrfToken) config.headers['X-CSRF-Token'] = csrfToken;
}
```

- **Cookie Credentials**: Always sends `credentials: 'include'` so PHP session cookies accompany all requests.
- **Multipart Form Data**: Automatically removes the `Content-Type` header when sending `FormData` payloads, allowing the browser to append the correct multipart boundary.

---

## 6. Theme Tokens & Design System (`src/index.css`)

MyVPR employs a visual design language centered around reverence, elegance, and modern clarity.

### Color Palette
- **Primary Purple**: `#5b3ee4` (Interactive actions, buttons, active states)
- **Primary Hover**: `#4f2bee`
- **Rose / Offering Accent**: `#e11d48` (Giving badges, live status dots)
- **Emerald / Live Accent**: `#10b981` (On Air pills, success indicators)
- **Canvas Light**: `#fcfcfd`
- **Canvas Dark**: `#0c0d24` / `#030712`

### Typography
- **Headings**: Editorial serif typography via `font-serif` (`Newsreader`, `Fraunces`, `Georgia`).
- **Body & Controls**: Clean grotesque sans-serif via `font-sans` (`Inter`, system SF Pro).
