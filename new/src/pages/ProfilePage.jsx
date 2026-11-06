import { useState, useEffect } from 'react';
import { useAuth } from '@/context/AuthContext';
import { userApi } from '@/services/api';
import { FormField, Input, Button, Alert } from '@/components/ui/FormElements';
import { User, Shield, Activity, Crown, Settings, LogOut, Heart, Bookmark, Check } from 'lucide-react';
import { Link } from 'react-router-dom';

export default function ProfilePage() {
  const { user, refreshUser, logout } = useAuth();
  const [username, setUsername] = useState(user?.username || '');
  const [loading, setLoading] = useState(false);
  const [status, setStatus] = useState({ type: '', message: '' });

  useEffect(() => {
    if (user?.username) {
      setUsername(user.username);
    }
  }, [user]);

  const handleUpdate = async (e) => {
    e.preventDefault();
    setStatus({ type: '', message: '' });
    
    if (username === user?.username) return;
    
    if (!username.trim() || !/^[a-zA-Z0-9_]+$/.test(username)) {
      setStatus({ type: 'error', message: 'Username can only contain letters, numbers, and underscores.' });
      return;
    }

    setLoading(true);
    try {
      const res = await userApi.updateUsername({ username: username.trim() });
      if (res.success) {
        setStatus({ type: 'success', message: 'Username updated successfully!' });
        await refreshUser();
      } else {
        setStatus({ type: 'error', message: res.message || 'Failed to update username.' });
      }
    } catch (err) {
      setStatus({ type: 'error', message: 'Unable to connect. Please try again.' });
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-8 pb-28 lg:pb-16 animate-in fade-in duration-300">
      
      {/* Header Profile Section */}
      <div className="bg-white dark:bg-gray-900 rounded-3xl p-6 sm:p-10 border border-gray-100 dark:border-gray-800 shadow-sm flex flex-col md:flex-row items-center md:items-start gap-6 sm:gap-8 mb-8 sm:mb-12">
        <div className="w-24 h-24 sm:w-32 sm:h-32 rounded-full bg-gradient-to-tr from-[#6344e7] to-[#432bb3] text-white flex items-center justify-center font-bold text-3xl sm:text-4xl shadow-lg shrink-0 relative">
          {(user?.name || user?.username || 'U')[0].toUpperCase()}
          {user?.isSubscribed && (
            <div className="absolute -bottom-1 bg-[#f59e0b] text-white px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider shadow-xs">
              ★ Premium
            </div>
          )}
        </div>
        
        <div className="text-center md:text-left flex-1">
          <h1 className="font-serif text-3xl sm:text-4xl lg:text-5xl font-bold text-gray-950 dark:text-white mb-1 tracking-tight">
            {user?.name || 'User Account'}
          </h1>
          <p className="text-sm sm:text-base text-gray-500 dark:text-gray-400 mb-6 font-medium">
            @{user?.username || 'username'}
          </p>

          <div className="flex flex-wrap items-center justify-center md:justify-start gap-3">
            <button 
              onClick={logout}
              className="px-5 py-2.5 rounded-full border border-gray-200 dark:border-gray-700 font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors flex items-center gap-2 text-sm cursor-pointer"
            >
              <LogOut className="w-4 h-4" />
              Sign Out
            </button>
            {user?.is_admin && (
              <Link 
                to="/admin" 
                className="px-5 py-2.5 rounded-full bg-[#5b3ee4] hover:bg-[#4f2bee] text-white font-semibold transition-colors flex items-center gap-2 text-sm shadow-xs"
              >
                <Shield className="w-4 h-4" />
                Admin Panel
              </Link>
            )}
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 sm:gap-8 items-start">
        
        {/* Main Settings Panel */}
        <div className="lg:col-span-2 space-y-8">
          
          <section className="bg-white dark:bg-gray-900 rounded-3xl p-6 sm:p-8 border border-gray-100 dark:border-gray-800 shadow-sm">
            <h2 className="font-serif text-2xl font-bold text-gray-950 dark:text-white mb-5 flex items-center gap-2.5">
              <Settings className="w-5 h-5 text-gray-400" /> 
              Account Settings
            </h2>
            
            {status.message && (
              <Alert type={status.type} className="mb-5">
                {status.message}
              </Alert>
            )}

            <form onSubmit={handleUpdate} className="max-w-md space-y-4">
              <div>
                <label className="block text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-2">Username</label>
                <input
                  type="text"
                  value={username}
                  onChange={(e) => setUsername(e.target.value)}
                  placeholder="Your unique username"
                  className="w-full px-4 py-3 rounded-2xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-950 dark:text-white text-sm outline-none focus:ring-2 focus:ring-[#5b3ee4]/20 transition-all"
                />
                <p className="text-xs text-gray-400 mt-1.5">
                  Used for your public stream link, chat reactions, and event invitations.
                </p>
              </div>

              <button 
                type="submit" 
                disabled={username === user?.username || loading}
                className="bg-[#5b3ee4] hover:bg-[#4f2bee] text-white px-7 py-3 rounded-full font-semibold text-sm transition-all shadow-md active:scale-95 disabled:opacity-50 cursor-pointer"
              >
                {loading ? 'Saving...' : 'Save Profile'}
              </button>
            </form>
          </section>

          <section className="bg-white dark:bg-gray-900 rounded-3xl p-6 sm:p-8 border border-gray-100 dark:border-gray-800 shadow-sm">
            <h2 className="font-serif text-2xl font-bold text-gray-950 dark:text-white mb-5 flex items-center gap-2.5">
              <Bookmark className="w-5 h-5 text-gray-400" /> 
              Saved Library
            </h2>
            <div className="text-center py-12 bg-gray-50 dark:bg-gray-850 rounded-2xl border border-dashed border-gray-200 dark:border-gray-750">
              <Heart className="w-10 h-10 mx-auto text-gray-300 dark:text-gray-600 mb-3" />
              <h3 className="font-serif font-bold text-lg text-gray-950 dark:text-white mb-1">Nothing saved yet</h3>
              <p className="text-xs text-gray-500 dark:text-gray-400">Content you bookmark or save will appear in this shelf.</p>
            </div>
          </section>

        </div>

        {/* Sidebar Info */}
        <div className="space-y-6">
          
          <div className="bg-[#fef3c7]/60 dark:bg-amber-950/20 rounded-3xl p-6 sm:p-8 border border-amber-200/60 dark:border-amber-900/40 shadow-sm">
            <h3 className="text-xs font-bold text-amber-900 dark:text-amber-200 uppercase tracking-wider mb-5">Subscription Status</h3>
            
            <div className="flex items-center gap-4 mb-6">
              <div className={`w-12 h-12 rounded-2xl flex items-center justify-center ${
                user?.isSubscribed 
                  ? 'bg-amber-100 dark:bg-amber-900/60 text-[#f59e0b]' 
                  : 'bg-white dark:bg-gray-800 text-gray-400'
              }`}>
                <Crown className="w-6 h-6" />
              </div>
              <div>
                <p className="font-serif font-bold text-gray-950 dark:text-white text-xl">
                  {user?.isSubscribed ? 'Premium Member' : 'Free Account'}
                </p>
                <p className="text-xs text-gray-500 dark:text-gray-400">
                  {user?.isSubscribed ? 'Full access enabled' : 'Limited resolution & archives'}
                </p>
              </div>
            </div>

            {!user?.isSubscribed && (
              <Link 
                to="/subscribe" 
                className="block text-center w-full bg-[#f59e0b] hover:bg-[#d97706] text-white font-semibold py-3 rounded-full text-sm transition-all shadow-sm active:scale-95"
              >
                ★ Upgrade to Premium
              </Link>
            )}
          </div>

          <div className="bg-white dark:bg-gray-900 rounded-3xl p-6 sm:p-8 border border-gray-100 dark:border-gray-800 shadow-sm">
            <h3 className="text-xs font-bold text-gray-400 uppercase tracking-wider mb-5">Activity</h3>
            <div className="flex items-center gap-4">
              <div className="w-12 h-12 rounded-2xl bg-teal-50 dark:bg-teal-950/60 text-teal-600 dark:text-teal-400 flex items-center justify-center">
                <Activity className="w-6 h-6" />
              </div>
              <div>
                <p className="font-serif font-bold text-gray-950 dark:text-white text-3xl">{user?.totalViews || 0}</p>
                <p className="text-xs text-gray-400">Total services attended</p>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  );
}
