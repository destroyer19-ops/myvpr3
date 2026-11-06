/**
 * Shared form UI primitives — used across all auth forms and beyond.
 */

import { useState } from 'react'

// ─── FormField ────────────────────────────────────────────────────────────────
export function FormField({ label, error, children, required }) {
  return (
    <div className="flex flex-col gap-1.5">
      {label && (
        <label className="text-sm font-semibold text-[var(--color-brand-text)]">
          {label}
          {required && <span className="text-[var(--color-danger)] ml-0.5">*</span>}
        </label>
      )}
      {children}
      {error && (
        <p className="text-xs text-[var(--color-danger)] font-medium flex items-center gap-1">
          <span>⚠</span> {error}
        </p>
      )}
    </div>
  )
}

// ─── Label ────────────────────────────────────────────────────────────────────
export function Label({ htmlFor, children, className = '' }) {
  return (
    <label
      htmlFor={htmlFor}
      className={`block text-sm font-semibold text-[var(--color-brand-text)] mb-1 ${className}`}
    >
      {children}
    </label>
  )
}

// ─── Input ────────────────────────────────────────────────────────────────────
export function Input({ error, className = '', ...props }) {
  return (
    <input
      className={`
        w-full px-4 py-3 rounded-xl text-sm text-[var(--color-brand-text)]
        bg-gray-50 dark:bg-gray-800 border transition-all outline-none
        placeholder:text-[var(--color-brand-muted)]
        ${error
          ? 'border-[var(--color-danger)] focus:ring-2 focus:ring-[rgba(239,68,68,0.2)]'
          : 'border-gray-200 dark:border-gray-700 focus:border-[#5b3ee4] dark:focus:border-[#818cf8] focus:ring-2 focus:ring-[#5b3ee4]/20'
        }
        ${className}
      `}
      {...props}
    />
  )
}

// ─── PasswordInput ────────────────────────────────────────────────────────────
export function PasswordInput({ error, className = '', ...props }) {
  const [show, setShow] = useState(false)
  return (
    <div className="relative">
      <input
        type={show ? 'text' : 'password'}
        className={`
          w-full px-4 py-3 pr-11 rounded-xl text-sm text-[var(--color-brand-text)]
          bg-gray-50 dark:bg-gray-800 border transition-all outline-none
          placeholder:text-[var(--color-brand-muted)]
          ${error
            ? 'border-[var(--color-danger)] focus:ring-2 focus:ring-[rgba(239,68,68,0.2)]'
            : 'border-gray-200 dark:border-gray-700 focus:border-[#5b3ee4] dark:focus:border-[#818cf8] focus:ring-2 focus:ring-[#5b3ee4]/20'
          }
          ${className}
        `}
        {...props}
      />
      <button
        type="button"
        onClick={() => setShow(v => !v)}
        className="absolute right-3 top-1/2 -translate-y-1/2 text-[var(--color-brand-muted)] hover:text-[var(--color-brand-text)] transition-colors p-1"
        tabIndex={-1}
        aria-label={show ? 'Hide password' : 'Show password'}
      >
        {show ? <EyeOffIcon /> : <EyeIcon />}
      </button>
    </div>
  )
}

// ─── Select ───────────────────────────────────────────────────────────────────
export function Select({ error, className = '', children, ...props }) {
  return (
    <select
      className={`
        w-full px-4 py-3 rounded-xl text-sm text-[var(--color-brand-text)]
        bg-gray-50 dark:bg-gray-800 border transition-all outline-none appearance-none
        ${error
          ? 'border-[var(--color-danger)] focus:ring-2 focus:ring-[rgba(239,68,68,0.2)]'
          : 'border-gray-200 dark:border-gray-700 focus:border-[#5b3ee4] dark:focus:border-[#818cf8] focus:ring-2 focus:ring-[#5b3ee4]/20'
        }
        ${className}
      `}
      {...props}
    >
      {children}
    </select>
  )
}

// ─── Button ───────────────────────────────────────────────────────────────────
export function Button({ loading, children, variant = 'primary', className = '', ...props }) {
  const base = 'w-full py-3 px-6 rounded-full text-sm font-bold transition-all disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2 cursor-pointer'
  const variants = {
    primary:     'bg-[#5b3ee4] hover:bg-[#4f2bee] text-white shadow-md active:scale-95',
    secondary:   'bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-750',
    outline:     'border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-full',
    ghost:       'bg-transparent text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-full border-0',
    danger:      'bg-[var(--color-danger)] text-white hover:opacity-90 rounded-full',
    destructive: 'bg-[var(--color-danger)] text-white hover:opacity-90 rounded-full',
  }
  return (
    <button
      className={`${base} ${variants[variant] || variants.primary} ${className}`}
      disabled={loading || props.disabled}
      {...props}
    >
      {loading && (
        <span className="w-4 h-4 rounded-full border-2 border-white/30 border-t-white animate-spin" />
      )}
      {children}
    </button>
  )
}

