import { useState } from 'react'
import { Link } from 'react-router-dom'
import { authApi } from '@/services/api'
import AuthLayout from '@/components/layout/AuthLayout'
import { FormField, Input, Button, Alert } from '@/components/ui/FormElements'
import { z } from 'zod'

const forgotSchema = z.object({
  email: z.string().email('Please enter a valid email address.'),
})

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [sent, setSent] = useState(false)

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')

    const validation = forgotSchema.safeParse({ email })
    if (!validation.success) {
      setError(validation.error.errors[0].message)
      return
    }

    setLoading(true)
    try {
      await authApi.forgotPassword({ email: email.trim() })
      // API always returns success (doesn't reveal if email exists)
      setSent(true)
    } catch (err) {
      setError(err.message || 'Unable to send reset link. Please try again.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <AuthLayout
      title="Reset your password"
      subtitle="Enter your email and we'll send you a reset link"
      footer={
        <>
          Remembered it?{' '}
          <Link to="/login" className="font-semibold text-[var(--color-brand-accent)] hover:underline">
            Back to sign in
          </Link>
        </>
      }
    >
      {sent ? (
        <div className="flex flex-col items-center gap-4 py-4 text-center">
          <div className="w-16 h-16 rounded-full bg-[rgba(43,140,140,0.1)] flex items-center justify-center">
            <svg className="w-8 h-8 text-[var(--color-brand-accent)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
            </svg>
          </div>
          <div>
            <h3 className="text-lg font-bold text-[var(--color-brand-text)]">Check your inbox</h3>
            <p className="mt-1 text-sm text-[var(--color-brand-muted)] leading-relaxed">
              If an account exists for <strong className="text-[var(--color-brand-text)]">{email}</strong>, you'll receive a password reset link shortly.
            </p>
          </div>
          <Alert type="info">
            Don't see it? Check your spam folder.
          </Alert>
          <Link
            to="/login"
            className="mt-2 text-sm font-semibold text-[var(--color-brand-accent)] hover:underline"
          >
            ← Back to sign in
          </Link>
        </div>
      ) : (
        <form onSubmit={handleSubmit} className="flex flex-col gap-5" noValidate>
          {error && <Alert type="error">{error}</Alert>}

          <FormField label="Email address" required>
            <Input
              type="email"
              placeholder="you@example.com"
              value={email}
              onChange={e => setEmail(e.target.value)}
              autoComplete="email"
              autoFocus
              error={error}
            />
          </FormField>

          <Button type="submit" loading={loading}>
            {loading ? 'Sending…' : 'Send Reset Link'}
          </Button>
        </form>
      )}
    </AuthLayout>
  )
}
