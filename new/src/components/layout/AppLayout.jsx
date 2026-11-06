import { Outlet, useLocation } from 'react-router-dom'
import Navbar from './Navbar'
import MobileBottomNav from './MobileBottomNav'
import { useAuth } from '@/context/AuthContext'
import UsernameUpdateModal from '@/components/streaming/UsernameUpdateModal'

/**
 * AppLayout — wraps all app pages with the Navbar and (if authenticated)
 * the mobile bottom navigation. Adds correct body padding so content
 * doesn't hide under the fixed bars.
 * For /login, the global navbar is hidden as requested.
 */
export default function AppLayout() {
  const { isAuthenticated } = useAuth()
  const location = useLocation()
  const isLoginPage = location.pathname === '/login'

  if (isLoginPage) {
    return (
      <div className="flex flex-col min-h-dvh bg-[#fcfcfd] dark:bg-gray-950 text-gray-950 dark:text-white">
        <main className="flex-1 flex flex-col min-h-dvh">
          <Outlet />
        </main>
      </div>
    )
  }

  return (
    <div className="flex flex-col min-h-dvh">
      <Navbar />

      {/* Main content — padded for mobile bottom nav (80px) if authenticated, and pt-24 sm:pt-28 to clear fixed Navbar */}
      <main className={`flex-1 w-full max-w-full min-w-0 overflow-x-clip pt-24 sm:pt-28 ${isAuthenticated ? 'pb-20 lg:pb-0' : ''}`}>
        <Outlet />
      </main>

      {isAuthenticated && <MobileBottomNav />}
      
      {/* Required Modals */}
      <UsernameUpdateModal />
    </div>
  )
}
