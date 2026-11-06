import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from '@/context/AuthContext'

/**
 * Wraps routes that require the user to be logged in.
 * Shows a full-screen loader while session is being checked.
 */
export function ProtectedRoute() {
  const { isAuthenticated, loading } = useAuth()

  if (loading) return <AppLoader />
  if (!isAuthenticated) return <Navigate to="/login" replace />
  return <Outlet />
}

/**
 * Wraps routes that require admin access.
 */
export function AdminRoute() {
  const { isAuthenticated, isAdmin, loading } = useAuth()

  if (loading) return <AppLoader />
  if (!isAuthenticated) return <Navigate to="/login" replace />
  if (!isAdmin) return <Navigate to="/dashboard" replace />
  return <Outlet />
}

/**
 * Redirects already-authenticated users away from login/register.
 */
export function GuestRoute() {
  const { isAuthenticated, isAdmin, loading } = useAuth()

  if (loading) return <AppLoader />
  if (isAuthenticated) {
    if (isAdmin) return <Navigate to="/admin" replace />
    return <Navigate to="/dashboard" replace />
  }
  return <Outlet />
}

function AppLoader() {
  return (
    <div className="fixed inset-0 flex items-center justify-center bg-[var(--color-brand-bg)]">
      <div className="flex flex-col items-center gap-4">
        <div className="w-10 h-10 rounded-full border-4 border-[var(--color-brand-border)] border-t-[var(--color-brand-accent)] animate-spin" />
        <span className="text-sm font-medium text-[var(--color-brand-muted)]">Loading…</span>
      </div>
    </div>
  )
}
