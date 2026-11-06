import { createContext, useContext, useState, useEffect, useCallback } from 'react'
import { userApi, authApi } from '@/services/api'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true) // true on first load while we check session

  // On mount: check if the PHP session is still valid
  const checkSession = useCallback(async () => {
    try {
      const res = await userApi.getProfile()
      // user.php returns the profile in `res.data`, while login returns it in `res.user`
      const user = res?.user || res?.data?.user || res?.data
      if (res.success && user) {
        setUser(user)
      } else {
        setUser(null)
      }
    } catch {
      // 401 = not logged in; any other error = treat as logged out
      setUser(null)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    checkSession()
  }, [checkSession])

  const login = useCallback(async (credentials) => {
    const res = await authApi.login(credentials)
    if (res.success) {
      // Login returns minimal user; immediately fetch the full profile
      // so we get is_admin, subscription_status, etc.
      await checkSession()
    }
    return res
  }, [checkSession])

  const logout = useCallback(async () => {
    try {
      await authApi.logout()
    } finally {
      setUser(null)
    }
  }, [])

  const value = {
    user,
    loading,
    isAuthenticated: !!user,
    isAdmin: user?.is_admin === true || user?.role === 'admin',
    isSubscribed: user?.subscription_status === 'active' || user?.isSubscribed === true,
    login,
    logout,
    refreshUser: checkSession,
  }

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used inside <AuthProvider>')
  return ctx
}
