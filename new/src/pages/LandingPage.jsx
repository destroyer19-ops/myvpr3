import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Play, Tv, Users, Heart, ShieldCheck, Video, Gift, ChevronRight } from 'lucide-react';
import { streamsApi, videosApi } from '../services/api';

export default function LandingPage() {
  const [heroStream, setHeroStream] = useState(null);
  const [latestVideos, setLatestVideos] = useState([]);
  const [videosLoading, setVideosLoading] = useState(true);

  useEffect(() => {
    // Fetch live stream for hero
    streamsApi.getLive().then(res => {
      if (res?.success && res.streams?.length > 0) {
        setHeroStream(res.streams[0]);
      }
    }).catch(() => {});

    // Fetch latest videos
    videosApi.list().then(res => {
      if (res?.success && Array.isArray(res.data)) {
        setLatestVideos(res.data.slice(0, 10));
      }
    }).catch(() => {}).finally(() => setVideosLoading(false));
  }, []);

  const defaultTitles = [
    { 
      tag: 'Ministration', 
      title: 'Day of Bliss',
      subtitle: 'A Day of Bliss with Pastor Chris (Samoa)', 
      bgClass: 'bg-[#5B3EE4]',
      arcClass: 'bg-[#7C5CF7]',
      img: 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=600&q=80' 
    },
    { 
      tag: 'Service', 
      title: 'Healing Streams', 
      subtitle: 'Healing Streams Live Healing Services',
      bgClass: 'bg-[#5B3EE4]',
      arcClass: 'bg-[#7C5CF7]'
    },
    { 
      tag: 'Teaching', 
      title: 'Sunday Service', 
      subtitle: 'Sunday Service highlights',
      bgClass: 'bg-[#F59E0B]',
      arcClass: 'bg-[#FBBF24]'
    },
    { 
      tag: 'Children', 
      title: "Kids' Praise", 
      subtitle: "Children's worship and Bible stories",
      bgClass: 'bg-[#0D9488]',
      arcClass: 'bg-[#14B8A6]'
    },
    { 
      tag: 'Music', 
      title: 'Praise Night', 
      subtitle: 'Praise and worship medley',
      bgClass: 'bg-[#E11D48]',
      arcClass: 'bg-[#F43F5E]'
    }
  ];

  return (
    <div className="flex flex-col min-h-screen bg-[#fcfcfd] dark:bg-gray-950">

      {/* ── 1. Hero Section ────────────────────────────────────────── */}
      <section className="pt-6 pb-12 sm:pt-10 sm:pb-16 lg:pt-14 lg:pb-20">
        <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid lg:grid-cols-12 gap-8 lg:gap-12 items-center">

            {/* Left Column (Headline & Actions) */}
            <div className="lg:col-span-6 xl:col-span-6 space-y-5 sm:space-y-6">
              
              {/* Live Tag Pill */}
              <Link 
                to="/live-tv" 
                className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-[#fdf0f4] dark:bg-rose-950/40 text-[#e11d48] dark:text-rose-400 text-xs font-semibold hover:bg-rose-100 dark:hover:bg-rose-950/60 transition-colors"
              >
                <span className="w-2 h-2 rounded-full bg-[#e11d48] animate-pulse" />
                <span>Live now: {heroStream?.title || 'Healing Streams Live Healing Services'}</span>
              </Link>

              {/* Main Headline */}
              <h1 className="font-serif text-3xl sm:text-5xl xl:text-6xl font-bold tracking-tight text-gray-950 dark:text-white leading-[1.08]">
                Worship with believers everywhere, day and night.
              </h1>

              {/* Subheading */}
              <p className="text-gray-600 dark:text-gray-300 text-base sm:text-lg leading-relaxed max-w-xl">
                Join live services around the clock, watch thousands of teachings on demand, and bring your whole church together in a digital crusade.
              </p>

              {/* Buttons */}
              <div className="flex flex-wrap items-center gap-3 pt-2">
                <Link
                  to="/live-tv"
                  className="inline-flex items-center justify-center gap-2 px-7 py-3.5 bg-[#5b3ee4] hover:bg-[#4f2bee] text-white font-semibold rounded-full transition-all text-sm sm:text-base shadow-lg shadow-indigo-500/25 active:scale-95"
                >
                  <Play className="w-4 h-4 fill-white" /> Watch live
                </Link>
                <Link
                  to="/videos"
                  className="inline-flex items-center justify-center px-7 py-3.5 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 text-gray-800 dark:text-gray-200 font-semibold rounded-full border border-gray-300 dark:border-gray-700 transition-all text-sm sm:text-base shadow-2xs active:scale-95"
                >
                  Browse teachings
                </Link>
              </div>

              {/* Social Proof Avatars */}
              <div className="flex items-center gap-3 pt-3">
                <div className="flex -space-x-2">
                  <div className="w-7 h-7 rounded-full bg-amber-400 border-2 border-white dark:border-gray-950" />
                  <div className="w-7 h-7 rounded-full bg-teal-500 border-2 border-white dark:border-gray-950" />
                  <div className="w-7 h-7 rounded-full bg-pink-500 border-2 border-white dark:border-gray-950" />
                  <div className="w-7 h-7 rounded-full bg-indigo-500 border-2 border-white dark:border-gray-950" />
                </div>
                <span className="text-xs text-gray-500 dark:text-gray-400 font-medium">
                  Praising together with believers across the globe
                </span>
              </div>
            </div>

            {/* Right Column (Hero Video Card) */}
            <div className="lg:col-span-6 xl:col-span-6">
              <div className="relative rounded-3xl overflow-hidden bg-gray-950 shadow-2xl ring-4 ring-[#3b82f6]/30 aspect-[16/10] group">
                <img
                  src={heroStream?.thumbnail_url || "https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=1200&q=80"}
                  alt="Live Service"
                  className="w-full h-full object-cover opacity-85 group-hover:scale-105 transition-transform duration-700"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-gray-950/95 via-gray-950/25 to-black/30" />

                {/* Top Live Badge */}
                <div className="absolute top-4 left-4 z-10">
                  <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#e11d48] text-white text-xs font-bold shadow-md">
                    <span className="w-2 h-2 rounded-full bg-white animate-pulse" /> Live
                  </span>
                </div>

                {/* Center Big Circular Play Button */}
                <Link
                  to="/live-tv"
                  className="absolute inset-0 flex items-center justify-center z-10"
                >
                  <div className="w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-white text-[#5b3ee4] flex items-center justify-center shadow-2xl hover:scale-110 active:scale-95 transition-transform">
                    <Play className="w-7 h-7 sm:w-9 sm:h-9 fill-[#5b3ee4] translate-x-1" />
                  </div>
                </Link>

                {/* Bottom Card Bar */}
                <div className="absolute bottom-4 left-5 right-5 z-10 flex items-end justify-between">
                  <div>
                    <h3 className="font-serif font-bold text-white text-lg sm:text-2xl drop-shadow-sm">
                      {heroStream?.title || 'Healing Streams Live Healing Services'}
                    </h3>
                    <p className="text-gray-300 text-xs sm:text-sm mt-0.5 font-medium">
                      With Pastor Chris
                    </p>
                  </div>
                  {/* Decorative sound wave bars */}
                  <div className="hidden sm:flex items-end gap-1 h-5">
                    <span className="w-1 bg-white/70 h-3 rounded-full animate-pulse" />
                    <span className="w-1 bg-white/90 h-5 rounded-full animate-pulse [animation-delay:150ms]" />
                    <span className="w-1 bg-white/70 h-2 rounded-full animate-pulse [animation-delay:300ms]" />
                    <span className="w-1 bg-white/80 h-4 rounded-full animate-pulse [animation-delay:450ms]" />
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </section>

      {/* ── 2. Built for praise, not just streaming ───────────────── */}
      <section className="py-14 sm:py-20 bg-white dark:bg-gray-900 border-t border-gray-100 dark:border-gray-800">
        <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
          
          <div className="max-w-2xl mb-10 sm:mb-12">
            <h2 className="font-serif text-3xl sm:text-4xl font-bold text-gray-950 dark:text-white tracking-tight mb-2">
              Built for praise, not just streaming
            </h2>
            <p className="text-gray-600 dark:text-gray-300 text-base">
              A space made to grow your faith and bring believers together, wherever they are.
            </p>
          </div>

          <div className="grid lg:grid-cols-12 gap-6 items-stretch">
            {/* Left large Purple Card */}
            <div className="lg:col-span-7 bg-[#5b3ee4] rounded-3xl p-8 sm:p-10 text-white flex flex-col justify-between min-h-[320px] relative overflow-hidden shadow-sm">
              <div className="w-12 h-12 rounded-2xl bg-white/15 flex items-center justify-center">
                <Users className="w-6 h-6 text-white" />
              </div>

              <div className="relative z-10 pt-16">
                <h3 className="font-serif text-2xl sm:text-3xl font-bold mb-2">
                  Worship together, wherever you are
                </h3>
                <p className="text-indigo-100 text-sm sm:text-base max-w-md leading-relaxed">
                  Pray, sing and fellowship with believers from every nation in the same live service.
                </p>
              </div>

              {/* Decorative colorful dots (matching Landing page.png) */}
              <div className="absolute bottom-6 right-6 grid grid-cols-3 gap-2 opacity-90">
                <div className="w-6 h-6 sm:w-8 sm:h-8 rounded-full bg-amber-400" />
                <div className="w-6 h-6 sm:w-8 sm:h-8 rounded-full bg-pink-500" />
                <div className="w-6 h-6 sm:w-8 sm:h-8 rounded-full bg-purple-300" />
                <div className="w-6 h-6 sm:w-8 sm:h-8 rounded-full bg-indigo-200" />
                <div className="w-6 h-6 sm:w-8 sm:h-8 rounded-full bg-teal-400" />
                <div className="w-6 h-6 sm:w-8 sm:h-8 rounded-full bg-orange-400" />
              </div>
            </div>

            {/* Right stacked Cards */}
            <div className="lg:col-span-5 flex flex-col gap-6">
              <div className="bg-white dark:bg-gray-850 rounded-3xl p-6 sm:p-8 border border-gray-100 dark:border-gray-800 shadow-sm flex items-start gap-5 flex-1">
                <div className="w-11 h-11 rounded-2xl bg-[#fdf0f4] dark:bg-pink-950/50 text-[#e11d48] flex items-center justify-center shrink-0">
                  <Heart className="w-5 h-5" />
                </div>
                <div>
                  <h4 className="font-serif font-bold text-xl text-gray-950 dark:text-white mb-1">
                    A room made for praise
                  </h4>
                  <p className="text-gray-500 dark:text-gray-400 text-sm leading-relaxed">
                    Lift your hands with live reactions, prayer requests and sing-along lyrics during every service.
                  </p>
                </div>
              </div>

              <div className="bg-white dark:bg-gray-850 rounded-3xl p-6 sm:p-8 border border-gray-100 dark:border-gray-800 shadow-sm flex items-start gap-5 flex-1">
                <div className="w-11 h-11 rounded-2xl bg-[#eefbf7] dark:bg-teal-950/50 text-teal-600 dark:text-teal-400 flex items-center justify-center shrink-0">
                  <ShieldCheck className="w-5 h-5" />
                </div>
                <div>
                  <h4 className="font-serif font-bold text-xl text-gray-950 dark:text-white mb-1">
                    Private and secure
                  </h4>
                  <p className="text-gray-500 dark:text-gray-400 text-sm leading-relaxed">
                    Your gatherings, prayer requests and account stay protected with encryption.
                  </p>
                </div>
              </div>
            </div>
          </div>

        </div>
      </section>

      {/* ── 3. Live worship, teachings and crusades in one place ──── */}
      <section className="py-14 sm:py-20 bg-[#fbfbfe] dark:bg-gray-950 border-t border-gray-100 dark:border-gray-800">
        <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
          
          <div className="mb-10 sm:mb-12">
            <h2 className="font-serif text-3xl sm:text-4xl font-bold text-gray-950 dark:text-white tracking-tight">
              Live worship, teachings and crusades in one place
            </h2>
          </div>

          <div className="grid lg:grid-cols-12 gap-6 items-stretch">
            {/* Left Big Live TV Card */}
            <div className="lg:col-span-6 rounded-3xl overflow-hidden relative min-h-[340px] sm:min-h-[380px] p-8 flex flex-col justify-between text-white bg-gray-950 shadow-md group">
              <img
                src="https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=1000&q=80"
                alt="Live TV"
                className="absolute inset-0 w-full h-full object-cover opacity-60 group-hover:scale-105 transition-transform duration-700"
              />
              <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent" />

              <div className="relative z-10">
                <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#e11d48] text-white text-xs font-bold shadow-md">
                  <span className="w-1.5 h-1.5 rounded-full bg-white animate-pulse" /> On air 24 hours
                </span>
              </div>

              <div className="relative z-10">
                <h3 className="font-serif font-bold text-3xl sm:text-4xl text-white mb-2">
                  Live TV
                </h3>
                <p className="text-gray-200 text-sm sm:text-base max-w-sm mb-4 leading-relaxed">
                  Services, worship and prayer streaming around the clock. Tune in any time of day.
                </p>
                <Link
                  to="/live-tv"
                  className="inline-flex items-center gap-1 text-white font-semibold text-sm hover:underline"
                >
                  Open Live TV <ChevronRight className="w-4 h-4" />
                </Link>
              </div>
            </div>

            {/* Right Stack of 3 Cards */}
            <div className="lg:col-span-6 flex flex-col gap-6">
              
              {/* Video Library Card (mint) */}
              <Link
                to="/videos"
                className="bg-[#eefbf7] dark:bg-[#102622] hover:bg-[#e4f7f2] dark:hover:bg-[#163630] rounded-3xl p-6 sm:p-8 border border-teal-100/60 dark:border-teal-900/40 flex items-center gap-5 transition-all shadow-2xs group"
              >
                <div className="w-14 h-14 rounded-2xl bg-teal-600 text-white flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                  <Play className="w-6 h-6 fill-white translate-x-0.5" />
                </div>
                <div>
                  <h4 className="font-serif font-bold text-xl text-teal-950 dark:text-teal-100 mb-1">
                    Video library
                  </h4>
                  <p className="text-teal-800/80 dark:text-teal-300 text-sm">
                    Thousands of teachings and messages, ready when you are.
                  </p>
                </div>
              </Link>

              {/* Bottom 2 Cards Grid */}
              <div className="grid sm:grid-cols-2 gap-6 flex-1">
                {/* Digital Crusades Card (amber) */}
                <Link
                  to="/crusades"
                  className="bg-[#fef3c7]/60 dark:bg-[#2c1e10] hover:bg-[#fef3c7] dark:hover:bg-[#3d2a17] rounded-3xl p-6 sm:p-8 border border-amber-100/60 dark:border-amber-900/40 flex flex-col justify-between transition-all shadow-2xs group"
                >
                  <div className="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-400 flex items-center justify-center mb-6">
                    <Video className="w-5 h-5" />
                  </div>
                  <div>
                    <h4 className="font-serif font-bold text-lg text-amber-950 dark:text-amber-100 mb-1">
                      Digital crusades
                    </h4>
                    <p className="text-amber-800/80 dark:text-amber-300 text-xs">
                      Host or join a virtual crusade.
                    </p>
                  </div>
                </Link>

                {/* Give Card (rose) */}
                <Link
                  to="/give"
                  className="bg-[#fdf2f8] dark:bg-[#2a1726] hover:bg-[#fce7f3] dark:hover:bg-[#381f33] rounded-3xl p-6 sm:p-8 border border-pink-100/60 dark:border-pink-900/40 flex flex-col justify-between transition-all shadow-2xs group"
                >
                  <div className="w-10 h-10 rounded-xl bg-pink-100 dark:bg-pink-900/60 text-[#e11d48] flex items-center justify-center mb-6">
                    <Gift className="w-5 h-5" />
                  </div>
                  <div>
                    <h4 className="font-serif font-bold text-lg text-pink-950 dark:text-pink-100 mb-1">
                      Give
                    </h4>
                    <p className="text-pink-800/80 dark:text-pink-300 text-xs">
                      Partner with the ministry and support the work.
                    </p>
                  </div>
                </Link>
              </div>

            </div>
          </div>

        </div>
      </section>

      {/* ── 4. New in the library ─────────────────────────────────── */}
      <section className="py-14 sm:py-20 bg-white dark:bg-gray-900 border-t border-gray-100 dark:border-gray-800">
        <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
          
          <div className="flex items-end justify-between mb-8">
            <div>
              <h2 className="font-serif text-2xl sm:text-3xl font-bold text-gray-950 dark:text-white">
                New in the library
              </h2>
              <p className="text-gray-500 dark:text-gray-400 text-sm mt-1">
                Recent teachings and messages
              </p>
            </div>
            <Link
              to="/videos"
              className="text-[#5b3ee4] dark:text-[#818cf8] text-sm font-semibold hover:underline"
            >
              See the whole library
            </Link>
          </div>

          {/* 5 Cards Row */}
          <div className="content-rail sm:grid sm:grid-cols-3 lg:grid-cols-5 gap-4 sm:gap-5 hide-scrollbar">
            {defaultTitles.map((item, idx) => (
              <Link
                key={idx}
                to="/videos"
                className="content-rail-item w-[220px] sm:w-auto group flex flex-col shrink-0"
              >
                <div className={`relative aspect-[3/4] rounded-2xl overflow-hidden ${item.bgClass} p-4 flex flex-col justify-between text-white mb-2.5 shadow-2xs group-hover:shadow-md transition-all`}>
                  {item.img && (
                    <img
                      src={item.img}
                      alt={item.title}
                      className="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-300 opacity-80"
                    />
                  )}
                  {item.arcClass && !item.img && (
                    <div className={`absolute -right-8 -bottom-8 w-40 h-40 rounded-full ${item.arcClass} opacity-80`} />
                  )}
                  <div className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/20 to-transparent" />

                  <span className="relative z-10 self-start text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-white text-gray-900 shadow-xs">
                    {item.tag}
                  </span>
                  
                  <span className="relative z-10 font-serif font-bold text-lg sm:text-xl leading-tight text-white drop-shadow-sm">
                    {item.title}
                  </span>
                </div>
                
                <p className="font-bold text-sm text-gray-950 dark:text-gray-100 line-clamp-2 leading-snug group-hover:text-[#5b3ee4] dark:group-hover:text-[#818cf8] transition-colors">
                  {item.subtitle}
                </p>
                <p className="text-xs text-gray-400 dark:text-gray-500 mt-0.5">English</p>
              </Link>
            ))}
          </div>

        </div>
      </section>

      {/* ── 5. Bring your church into the room (CTA Banner) ────────── */}
      <section className="py-12 sm:py-16">
        <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
          <div className="bg-[#fef3c7] dark:bg-amber-950/40 rounded-3xl p-8 sm:p-12 border border-amber-200/60 dark:border-amber-900/40 flex flex-col md:flex-row items-center justify-between gap-8">
            <div className="max-w-xl">
              <h3 className="font-serif font-bold text-2xl sm:text-3xl text-amber-950 dark:text-amber-100 mb-2">
                Bring your church into the room
              </h3>
              <p className="text-amber-900/80 dark:text-amber-300 text-sm sm:text-base leading-relaxed">
                Go live from Studio, invite your members and host a digital crusade they can join from any device.
              </p>
            </div>
            <div className="flex flex-wrap items-center gap-3 shrink-0">
              <Link
                to="/register"
                className="px-6 py-3 bg-[#0c0d24] dark:bg-white hover:bg-black dark:hover:bg-gray-100 text-white dark:text-gray-950 text-sm font-semibold rounded-full transition-all shadow-sm active:scale-95"
              >
                Create free account
              </Link>
              <Link
                to="/crusades"
                className="px-6 py-3 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 text-gray-900 dark:text-white border border-amber-200 dark:border-gray-700 text-sm font-semibold rounded-full transition-all shadow-2xs active:scale-95"
              >
                Start a crusade
              </Link>
            </div>
          </div>
        </div>
      </section>

      {/* ── 6. Footer ──────────────────────────────────────────────── */}
      <footer className="bg-[#0c0d24] text-white py-10 border-t border-gray-900 mt-auto">
        <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center justify-between gap-6">
          <div className="flex items-center gap-3">
            <img 
              src="/logo.png" 
              alt="VPR" 
              className="h-8 w-auto brightness-200" 
              onError={(e) => { e.target.style.display = 'none'; }}
            />
            <span className="font-serif font-bold text-lg text-white">
              Virtual Praise Room
            </span>
          </div>

          <div className="flex flex-wrap items-center justify-center gap-6 text-sm text-gray-400 font-medium">
            <Link to="/live-tv" className="hover:text-white transition-colors">Live TV</Link>
            <Link to="/videos" className="hover:text-white transition-colors">Library</Link>
            <Link to="/dashboard/streaming" className="hover:text-white transition-colors">Studio</Link>
            <Link to="/give" className="hover:text-white transition-colors">Give</Link>
            <Link to="/contact" className="hover:text-white transition-colors">Help</Link>
            <Link to="/about" className="hover:text-white transition-colors">Privacy</Link>
          </div>
        </div>
      </footer>

    </div>
  );
}
