import { NavLink } from 'react-router-dom';
import { Home, Compass, Layers, User, Play } from 'lucide-react';

export default function MobileBottomNav() {
  return (
    <nav className="lg:hidden fixed bottom-0 left-0 right-0 z-50 bg-white/95 dark:bg-gray-950/95 backdrop-blur-xl border-t border-gray-100 dark:border-gray-800 shadow-[0_-4px_25px_rgba(0,0,0,0.06)] pb-safe">
      <div className="flex items-center justify-around h-16 px-3 max-w-lg mx-auto">
        <BottomNavItem to="/dashboard" label="Home" icon={Home} />
        <BottomNavItem to="/videos" label="Explore" icon={Compass} />
        
        {/* Elevated Live Play Button in center */}
        <NavLink
          to="/live-tv"
          className="flex flex-col items-center justify-center -mt-6 group focus:outline-none"
        >
          {({ isActive }) => (
            <>
              <div className={`w-13 h-13 rounded-full bg-gradient-to-tr from-[#e11d48] to-[#f43f5e] text-white flex items-center justify-center shadow-lg shadow-rose-500/35 transition-transform group-hover:scale-105 active:scale-95 ${isActive ? 'ring-4 ring-rose-200 dark:ring-rose-900/60' : ''}`}>
                <Play className="w-6 h-6 fill-white translate-x-0.5" />
              </div>
              <span className={`text-[11px] font-semibold tracking-tight mt-1 ${isActive ? 'text-rose-600 dark:text-rose-400' : 'text-gray-600 dark:text-gray-400'}`}>
                Live
              </span>
            </>
          )}
        </NavLink>

        <BottomNavItem to="/crusades" label="Library" icon={Layers} />
        <BottomNavItem to="/profile" label="Profile" icon={User} />
      </div>
    </nav>
  );
}

function BottomNavItem({ to, label, icon: Icon }) {
  return (
    <NavLink
      to={to}
      className={({ isActive }) =>
        `flex flex-col items-center justify-center gap-1 w-14 h-full transition-all duration-200 ${
          isActive
            ? 'text-[#5b3ee4] dark:text-[#818cf8]'
            : 'text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200'
        }`
      }
    >
      {({ isActive }) => (
        <>
          <div className="relative">
            <Icon 
              className={`w-5 h-5 transition-transform ${isActive ? 'stroke-[2.5px] scale-105' : 'stroke-2'}`} 
            />
          </div>
          <span className={`text-[11px] font-medium tracking-tight ${isActive ? 'font-semibold text-[#5b3ee4] dark:text-[#818cf8]' : ''}`}>
            {label}
          </span>
        </>
      )}
    </NavLink>
  );
}