// ─── Alert ────────────────────────────────────────────────────────────────────
export function Alert({ type = 'error', children }) {
  const styles = {
    error:   'bg-red-50 border-red-200 text-red-700',
    success: 'bg-green-50 border-green-200 text-green-700',
    info:    'bg-[rgba(43,140,140,0.07)] border-[var(--color-brand-border)] text-[var(--color-brand-accent)]',
  }
  return (
    <div className={`px-4 py-3 rounded-xl border text-sm font-medium ${styles[type]}`}>
      {children}
    </div>
  )
}

// ─── PageWrapper ──────────────────────────────────────────────────────────────
export function PageWrapper({ children, className = '' }) {
  return (
    <div className={`min-h-screen bg-[var(--color-brand-bg)] pb-24 lg:pb-8 ${className}`}>
      {children}
    </div>
  )
}

// ─── Card ─────────────────────────────────────────────────────────────────────
export function Card({ children, className = '', hover = false, ...props }) {
  return (
    <div
      className={`bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-3xl shadow-sm ${
        hover ? 'hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 cursor-pointer' : ''
      } ${className}`}
      {...props}
    >
      {children}
    </div>
  )
}

// ─── PageHeader ───────────────────────────────────────────────────────────────
export function PageHeader({ title, subtitle, action }) {
  return (
    <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
      <div>
        <h1 className="text-2xl sm:text-3xl font-bold text-[var(--color-brand-text)] leading-tight">{title}</h1>
        {subtitle && <p className="text-[var(--color-brand-muted)] mt-1 text-sm">{subtitle}</p>}
      </div>
      {action && <div className="shrink-0">{action}</div>}
    </div>
  )
}

// ─── EmptyState ───────────────────────────────────────────────────────────────
export function EmptyState({ icon, title, description, action }) {
  return (
    <div className="flex flex-col items-center justify-center py-20 text-center px-4">
      {icon && <div className="text-[var(--color-brand-border)] mb-5 opacity-60">{icon}</div>}
      <h3 className="text-lg font-bold text-[var(--color-brand-text)] mb-2">{title}</h3>
      {description && <p className="text-[var(--color-brand-muted)] text-sm max-w-sm mb-6">{description}</p>}
      {action}
    </div>
  )
}

// ─── ErrorState ───────────────────────────────────────────────────────────────
export function ErrorState({ message, onRetry }) {
  return (
    <div className="flex flex-col items-center justify-center py-20 text-center px-4">
      <div className="w-12 h-12 rounded-full bg-red-50 flex items-center justify-center mb-4">
        <span className="text-[var(--color-danger)] text-xl">⚠️</span>
      </div>
      <h3 className="text-lg font-bold text-[var(--color-brand-text)] mb-2">Something went wrong</h3>
      <p className="text-[var(--color-brand-muted)] text-sm mb-6">{message}</p>
      {onRetry && (
        <button
          onClick={onRetry}
          className="px-6 py-2.5 rounded-full bg-[var(--color-brand-accent)] text-white text-sm font-bold hover:bg-[var(--color-brand-accent-h)] transition-colors"
        >
          Try Again
        </button>
      )}
    </div>
  )
}

// ─── LoadingSpinner ───────────────────────────────────────────────────────────
export function LoadingSpinner({ size = 'md', className = '' }) {
  const sizes = { sm: 'w-5 h-5', md: 'w-8 h-8', lg: 'w-12 h-12' }
  return (
    <div className={`${sizes[size]} rounded-full border-[3px] border-[var(--color-brand-border)] border-t-[var(--color-brand-accent)] animate-spin ${className}`} />
  )
}

// ─── PageLoading ──────────────────────────────────────────────────────────────
export function PageLoading({ message = 'Loading...' }) {
  return (
    <div className="flex flex-col items-center justify-center min-h-[50vh] gap-3">
      <LoadingSpinner size="lg" />
      <p className="text-[var(--color-brand-muted)] text-sm">{message}</p>
    </div>
  )
}

// ─── Badge ────────────────────────────────────────────────────────────────────
export function Badge({ children, variant = 'default', className = '' }) {
  const variants = {
    default: 'bg-[var(--color-brand-bg)] text-[var(--color-brand-muted)] border border-[var(--color-brand-border)]',
    primary: 'bg-[rgba(43,140,140,0.1)] text-[var(--color-brand-accent)]',
    success: 'bg-green-50 text-[var(--color-success)]',
    danger:  'bg-red-50 text-[var(--color-danger)]',
    warning: 'bg-amber-50 text-[var(--color-warning)]',
    live:    'bg-red-500 text-white animate-pulse',
  }
  return (
    <span className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold ${variants[variant] || variants.default} ${className}`}>
      {children}
    </span>
  )
}

// ─── Icons ────────────────────────────────────────────────────────────────────
function EyeIcon() {
  return (
    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
      <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
    </svg>
  )
}

function EyeOffIcon() {
  return (
    <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
    </svg>
  )
}
