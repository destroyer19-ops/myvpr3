import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '@/context/AuthContext'
import { authApi } from '@/services/api'
import AuthLayout from '@/components/layout/AuthLayout'
import { FormField, Input, PasswordInput, Select, Button, Alert } from '@/components/ui/FormElements'
import { COUNTRIES } from '@/constants/countries'
import { ZONES } from '@/constants/zones'
import { z } from 'zod'

const step1Schema = z.object({
  username: z.string().min(1, 'Username is required.').regex(/^[a-zA-Z0-9_]+$/, 'Only letters, numbers, and underscores.'),
  email: z.string().email('Enter a valid email.'),
  password: z.string().min(6, 'At least 6 characters.'),
  confirm_password: z.string(),
}).refine((data) => data.password === data.confirm_password, {
  message: 'Passwords do not match.',
  path: ['confirm_password'],
})

const step2Schema = z.object({
  country: z.string().min(1, 'Please select your country.'),
  satellite_campus: z.string().min(1, 'Satellite campus is required.'),
})

const ACCOUNT_TYPES = [
  { value: 'individual', label: '👤 Individual' },
  { value: 'church', label: '⛪ Church / Group' },
]

const initial = {
  username: '', email: '', password: '', confirm_password: '',
  account_type: 'individual', kc_handle: '',
  country: '', city: '', region: '', satellite_campus: '', church: '',
}

