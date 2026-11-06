import { useState, useEffect, useRef } from 'react';
import { Link, NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import { useTheme } from '@/context/ThemeContext';
import {
  Menu, X, Search, User, LogOut, 
  MonitorPlay, Compass, BookOpen, Play, Home, Radio,
  Sun, Moon
} from 'lucide-react';

export default function Navbar() {
  const { user, isAuthenticated, isAdmin, logout } = useAuth();
  const { toggleTheme, isDark } = useTheme();
  const navigate = useNavigate();
  const [scrolled, setScrolled] = useState(false);
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [profileDropdownOpen, setProfileDropdownOpen] = useState(false);
  const profileRef = useRef(null);

  // Scrolled state for navbar effect
  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 20);
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  // Close dropdown on click outside
  useEffect(() => {
    function handler(e) {
      if (profileRef.current && !profileRef.current.contains(e.target)) {
        setProfileDropdownOpen(false);
      }
    }
    if (profileDropdownOpen) document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [profileDropdownOpen]);

  const handleLogout = async () => {
    await logout();
    navigate('/');
  };

  const [searchOpen, setSearchOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    if (searchQuery.trim()) {
      navigate(`/videos?q=${encodeURIComponent(searchQuery.trim())}`);
      setSearchOpen(false);
    }
  };

  const navLinkClass = ({ isActive }) =>
    `px-3 py-1.5 text-[15px] transition-colors duration-150 ${
      isActive
        ? 'text-[#5b3ee4] dark:text-[#818cf8] font-semibold border-b-2 border-[#5b3ee4] dark:border-[#818cf8]'
        : 'text-gray-700 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white font-medium border-b-2 border-transparent'
    }`;

  const homePath = isAuthenticated ? '/dashboard' : '/';

  return (
    <>
      <header
        className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${
          scrolled
            ? 'bg-white/95 dark:bg-gray-950/95 backdrop-blur-xl border-b border-gray-100 dark:border-gray-850 shadow-[0_2px_20px_rgba(0,0,0,0.04)]'
            : 'bg-white/90 dark:bg-gray-950/90 backdrop-blur-md border-b border-gray-100/60 dark:border-gray-800/60'
        }`}
      >
        <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between h-20">
            
            {/* Logo & Primary Nav */}
            <div className="flex items-center gap-8 lg:gap-10">
              <Link to={homePath} className="flex items-center gap-3 group shrink-0" aria-label="Virtual Praise Room">
                <img 
                  src="/logo.png" 
                  alt="VPR" 
                  className="h-8 sm:h-9 w-auto transition-transform duration-200 group-hover:scale-105" 
                  onError={(e) => {
                    e.target.style.display = 'none';
                  }}
                />
                <div className="hidden sm:flex flex-col">
                  <span className="font-serif font-bold text-lg sm:text-xl text-gray-950 dark:text-white leading-tight tracking-tight">
                    Virtual Praise Room
                  </span>
                  <span className="text-[11px] text-gray-500 dark:text-gray-400 font-medium leading-none">
                    Praise Platform
                  </span>
                </div>
              </Link>

              {/* Desktop Navigation (visible for all) */}
              <nav className="hidden lg:flex items-center gap-4">
                <NavLink to={homePath} className={navLinkClass}>Home</NavLink>
                <NavLink to="/videos" className={navLinkClass}>Explore</NavLink>
                <NavLink to="/crusades" className={navLinkClass}>Library</NavLink>
                <NavLink to="/live-tv" className={navLinkClass}>Live</NavLink>
                <NavLink to="/dashboard/streaming" className={navLinkClass}>Studio</NavLink>
              </nav>
            </div>

            {/* Secondary Nav & Actions */}
            <div className="flex items-center gap-2.5 sm:gap-4">
              
              {/* Search (Desktop) */}
              <form onSubmit={handleSearchSubmit} className="hidden md:flex relative group">
                <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                  <Search className="h-4 w-4 text-gray-400 group-focus-within:text-[#5b3ee4] transition-colors" />
                </div>
                <input
                  type="text"
                  placeholder="Search teachings and services"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="w-48 lg:w-72 pl-9 pr-4 py-2 bg-gray-100 dark:bg-gray-800/80 hover:bg-gray-200/70 dark:hover:bg-gray-800 focus:bg-white dark:focus:bg-gray-900 border border-transparent focus:border-gray-200 dark:focus:border-gray-700 focus:ring-2 focus:ring-[#5b3ee4]/20 rounded-full text-sm text-gray-900 dark:text-white placeholder-gray-400 transition-all outline-none"
                />
              </form>

              {/* Search Icon (Mobile / Tablet) */}
              <button
                type="button"
                onClick={() => setSearchOpen(!searchOpen)}
                className="md:hidden w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                aria-label="Search"
              >
                <Search className="w-4 h-4 sm:w-5 sm:h-5" />
              </button>

              {/* Theme Toggle Button */}
              <button
                type="button"
                onClick={toggleTheme}
                className="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-amber-400 flex items-center justify-center transition-colors outline-none"
                aria-label={isDark ? "Switch to light mode" : "Switch to dark mode"}
                title={isDark ? "Switch to light mode" : "Switch to dark mode"}
              >
                {isDark ? <Sun className="w-4 h-4 sm:w-5 sm:h-5 text-amber-400" /> : <Moon className="w-4 h-4 sm:w-5 sm:h-5 text-gray-600" />}
              </button>

              {isAuthenticated ? (
                <div className="flex items-center gap-3">
                  <Link 
                    to="/subscribe" 
                    className="hidden sm:inline-flex items-center gap-1.5 px-4 py-2 bg-[#f59e0b] hover:bg-[#d97706] text-white text-sm font-semibold rounded-full transition-all shadow-sm active:scale-95"
                  >
                    <span>★</span> Go Premium
                  </Link>

                  {/* Profile Dropdown */}
                  <div className="relative" ref={profileRef}>
                    <button
                      onClick={() => setProfileDropdownOpen(!profileDropdownOpen)}
                      className="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-[#6344e7] hover:bg-[#5233d4] text-white font-bold text-sm flex items-center justify-center shadow-sm transition-all focus:ring-4 focus:ring-purple-100 outline-none"
                      title={user?.name || user?.username || 'Account'}
                    >
                      {(user?.name || user?.username || 'D')[0].toUpperCase()}
                    </button>

                    {profileDropdownOpen && (
                      <div className="absolute right-0 mt-3 w-64 bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl border border-gray-100 dark:border-gray-800 rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.12)] py-3 z-50 animate-in fade-in slide-in-from-top-2 duration-200">
                        <div className="px-5 py-3 border-b border-gray-100 dark:border-gray-800 mb-2">
                          <p className="font-semibold text-gray-900 dark:text-white">{user?.name || user?.username}</p>
                          <p className="text-xs text-gray-500 dark:text-gray-400 font-medium mt-0.5">{user?.email || 'User Account'}</p>
                        </div>
                        
                        <Link to="/profile" onClick={() => setProfileDropdownOpen(false)} className="flex items-center gap-3 px-5 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-black dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                          <User className="w-4 h-4 opacity-70" /> Profile & Settings
                        </Link>
                        <Link to="/subscribe" onClick={() => setProfileDropdownOpen(false)} className="sm:hidden flex items-center gap-3 px-5 py-2.5 text-sm font-medium text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/30 transition-colors">
                          <span>★</span> Go Premium
                        </Link>
                        {isAdmin && (
                          <Link to="/admin" onClick={() => setProfileDropdownOpen(false)} className="flex items-center gap-3 px-5 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-black dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            <MonitorPlay className="w-4 h-4 opacity-70" /> Admin Dashboard
                          </Link>
                        )}
                        <button
                          onClick={handleLogout}
                          className="w-full flex items-center gap-3 px-5 py-2.5 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30 transition-colors mt-2"
                        >
                          <LogOut className="w-4 h-4 opacity-70" /> Sign Out
                        </button>
                      </div>
                    )}
                  </div>
                </div>
              ) : (
                <div className="flex items-center gap-1.5 sm:gap-3">
                  <Link 
                    to="/login" 
                    className="px-3.5 sm:px-5 py-2 bg-[#0c0d24] dark:bg-white hover:bg-black dark:hover:bg-gray-100 text-white dark:text-gray-950 text-xs sm:text-sm font-semibold rounded-full transition-all shadow-sm shrink-0"
                  >
                    Sign in
                  </Link>
                </div>
              )}

              {/* Mobile Menu Toggle for secondary links */}
              <button
                className="lg:hidden p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-full transition-colors"
                onClick={() => setMobileMenuOpen(true)}
                aria-label="Toggle navigation menu"
              >
                <Menu className="w-6 h-6" />
              </button>
            </div>
          </div>

          {/* Mobile search bar expandable */}
          {searchOpen && (
            <div className="md:hidden py-3 border-t border-gray-100 dark:border-gray-800 animate-in fade-in duration-200">
              <form onSubmit={handleSearchSubmit} className="relative">
                <Search className="h-4 w-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                <input
                  type="text"
                  placeholder="Search teachings and services"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  autoFocus
                  className="w-full pl-9 pr-4 py-2.5 bg-gray-100 dark:bg-gray-800 rounded-full text-sm text-gray-900 dark:text-white placeholder-gray-400 outline-none"
                />
              </form>
            </div>
          )}
        </div>
      </header>

      {/* Mobile Menu Overlay */}
      {mobileMenuOpen && (
        <div className="fixed inset-0 z-[100] lg:hidden">
          <div className="absolute inset-0 bg-black/50 backdrop-blur-sm transition-opacity" onClick={() => setMobileMenuOpen(false)} />
          <div className="absolute right-0 top-0 bottom-0 w-[85%] max-w-sm bg-white dark:bg-gray-900 text-gray-950 dark:text-white shadow-2xl animate-in slide-in-from-right duration-300">
            <div className="flex items-center justify-between p-6 border-b border-gray-100 dark:border-gray-800">
              <span className="font-bold text-lg">Menu</span>
              <button onClick={() => setMobileMenuOpen(false)} className="p-2 bg-gray-100 dark:bg-gray-800 rounded-full text-gray-600 dark:text-gray-300">
                <X className="w-5 h-5" />
              </button>
            </div>
            
            <div className="p-4 overflow-y-auto h-full pb-32">
              {isAuthenticated ? (
                <div className="space-y-2">
                  <MobileNavLink to="/dashboard" onClick={() => setMobileMenuOpen(false)} icon={Home}>Home</MobileNavLink>
                  <MobileNavLink to="/videos" onClick={() => setMobileMenuOpen(false)} icon={Compass}>Explore</MobileNavLink>
                  <MobileNavLink to="/crusades" onClick={() => setMobileMenuOpen(false)} icon={BookOpen}>Library</MobileNavLink>
                  <MobileNavLink to="/live-tv" onClick={() => setMobileMenuOpen(false)} icon={Play}>Live TV</MobileNavLink>
                  <MobileNavLink to="/dashboard/streaming" onClick={() => setMobileMenuOpen(false)} icon={Radio}>Studio</MobileNavLink>
                  
                  <div className="h-px bg-gray-100 dark:bg-gray-800 my-4"></div>
                  
                  <MobileNavLink to="/profile" onClick={() => setMobileMenuOpen(false)} icon={User}>Profile Settings</MobileNavLink>
                  {isAdmin && <MobileNavLink to="/admin" onClick={() => setMobileMenuOpen(false)} icon={MonitorPlay}>Admin Dashboard</MobileNavLink>}
                  
                  <button
                    onClick={handleLogout}
                    className="w-full flex items-center gap-3 px-4 py-3.5 mt-4 text-left font-semibold text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/30 rounded-2xl"
                  >
                    <LogOut className="w-5 h-5" /> Sign Out
                  </button>
                </div>
              ) : (
                <div className="space-y-3 pt-4">
                  <Link to="/login" onClick={() => setMobileMenuOpen(false)} className="block w-full text-center py-3.5 font-semibold bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white rounded-2xl">Log In</Link>
                  <Link to="/register" onClick={() => setMobileMenuOpen(false)} className="block w-full text-center py-3.5 font-semibold bg-[#5b3ee4] hover:bg-[#4f2bee] text-white rounded-2xl">Create Account</Link>
                </div>
              )}
            </div>
          </div>
        </div>
      )}
    </>
  );
}

function MobileNavLink({ to, onClick, children, icon: Icon }) {
  return (
    <NavLink
      to={to}
      onClick={onClick}
      className={({ isActive }) =>
        `flex items-center gap-3 px-4 py-3.5 rounded-2xl font-semibold transition-all ${
          isActive
            ? 'bg-gray-100 dark:bg-gray-800 text-[#5b3ee4] dark:text-[#818cf8]'
            : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800'
        }`
      }
    >
      {Icon && <Icon className="w-5 h-5 opacity-70" />}
      {children}
    </NavLink>
  );
}
