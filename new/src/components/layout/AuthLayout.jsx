import { Link } from 'react-router-dom'

/**
 * AuthLayout — centered card layout used by Register, Forgot Password, Reset Password.
 * Clean, theme-aware layout with soft brand background tints.
 */
export default function AuthLayout({ title, subtitle, children, footer }) {
  return (
    <div className="min-h-screen bg-[#fcfcfd] dark:bg-gray-950 flex flex-col items-center justify-center px-4 py-12 relative">
      {/* Ambient background glows */}
      <div className="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-[#5b3ee4]/5 dark:bg-[#5b3ee4]/10 rounded-full blur-3xl pointer-events-none" />
      <div className="absolute bottom-1/4 right-1/4 w-80 h-80 bg-amber-400/5 dark:bg-amber-400/5 rounded-full blur-3xl pointer-events-none" />

      {/* Logo */}
      <Link to="/" className="mb-6 z-10 group" aria-label="Virtual Praise Room Home">
        <img src="/logo.png" alt="Virtual Praise Room" className="h-10 w-auto mx-auto group-hover:scale-105 transition-transform" />
      </Link>

      {/* Card */}
      <div className="w-full max-w-md bg-white dark:bg-gray-900 rounded-3xl shadow-[0_20px_50px_rgba(15,23,42,0.06)] dark:shadow-[0_20px_50px_rgba(0,0,0,0.4)] border border-gray-100 dark:border-gray-800 overflow-hidden relative z-10">
        {/* Header */}
        <div className="px-8 pt-8 pb-6 text-center">
          <h1 className="font-serif text-2xl sm:text-3xl font-bold text-gray-950 dark:text-white tracking-tight">{title}</h1>
          {subtitle && <p className="mt-1.5 text-sm text-gray-500 dark:text-gray-400">{subtitle}</p>}
        </div>

        {/* Divider */}
        <div className="h-px bg-gray-100 dark:bg-gray-800" />

        {/* Form body */}
        <div className="px-8 py-7">
          {children}
        </div>

        {/* Footer */}
        {footer && (
          <>
            <div className="h-px bg-gray-100 dark:bg-gray-800" />
            <div className="px-8 py-5 text-center text-sm text-gray-500 dark:text-gray-400">
              {footer}
            </div>
          </>
        )}
      </div>
    </div>
  )
}
