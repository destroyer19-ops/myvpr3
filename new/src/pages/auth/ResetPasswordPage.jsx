import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { authApi } from '@/services/api'
import AuthLayout from '@/components/layout/AuthLayout'
import { FormField, PasswordInput, Button, Alert } from '@/components/ui/FormElements'
import { z } from 'zod'

const resetSchema = z.object({
  password: z.string().min(6, 'Password must be at least 6 characters'),
  confirm_password: z.string(),
}).refine((data) => data.password === data.confirm_password, {
  message: 'Passwords do not match',
  path: ['confirm_password'],
})

export default function ResetPasswordPage() {
  const [searchParams] = useSearchParams()
  const token = searchParams.get('token')
  const email = searchParams.get('email')
  const navigate = useNavigate()

  const [form, setForm] = useState({ password: '', confirm_password: '' })
  const [errors, setErrors] = useState({})
  const [loading, setLoading] = useState(false)
  const [apiError, setApiError] = useState('')
  const [success, setSuccess] = useState(false)

  const set = (field) => (e) => {
    setForm(f => ({ ...f, [field]: e.target.value }))
    setErrors(er => ({ ...er, [field]: '' }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setApiError('')
    setErrors({})

    const validation = resetSchema.safeParse(form)
    if (!validation.success) {
      const fieldErrors = {}
      validation.error.issues.forEach(issue => {
        fieldErrors[issue.path[0]] = issue.message
      })
      setErrors(fieldErrors)
      return
    }

    if (!token || !email) {
      setApiError('Invalid reset link. Please request a new password reset.')
      return
    }

    setLoading(true)
    try {
      const res = await authApi.resetPassword({ email, token, password: form.password })
      if (res.success) {
        setSuccess(true)
        setTimeout(() => navigate('/login', { replace: true }), 3000)
      } else {
        setApiError(res.message || 'Failed to reset password.')
      }
    } catch (err) {
      setApiError(err.message || 'Unable to connect. Please try again.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <AuthLayout
      title="Create new password"
      subtitle="Enter your new password below"
      footer={
        <Link to="/login" className="font-semibold text-[var(--color-brand-accent)] hover:underline">
          Back to sign in
        </Link>
      }
    >
      {success ? (
        <div className="flex flex-col items-center gap-4 py-4 text-center">
          <div className="w-16 h-16 rounded-full bg-[rgba(43,140,140,0.1)] flex items-center justify-center">
            <svg className="w-8 h-8 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <div>
            <h3 className="text-lg font-bold text-[var(--color-brand-text)]">Password reset successful</h3>
            <p className="mt-1 text-sm text-[var(--color-brand-muted)]">
              Redirecting you to login...
            </p>
          </div>
        </div>
      ) : (
        <form onSubmit={handleSubmit} className="flex flex-col gap-5" noValidate>
          {apiError && <Alert type="error">{apiError}</Alert>}

          <FormField label="New Password" required error={errors.password}>
            <PasswordInput
              placeholder="Min. 6 characters"
              value={form.password}
              onChange={set('password')}
              error={errors.password}
              autoFocus
            />
          </FormField>

          <FormField label="Confirm Password" required error={errors.confirm_password}>
            <PasswordInput
              placeholder="Repeat your password"
              value={form.confirm_password}
              onChange={set('confirm_password')}
              error={errors.confirm_password}
            />
          </FormField>

          <Button type="submit" loading={loading}>
            {loading ? 'Resetting...' : 'Reset Password'}
          </Button>
        </form>
      )}
    </AuthLayout>
  )
}
