import { lazy, Suspense } from 'react'
import { Routes, Route } from 'react-router-dom'
import { ProtectedRoute, AdminRoute, GuestRoute } from '@/components/layout/RouteGuards'
import AdminLayout from '@/components/layout/AdminLayout'
import AppLayout from '@/components/layout/AppLayout'

// ─── Lazy-loaded Pages ────────────────────────────────────────────────────
// Public
const LandingPage    = lazy(() => import('@/pages/LandingPage'))
const LoginPage      = lazy(() => import('@/pages/auth/LoginPage'))
const RegisterPage   = lazy(() => import('@/pages/auth/RegisterPage'))
const ForgotPage     = lazy(() => import('@/pages/auth/ForgotPasswordPage'))
const ResetPage      = lazy(() => import('@/pages/auth/ResetPasswordPage'))

// Protected
const DashboardPage      = lazy(() => import('@/pages/DashboardPage'))
const ProfilePage        = lazy(() => import('@/pages/ProfilePage'))

// Streaming
const StreamingDashboardPage = lazy(() => import('@/pages/streaming/StreamingDashboardPage'))
const StreamingPlansPage     = lazy(() => import('@/pages/streaming/StreamingPlansPage'))
const StreamingSetupPage     = lazy(() => import('@/pages/streaming/StreamingSetupPage'))
const PublicStreamPage       = lazy(() => import('@/pages/streaming/PublicStreamPage'))
const BrowserStudioPage      = lazy(() => import('@/pages/streaming/BrowserStudioPage'))
const VdoStudioPage          = lazy(() => import('@/pages/streaming/VdoStudioPage'))
const VdoViewerPage          = lazy(() => import('@/pages/streaming/VdoViewerPage'))
const RealtimeStudioPage     = lazy(() => import('@/pages/streaming/RealtimeStudioPage'))
const RealtimeViewerPage     = lazy(() => import('@/pages/streaming/RealtimeViewerPage'))
const VideosPage         = lazy(() => import('@/pages/VideosPage'))
const WatchPage          = lazy(() => import('@/pages/WatchPage'))
const LiveTvPage         = lazy(() => import('@/pages/LiveTvPage'))
const CrusadesPage       = lazy(() => import('@/pages/CrusadesPage'))
const CrusadeRoomPage    = lazy(() => import('@/pages/CrusadeRoomPage'))
const MeetingRoomPage    = lazy(() => import('@/pages/MeetingRoomPage'))
const CreateMeetingPage  = lazy(() => import('@/pages/CreateMeetingPage'))
const SubscribePage      = lazy(() => import('@/pages/SubscribePage'))
const AboutPage          = lazy(() => import('@/pages/AboutPage'))
const ContactPage        = lazy(() => import('@/pages/ContactPage'))
const GivePage           = lazy(() => import('@/pages/GivePage'))
const SalvationPage      = lazy(() => import('@/pages/SalvationPage'))

// Admin
const AdminDashboard     = lazy(() => import('@/pages/admin/AdminDashboard'))
const AdminRecordings    = lazy(() => import('@/pages/admin/AdminRecordings'))
const AdminVideos        = lazy(() => import('@/pages/admin/AdminVideos'))
const AdminStreams        = lazy(() => import('@/pages/admin/AdminStreams'))
const AdminUsers         = lazy(() => import('@/pages/admin/AdminUsers'))
const AdminSubscriptions = lazy(() => import('@/pages/admin/AdminSubscriptions'))
const AdminCMS           = lazy(() => import('@/pages/admin/AdminCMS'))
const AdminSalvations    = lazy(() => import('@/pages/admin/AdminSalvations'))
const AdminCrusades      = lazy(() => import('@/pages/admin/AdminCrusades'))
const AdminOfferings     = lazy(() => import('@/pages/admin/AdminOfferings'))
const AdminTestimonies   = lazy(() => import('@/pages/admin/AdminTestimonies'))
const AdminStudioPlans   = lazy(() => import('@/pages/admin/AdminStudioPlans'))
const AdminCompareStreaming = lazy(() => import('@/pages/admin/AdminCompareStreaming'))
const TestimoniesPage    = lazy(() => import('@/pages/TestimoniesPage'))
const CrusadeAnalyticsPage = lazy(() => import('@/pages/CrusadeAnalyticsPage'))

// ─── Page Fallback ────────────────────────────────────────────────────────
function PageLoader() {
  return (
    <div className="flex flex-1 items-center justify-center min-h-screen bg-[var(--color-brand-bg)]">
      <div className="w-8 h-8 rounded-full border-4 border-[var(--color-brand-border)] border-t-[var(--color-brand-accent)] animate-spin" />
    </div>
  )
}

