import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { useAuth } from '@/context/AuthContext'
import { useTheme } from '@/context/ThemeContext'
import { FormField, Input, PasswordInput, Button, Alert } from '@/components/ui/FormElements'
import { authApi } from '@/services/api'
import { ArrowLeft, Sun, Moon } from 'lucide-react'
import { z } from 'zod'

const loginSchema = z.object({
  username: z.string().min(1, 'Please enter your username.'),
  password: z.string().min(1, 'Please enter your password.'),
})

export default function LoginPage() {
  const { login } = useAuth()
  const { isDark, toggleTheme } = useTheme()
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()

  const [form, setForm] = useState({ username: '', password: '', remember_me: false })
  const [loading, setLoading] = useState(false)
  const [kcLoading, setKcLoading] = useState(false)
  const [error, setError] = useState(searchParams.get('error') || '')

  const set = (field) => (e) => setForm(f => ({ ...f, [field]: e.target.value }))

  const handleKingsChat = async () => {
    setError('')
    setKcLoading(true)
    try {
      const redirect = searchParams.get('redirect')
      const data = await authApi.kingschatStart(redirect)
      if (data?.success && data?.url) {
        window.location.href = data.url
      } else {
        setError(data?.message || 'Could not start KingsChat login. Please try again.')
        setKcLoading(false)
      }
    } catch (err) {
      console.error(err)
      setError(err?.message || 'Unable to connect to KingsChat. Please try again.')
      setKcLoading(false)
    }
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')

    const validation = loginSchema.safeParse(form)
    if (!validation.success) {
      setError(validation.error.errors[0].message)
      return
    }

    setLoading(true)
    try {
      const res = await login({ username: form.username.trim(), password: form.password, remember_me: form.remember_me })
      if (res.success) {
        const redirect = searchParams.get('redirect')
        const userObj = res.user || res.data?.user || res.data
        if (userObj?.role === 'admin' || userObj?.is_admin) {
          navigate(redirect || '/admin', { replace: true })
        } else {
          navigate(redirect || '/dashboard', { replace: true })
        }
      } else {
        setError(res.message || 'Login failed. Please try again.')
      }
    } catch (err) {
      setError(err.message || 'Unable to connect. Please try again.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-screen bg-[#fcfcfd] dark:bg-gray-950 text-gray-950 dark:text-white flex flex-col justify-between relative overflow-x-hidden transition-colors duration-200">
      
      {/* Subtle ambient colorful background glows (matching design references) */}
      <div className="absolute -top-32 -left-32 w-96 h-96 bg-[#5b3ee4]/8 dark:bg-[#5b3ee4]/15 rounded-full blur-3xl pointer-events-none" />
      <div className="absolute top-1/3 -right-32 w-96 h-96 bg-amber-400/8 dark:bg-amber-400/10 rounded-full blur-3xl pointer-events-none" />
      <div className="absolute -bottom-32 left-1/4 w-96 h-96 bg-pink-500/5 dark:bg-pink-500/10 rounded-full blur-3xl pointer-events-none" />

      {/* Top Header / Back Navigation Bar */}
      <header className="w-full max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-6 flex items-center justify-between relative z-20">
        <Link
          to="/"
          className="inline-flex items-center gap-2 px-3.5 py-2 rounded-full text-xs sm:text-sm font-semibold text-gray-700 dark:text-gray-300 hover:text-gray-950 dark:hover:text-white bg-white/80 dark:bg-gray-900/80 hover:bg-white dark:hover:bg-gray-850 border border-gray-200/80 dark:border-gray-800 backdrop-blur-md shadow-xs transition-all active:scale-95 group focus:outline-none focus:ring-2 focus:ring-[#5b3ee4]"
          aria-label="Return to home page"
        >
          <ArrowLeft className="w-4 h-4 transition-transform group-hover:-translate-x-0.5" />
          <span>Back to Home</span>
        </Link>

        {/* Theme toggle switch */}
        <button
          type="button"
          onClick={toggleTheme}
          className="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-white/80 dark:bg-gray-900/80 hover:bg-white dark:hover:bg-gray-850 border border-gray-200/80 dark:border-gray-800 text-gray-700 dark:text-amber-400 flex items-center justify-center transition-colors shadow-xs outline-none focus:ring-2 focus:ring-[#5b3ee4]"
          aria-label={isDark ? "Switch to light mode" : "Switch to dark mode"}
          title={isDark ? "Switch to light mode" : "Switch to dark mode"}
        >
          {isDark ? <Sun className="w-4 h-4 sm:w-5 sm:h-5 text-amber-400" /> : <Moon className="w-4 h-4 sm:w-5 sm:h-5 text-gray-600" />}
        </button>
      </header>

      {/* Main Login Area */}
      <main className="flex-1 flex items-center justify-center px-4 py-8 sm:py-12 relative z-10">
        <div className="w-full max-w-[440px] bg-white dark:bg-gray-900 rounded-3xl p-6 sm:p-10 shadow-[0_20px_60px_rgba(15,23,42,0.08)] dark:shadow-[0_20px_60px_rgba(0,0,0,0.5)] border border-gray-100 dark:border-gray-800 transition-all">
          
          {/* Logo & Headline */}
          <div className="text-center mb-8">
            <Link to="/" className="inline-block mb-3.5 group" aria-label="Virtual Praise Room Home">
              <img 
                src="/logo.png" 
                alt="Virtual Praise Room" 
                className="h-10 w-auto mx-auto group-hover:scale-105 transition-transform" 
              />
            </Link>
            <h1 className="font-serif text-2xl sm:text-3xl font-bold text-gray-950 dark:text-white tracking-tight">
              Welcome back
            </h1>
            <p className="mt-1.5 text-xs sm:text-sm text-gray-500 dark:text-gray-400">
              Sign in to your Virtual Praise Room account
            </p>
          </div>

          {/* Form */}
          <form onSubmit={handleSubmit} className="flex flex-col gap-4 sm:gap-5" noValidate>
            {error && <Alert type="error">{error}</Alert>}

            <FormField label="Username" required>
              <Input
                type="text"
                placeholder="Enter your username"
                value={form.username}
                onChange={set('username')}
                autoComplete="username"
                autoFocus
                error={error}
              />
            </FormField>

            <FormField label="Password" required>
              <PasswordInput
                placeholder="Enter your password"
                value={form.password}
                onChange={set('password')}
                autoComplete="current-password"
                error={error}
              />
            </FormField>

            <div className="flex items-center justify-between text-xs sm:text-sm pt-1">
              <label className="flex items-center gap-2 cursor-pointer select-none">
                <input
                  type="checkbox"
                  checked={form.remember_me}
                  onChange={(e) => setForm(f => ({ ...f, remember_me: e.target.checked }))}
                  className="w-4 h-4 rounded border-gray-300 dark:border-gray-700 text-[#5b3ee4] focus:ring-[#5b3ee4]/20"
                />
                <span className="text-gray-600 dark:text-gray-400 font-medium">Remember me</span>
              </label>
              <Link
                to="/forgot-password"
                className="text-[#5b3ee4] dark:text-[#818cf8] font-semibold hover:underline"
              >
                Forgot password?
              </Link>
            </div>

            <Button type="submit" loading={loading} className="mt-2">
              {loading ? 'Signing in…' : 'Sign In'}
            </Button>

            {/* KingsChat SSO Divider */}
            <div className="relative flex items-center gap-3 my-1">
              <div className="flex-1 h-px bg-gray-100 dark:bg-gray-800" />
              <span className="text-xs text-gray-400 dark:text-gray-500 font-medium">or</span>
              <div className="flex-1 h-px bg-gray-100 dark:bg-gray-800" />
            </div>

            {/* KingsChat Button */}
            <button
              type="button"
              onClick={handleKingsChat}
              disabled={kcLoading || loading}
              className="w-full py-3 px-6 rounded-full text-xs sm:text-sm font-semibold border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-750 text-gray-800 dark:text-gray-200 transition-colors flex items-center justify-center gap-2 shadow-2xs disabled:opacity-60 disabled:cursor-not-allowed cursor-pointer"
            >
              {kcLoading ? (
                <span className="w-4 h-4 border-2 border-gray-400 border-t-gray-700 rounded-full animate-spin" />
              ) : (
                <KingsChatIcon />
              )}
              {kcLoading ? 'Redirecting to KingsChat…' : 'Continue with KingsChat'}
            </button>
          </form>

          {/* Footer note */}
          <div className="mt-6 pt-5 border-t border-gray-100 dark:border-gray-800 text-center text-xs sm:text-sm text-gray-500 dark:text-gray-400">
            Don't have an account?{' '}
            <Link to="/register" className="font-semibold text-[#5b3ee4] dark:text-[#818cf8] hover:underline">
              Sign up free
            </Link>
          </div>

        </div>
      </main>

      {/* Minimal Footer */}
      <footer className="w-full py-4 text-center text-xs text-gray-400 dark:text-gray-600 relative z-10">
        © {new Date().getFullYear()} Virtual Praise Room. All rights reserved.
      </footer>

    </div>
  )
}

function KingsChatIcon() {
  return (
    <svg className="w-5 h-5" viewBox="0 0 24 24" fill="none">
      <rect width="24" height="24" rx="6" fill="#8B5CF6" />
      <path d="M7 8h10M7 12h7M7 16h5" stroke="white" strokeWidth="2" strokeLinecap="round" />
    </svg>
  )
}
