import { Outlet, Link } from 'react-router-dom'
import { useAuth } from '@/context/AuthContext'

export default function AdminLayout() {
  const { logout } = useAuth()

  return (
    <div className="min-h-screen bg-[var(--color-brand-bg)] flex flex-col">
      {/* Admin Top Navbar */}
      <header className="sticky top-0 z-50 bg-[var(--color-brand-surface)] border-b border-[var(--color-brand-border)]">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between h-16">
            <div className="flex items-center gap-4">
              <Link to="/admin" className="font-bold text-lg text-[var(--color-brand-text)]">
                Admin Panel
              </Link>
              <nav className="hidden md:flex gap-4">
                <Link to="/admin/recordings" className="text-sm font-medium text-[var(--color-brand-muted)] hover:text-[var(--color-brand-text)] transition-colors">Recordings CMS</Link>
                <Link to="/admin/videos" className="text-sm font-medium text-[var(--color-brand-muted)] hover:text-[var(--color-brand-text)] transition-colors">Videos</Link>
                <Link to="/admin/streams" className="text-sm font-medium text-[var(--color-brand-muted)] hover:text-[var(--color-brand-text)] transition-colors">Streams</Link>
                <Link to="/admin/users" className="text-sm font-medium text-[var(--color-brand-muted)] hover:text-[var(--color-brand-text)] transition-colors">Users</Link>
                <Link to="/admin/studio/compare" className="text-sm font-medium text-[var(--color-brand-accent)] hover:text-[var(--color-brand-text)] transition-colors">Compare Streams</Link>
              </nav>
            </div>
            <div className="flex items-center gap-4">
              <Link to="/dashboard" className="text-sm font-medium text-[var(--color-brand-accent)] hover:underline">
                Public Site
              </Link>
              <button 
                onClick={logout}
                className="text-sm font-medium text-red-500 hover:text-red-400 transition-colors"
              >
                Logout
              </button>
            </div>
          </div>
        </div>
      </header>

      {/* Main Content Area */}
      <main className="flex-1 w-full relative">
        <Outlet />
      </main>
    </div>
  )
}