export default function App() {
  return (
    <Suspense fallback={<PageLoader />}>
      <Routes>
        {/* All routes share AppLayout (Navbar + optional bottom nav) */}
        <Route element={<AppLayout />}>

          {/* ── Public routes ─────────────────────────────────────── */}
          <Route path="/"        element={<LandingPage />} />
          <Route path="/about"   element={<AboutPage />} />
          <Route path="/contact" element={<ContactPage />} />
          <Route path="/give"    element={<GivePage />} />
          <Route path="/salvation" element={<SalvationPage />} />
          <Route path="/stream/:username" element={<PublicStreamPage />} />
          <Route path="/watch/vdo/:streamId" element={<VdoViewerPage />} />
          <Route path="/watch/realtime-test/:username" element={<RealtimeViewerPage />} />
          <Route path="/crusade/:code" element={<CrusadeRoomPage />} />
          <Route path="/testimonies" element={<TestimoniesPage />} />

          {/* Studio Landing & Plans (Public) */}
          <Route path="/dashboard/streaming"       element={<StreamingDashboardPage />} />
          <Route path="/dashboard/streaming/plans" element={<StreamingPlansPage />} />

          {/* Studio Landing & Plans (Public) */}
          
          

          {/* ── Guest-only routes (redirect logged-in users to dashboard) */}
          <Route element={<GuestRoute />}>
            <Route path="/login"           element={<LoginPage />} />
            <Route path="/register"        element={<RegisterPage />} />
            <Route path="/forgot-password" element={<ForgotPage />} />
            <Route path="/reset-password"  element={<ResetPage />} />
          </Route>

          {/* ── Protected routes (require login) ─────────────────── */}
          <Route element={<ProtectedRoute />}>
            <Route path="/dashboard"         element={<DashboardPage />} />
            <Route path="/profile"           element={<ProfilePage />} />
            
            {/* Self-Service Streaming Dashboard */}
            <Route path="/dashboard/streaming/setup" element={<StreamingSetupPage />} />
            <Route path="/studio"                    element={<VdoStudioPage />} />
            <Route path="/studio/vdo"                element={<VdoStudioPage />} />
            <Route path="/studio/aws"                element={<BrowserStudioPage />} />
            <Route path="/studio/realtime-test"      element={<RealtimeStudioPage />} />
            
            <Route path="/videos"            element={<VideosPage />} />
            <Route path="/watch/:id"         element={<WatchPage />} />
            <Route path="/watch/recording/:id" element={<WatchPage />} />
            <Route path="/live-tv"           element={<LiveTvPage />} />
            <Route path="/crusades"          element={<CrusadesPage />} />
            <Route path="/crusades/:code/analytics" element={<CrusadeAnalyticsPage />} />
            
            <Route path="/meeting/create"    element={<CreateMeetingPage />} />
            <Route path="/meeting/:code"     element={<MeetingRoomPage />} />
            <Route path="/subscribe"         element={<SubscribePage />} />
          </Route>

          

          {/* ── 404 ──────────────────────────────────────────────── */}
          <Route path="*" element={<NotFound />} />

        </Route>

        {/* Admin Shell */}
        <Route element={<AdminLayout />}>
          {/* ── Admin routes ──────────────────────────────────────── */}
          <Route path="/admin" element={<AdminRoute />}>
            <Route index                element={<AdminDashboard />} />
            <Route path="recordings"    element={<AdminRecordings />} />
            <Route path="videos"        element={<AdminVideos />} />
            <Route path="streams"       element={<AdminStreams />} />
            <Route path="studio-plans"  element={<AdminStudioPlans />} />
            <Route path="users"         element={<AdminUsers />} />
            <Route path="subscriptions" element={<AdminSubscriptions />} />
            <Route path="cms"           element={<AdminCMS />} />
            <Route path="salvations"    element={<AdminSalvations />} />
            <Route path="crusades"      element={<AdminCrusades />} />
            <Route path="offerings"     element={<AdminOfferings />} />
            <Route path="testimonies"   element={<AdminTestimonies />} />
            <Route path="studio/compare" element={<AdminCompareStreaming />} />
          </Route>
        </Route>
      </Routes>
    </Suspense>
  )
}

function NotFound() {
  return (
    <div className="flex flex-col flex-1 items-center justify-center min-h-screen gap-4 text-center p-6">
      <span className="text-7xl">🌊</span>
      <h1 className="text-2xl font-bold text-[var(--color-brand-text)]">Page Not Found</h1>
      <p className="text-[var(--color-brand-muted)]">The page you're looking for doesn't exist.</p>
      <a href="/" className="mt-2 px-6 py-3 rounded-full bg-[var(--color-brand-accent)] text-white font-semibold text-sm hover:bg-[var(--color-brand-accent-h)] transition-colors">
        Go Home
      </a>
    </div>
  )
}