export default function RegisterPage() {
  const { login } = useAuth()
  const navigate = useNavigate()

  const [form, setForm] = useState(initial)
  const [errors, setErrors] = useState({})
  const [apiError, setApiError] = useState('')
  const [loading, setLoading] = useState(false)
  const [step, setStep] = useState(1) // 2-step: credentials → church info

  const set = (field) => (e) => {
    setForm(f => ({ ...f, [field]: e.target.value }))
    setErrors(er => ({ ...er, [field]: '' }))
  }

  // ─── Validate step 1 ──────────────────────────────────────────────────────
  const validateStep1 = () => {
    const validation = step1Schema.safeParse(form)
    if (!validation.success) {
      const errs = {}
      validation.error.issues.forEach(issue => {
        errs[issue.path[0]] = issue.message
      })
      setErrors(errs)
      return false
    }
    setErrors({})
    return true
  }

  const handleNextStep = (e) => {
    e.preventDefault()
    if (validateStep1()) setStep(2)
  }

  // ─── Final submit ─────────────────────────────────────────────────────────
  const handleSubmit = async (e) => {
    e.preventDefault()
    setApiError('')

    const validation = step2Schema.safeParse(form)
    if (!validation.success) {
      const errs = {}
      validation.error.issues.forEach(issue => {
        errs[issue.path[0]] = issue.message
      })
      setErrors(errs)
      return
    }

    setLoading(true)
    try {
      const res = await authApi.register({
        username: form.username.trim(),
        email: form.email.trim(),
        password: form.password,
        account_type: form.account_type,
        kc_handle: form.kc_handle.trim(),
        country: form.country,
        city: form.city.trim(),
        region: form.region,
        satellite_campus: form.satellite_campus.trim(),
        church: form.church.trim(),
      })

      if (res.success) {
        // API sets the session; update auth context
        await login({ username: form.username.trim(), password: form.password })
        navigate('/dashboard', { replace: true })
      } else {
        setApiError(res.message || 'Registration failed.')
        if (res.message?.toLowerCase().includes('username')) setStep(1)
        if (res.message?.toLowerCase().includes('email')) setStep(1)
      }
    } catch (err) {
      setApiError(err.message || 'Unable to register. Please try again.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <AuthLayout
      title={step === 1 ? 'Create your account' : 'Church & location'}
      subtitle={
        step === 1
          ? 'Join the Virtual Praise Room community'
          : 'Help us connect you with your community'
      }
      footer={
        <>
          Already have an account?{' '}
          <Link to="/login" className="font-semibold text-[var(--color-brand-accent)] hover:underline">
            Sign in
          </Link>
        </>
      }
    >
      {/* Step indicator */}
      <div className="flex items-center gap-2 mb-6">
        {[1, 2].map(s => (
          <div key={s} className="flex items-center gap-2">
            <div
              className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-colors ${
                s === step
                  ? 'bg-[var(--color-brand-accent)] text-white'
                  : s < step
                  ? 'bg-[var(--color-brand-accent)] text-white'
                  : 'bg-[var(--color-brand-border)] text-[var(--color-brand-muted)]'
              }`}
            >
              {s < step ? '✓' : s}
            </div>
            {s < 2 && <div className={`flex-1 h-0.5 w-16 ${s < step ? 'bg-[var(--color-brand-accent)]' : 'bg-[var(--color-brand-border)]'}`} />}
          </div>
        ))}
        <span className="ml-auto text-xs text-[var(--color-brand-muted)] font-medium">Step {step} of 2</span>
      </div>

      {apiError && <Alert type="error" className="mb-4">{apiError}</Alert>}

      {/* ── Step 1: Credentials ───────────────────────────────────────────── */}
      {step === 1 && (
        <form onSubmit={handleNextStep} className="flex flex-col gap-5" noValidate>
          {/* Account type tabs */}
          <div className="flex gap-2 p-1 bg-[var(--color-brand-bg)] rounded-xl">
            {ACCOUNT_TYPES.map(t => (
              <button
                key={t.value}
                type="button"
                onClick={() => setForm(f => ({ ...f, account_type: t.value }))}
                className={`flex-1 py-2.5 px-3 rounded-lg text-sm font-semibold transition-all ${
                  form.account_type === t.value
                    ? 'bg-white text-[var(--color-brand-accent)] shadow-sm border border-[var(--color-brand-border)]'
                    : 'text-[var(--color-brand-muted)] hover:text-[var(--color-brand-text)]'
                }`}
              >
                {t.label}
              </button>
            ))}
          </div>

          <FormField label="Username" error={errors.username} required>
            <Input
              type="text"
              placeholder="e.g. john_doe"
              value={form.username}
              onChange={set('username')}
              autoComplete="username"
              autoFocus
              error={errors.username}
            />
          </FormField>

          <FormField label="Email address" error={errors.email} required>
            <Input
              type="email"
              placeholder="you@example.com"
              value={form.email}
              onChange={set('email')}
              autoComplete="email"
              error={errors.email}
            />
          </FormField>

          <FormField label="KingsChat handle" error={errors.kc_handle}>
            <Input
              type="text"
              placeholder="@yourhandle (optional)"
              value={form.kc_handle}
              onChange={set('kc_handle')}
              error={errors.kc_handle}
            />
          </FormField>

          <FormField label="Password" error={errors.password} required>
            <PasswordInput
              placeholder="Min. 6 characters"
              value={form.password}
              onChange={set('password')}
              autoComplete="new-password"
              error={errors.password}
            />
          </FormField>

          <FormField label="Confirm password" error={errors.confirm_password} required>
            <PasswordInput
              placeholder="Repeat your password"
              value={form.confirm_password}
              onChange={set('confirm_password')}
              autoComplete="new-password"
              error={errors.confirm_password}
            />
          </FormField>

          <Button type="submit">Continue →</Button>
        </form>
      )}

      {/* ── Step 2: Church & Location ─────────────────────────────────────── */}
      {step === 2 && (
        <form onSubmit={handleSubmit} className="flex flex-col gap-5" noValidate>
          <FormField label="Country" error={errors.country} required>
            <Select value={form.country} onChange={set('country')} error={errors.country}>
              <option value="">Select your country</option>
              {COUNTRIES.map(c => <option key={c.code} value={c.code}>{c.name}</option>)}
            </Select>
          </FormField>

          <FormField label="City" error={errors.city}>
            <Input
              type="text"
              placeholder="Your city"
              value={form.city}
              onChange={set('city')}
              error={errors.city}
            />
          </FormField>

          <FormField label="Zone" error={errors.region}>
            <Select value={form.region} onChange={set('region')} error={errors.region}>
              <option value="">Select region (optional)</option>
              {ZONES.map(z => <option key={z} value={z}>{z}</option>)}
            </Select>
          </FormField>

          <FormField label="Satellite Campus" error={errors.satellite_campus} required>
            <Input
              type="text"
              placeholder="Your satellite campus"
              value={form.satellite_campus}
              onChange={set('satellite_campus')}
              autoFocus
              error={errors.satellite_campus}
            />
          </FormField>

          {form.account_type === 'church' && (
            <FormField label="Church / Group Name" error={errors.church}>
              <Input
                type="text"
                placeholder="Church or group name"
                value={form.church}
                onChange={set('church')}
                error={errors.church}
              />
            </FormField>
          )}

          <div className="flex gap-3 mt-1">
            <Button type="button" variant="secondary" onClick={() => setStep(1)} className="flex-1">
              ← Back
            </Button>
            <Button type="submit" loading={loading} className="flex-1">
              {loading ? 'Creating account…' : 'Create Account'}
            </Button>
          </div>

          <p className="text-xs text-center text-[var(--color-brand-muted)] leading-relaxed">
            By creating an account you agree to our{' '}
            <a href="/about" className="text-[var(--color-brand-accent)] hover:underline">Terms of Service</a>
          </p>
        </form>
      )}
    </AuthLayout>
  )
}
