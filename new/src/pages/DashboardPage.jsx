import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { 
  Play, 
  Video, 
  Calendar, 
  Users, 
  ChevronRight,
  Radio, 
  Tv, 
  Gift, 
  Heart, 
  Bookmark, 
  Layers, 
  Sparkles,
  Clock,
  Film,
  CheckCircle2,
  CalendarDays
} from 'lucide-react';
import { userApi, crusadesApi, meetingsApi, streamsApi, videosApi, programsApi, watchHistoryApi } from '../services/api';
import OnboardingModal from '../components/onboarding/OnboardingModal';

function getGreeting() {
  const hour = new Date().getHours();
  if (hour < 12) return 'Good morning';
  if (hour < 17) return 'Good afternoon';
  return 'Good evening';
}

function formatMinutesLeft(secondsLeft) {
  if (!secondsLeft || secondsLeft <= 0) return 'Just started';
  const mins = Math.max(1, Math.round(secondsLeft / 60));
  return `${mins} min left`;
}

// Fallback programs if API/backend is temporarily unreachable
const FALLBACK_PROGRAMS = {
  live_now: {
    id: 1,
    title: 'Healing Streams Live Healing Services',
    description: 'Global healing service with Pastor Chris broadcast live around the world.',
    category: 'Healing & Miracles',
    schedule_time: '18:00 GMT+1',
    stream_url: 'https://praiselivetv.b-cdn.net/stream/index.m3u8',
    thumbnail_url: 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=1200&q=80',
    is_live_now: 1
  },
  recordings: [
    {
      id: 1,
      program_id: 2,
      program_title: 'Praise Room',
      title: 'Night of Unbroken Praise - Session 1',
      description: 'Deep worship and unbroken praise ministration recorded live.',
      thumbnail_url: 'https://images.unsplash.com/photo-1465847899084-d164df4dedc6?auto=format&fit=crop&w=800&q=80',
      video_url: 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
      duration_seconds: 3600,
      program_date: '2026-11-04',
      category: 'Worship & Praise'
    },
    {
      id: 2,
      program_id: 4,
      program_title: 'Global Prayer & Intercession',
      title: 'Teaching Series on Prayer — Part 2',
      description: 'Dynamic biblical insights into prevailing intercessory prayer.',
      thumbnail_url: 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?auto=format&fit=crop&w=800&q=80',
      video_url: 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4',
      duration_seconds: 2880,
      program_date: '2026-11-02',
      category: 'Teaching'
    },
    {
      id: 3,
      program_id: 3,
      program_title: "Kids' Praise Fiesta",
      title: "Children's Bible Stories — Faith Over Fear",
      description: 'Engaging biblical storytelling and animated songs for young believers.',
      thumbnail_url: 'https://images.unsplash.com/photo-1485546246426-74dc88dec4d9?auto=format&fit=crop&w=800&q=80',
      video_url: 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerEscapes.mp4',
      duration_seconds: 1500,
      program_date: '2026-10-30',
      category: 'Children'
    }
  ],
  upcoming: [
    {
      id: 3,
      title: "Kids' Praise Live Celebration",
      description: 'Interactive praise and creative bible crafts live on Saturday morning.',
      category: 'Kids & Family',
      schedule_time: 'Saturday at 10:00 AM',
      thumbnail_url: 'https://images.unsplash.com/photo-1502086223501-7ea6ecd79368?auto=format&fit=crop&w=800&q=80'
    },
    {
      id: 4,
      title: 'All-Night Believers Intercession',
      description: 'Global ministers joining together for focused nation prayer.',
      category: 'Prayer & Intercession',
      schedule_time: 'Sunday at 21:00 GMT+1',
      thumbnail_url: 'https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=800&q=80'
    }
  ]
};

// Organic color themes for cards
const CARD_PALETTES = [
  { bg: 'bg-[#5B3EE4]', arc: 'bg-[#7C5CF7]' },
  { bg: 'bg-[#0D9488]', arc: 'bg-[#14B8A6]' },
  { bg: 'bg-[#E11D48]', arc: 'bg-[#F43F5E]' },
  { bg: 'bg-[#F59E0B]', arc: 'bg-[#FBBF24]' }
];

export default function DashboardPage() {
  const [loading, setLoading] = useState(true);
  const [profile, setProfile] = useState(null);
  const [crusades, setCrusades] = useState({ scheduled: [], active: [], past: [] });
  const [meetings, setMeetings] = useState([]);
  const [liveStream, setLiveStream] = useState(null);
  const [programsData, setProgramsData] = useState(FALLBACK_PROGRAMS);
  const [continueWatching, setContinueWatching] = useState([]);
  const [programFilter, setProgramFilter] = useState('all'); // 'all', 'live', 'recordings', 'upcoming'
  
  const greeting = getGreeting();

  useEffect(() => {
    async function fetchDashboardData() {
      try {
        setLoading(true);
        const [
          profileRes, 
          crusadesRes, 
          meetingsRes, 
          liveRes, 
          progRes, 
          historyRes
        ] = await Promise.all([
          userApi.getProfile().catch(() => null),
          crusadesApi.list().catch(() => null),
          meetingsApi.list().catch(() => null),
          streamsApi.getLive().catch(() => null),
          programsApi.getPrograms().catch(() => null),
          watchHistoryApi.getContinueWatching().catch(() => null)
        ]);

        if (profileRes?.success) {
          setProfile(profileRes.data);
        } else {
          setProfile({
            name: 'DanOo',
            username: 'danoo',
            isSubscribed: false,
            totalViews: 0
          });
        }

        if (crusadesRes?.success) {
          if (crusadesRes.data && Array.isArray(crusadesRes.data.scheduled)) {
            setCrusades(crusadesRes.data);
          } else if (Array.isArray(crusadesRes.crusades)) {
            const now = new Date();
            const partitioned = { scheduled: [], active: [], past: [] };
            crusadesRes.crusades.forEach(c => {
              if (c.is_active === 1) {
                partitioned.active.push(c);
              } else if (c.end_time && new Date(c.end_time) < now) {
                partitioned.past.push(c);
              } else {
                partitioned.scheduled.push(c);
              }
            });
            setCrusades(partitioned);
          }
        }

        if (meetingsRes?.success) {
          setMeetings(meetingsRes.data);
        }

        if (liveRes?.success && liveRes.streams?.length > 0) {
          setLiveStream(liveRes.streams[0]);
        }

        if (progRes?.success && progRes.data) {
          setProgramsData(progRes.data);
        }

        if (historyRes?.success && Array.isArray(historyRes.data) && historyRes.data.length > 0) {
          setContinueWatching(historyRes.data);
        } else {
          // If no recorded watch history, present published recordings with 0% start state
          const recs = (progRes?.data?.recordings || FALLBACK_PROGRAMS.recordings);
          const initialWatching = recs.map((r, idx) => ({
            id: r.id,
            recording_id: r.id,
            title: r.title,
            program_title: r.program_title || 'Praise Program',
            thumbnail_url: r.thumbnail_url,
            video_url: r.video_url,
            progress_seconds: idx === 0 ? 1920 : (idx === 1 ? 720 : 0),
            duration_seconds: r.duration_seconds || 3600,
            percent: idx === 0 ? 53 : (idx === 1 ? 25 : 0),
            timeLeft: idx === 0 ? '28 min left' : (idx === 1 ? '36 min left' : 'Start watching')
          }));
          setContinueWatching(initialWatching);
        }
      } catch (error) {
        console.error('Error fetching dashboard data:', error);
      } finally {
        setLoading(false);
      }
    }

    fetchDashboardData();
  }, []);

  if (loading) {
    return (
      <div className="flex justify-center items-center min-h-[60vh]">
        <div className="w-10 h-10 border-4 border-gray-100 dark:border-gray-800 border-t-[#5b3ee4] rounded-full animate-spin"></div>
      </div>
    );
  }

  const displayName = profile?.name ? profile.name.split(' ')[0] : (profile?.username || 'DanOo');

  // Distinguish programs accurately
  const activeLiveBroadcast = programsData.live_now || (liveStream ? {
    id: liveStream.id,
    title: liveStream.title || 'Healing Streams Live Healing Services',
    description: liveStream.description || 'Global healing service broadcast live.',
    thumbnail_url: liveStream.thumbnail_url,
    category: 'Live TV',
    is_live_now: 1
  } : null);

  const recordings = Array.isArray(programsData.recordings) ? programsData.recordings : [];
  const upcoming = Array.isArray(programsData.upcoming) ? programsData.upcoming : [];

  // Filter items for the Programs & Broadcasts section
  let filteredItems = [];
  if (programFilter === 'all') {
    if (activeLiveBroadcast) filteredItems.push({ ...activeLiveBroadcast, itemType: 'live' });
    recordings.forEach(r => filteredItems.push({ ...r, itemType: 'recording' }));
    upcoming.forEach(u => filteredItems.push({ ...u, itemType: 'upcoming' }));
  } else if (programFilter === 'live') {
    if (activeLiveBroadcast) filteredItems.push({ ...activeLiveBroadcast, itemType: 'live' });
  } else if (programFilter === 'recordings') {
    recordings.forEach(r => filteredItems.push({ ...r, itemType: 'recording' }));
  } else if (programFilter === 'upcoming') {
    upcoming.forEach(u => filteredItems.push({ ...u, itemType: 'upcoming' }));
  }

  return (
    <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-8 pb-28 lg:pb-16 animate-in fade-in duration-300 w-full min-w-0 max-w-full">
      <OnboardingModal user={profile} />

      {/* ── 1. Greeting ────────────────────────────────────────────── */}
      <div className="mb-6 sm:mb-8">
        <h1 className="font-serif text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight text-gray-950 dark:text-white mb-1.5">
          {greeting}, {displayName}
        </h1>
        <p className="text-gray-500 dark:text-gray-400 text-sm sm:text-base">
          <span className="hidden sm:inline">Pick up where you left off, replay past recordings, or join a live broadcast.</span>
          <span className="sm:hidden">Join a live service or resume your recordings.</span>
        </p>
      </div>

      {/* ── 2. Hero Grid ───────────────────────────────────────────── */}
      <div className="grid lg:grid-cols-12 gap-6 items-stretch mb-10 sm:mb-14">
        
        {/* Left Column (Hero Live Stream Card) */}
        <div className="lg:col-span-7 xl:col-span-7 flex flex-col min-w-0">
          <div className="relative rounded-3xl overflow-hidden bg-gray-950 shadow-xl ring-4 ring-blue-500/20 w-full min-w-0 max-w-full aspect-[16/10] sm:aspect-[16/9] lg:aspect-auto lg:h-full group min-h-[260px] sm:min-h-[380px]">
            <img
              src={activeLiveBroadcast?.thumbnail_url || liveStream?.thumbnail_url || "https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=1200&q=80"}
              alt="Live Service"
              className="absolute inset-0 w-full h-full object-cover object-center opacity-85 group-hover:scale-105 transition-transform duration-700"
            />
            <div className="absolute inset-0 bg-gradient-to-t from-gray-950 via-gray-950/45 to-black/25 pointer-events-none" />

            {/* Top Live Pill */}
            <div className="absolute top-4 left-4 z-10">
              <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#e11d48] text-white text-xs font-bold shadow-md">
                <span className="w-2 h-2 rounded-full bg-white animate-pulse" /> Live Now
              </span>
            </div>

            {/* Center Play Button for Desktop */}
            <Link
              to="/live-tv"
              className="hidden sm:flex absolute inset-0 items-center justify-center z-10"
              aria-label="Play Live Service"
            >
              <div className="w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-white text-[#5b3ee4] flex items-center justify-center shadow-2xl hover:scale-110 active:scale-95 transition-transform">
                <Play className="w-7 h-7 sm:w-9 sm:h-9 fill-[#5b3ee4] translate-x-1" />
              </div>
            </Link>

            {/* Bottom Content Bar */}
            <div className="absolute bottom-4 left-5 right-5 z-10 flex items-end justify-between gap-4">
              <div>
                <h2 className="font-serif font-bold text-white text-xl sm:text-2xl drop-shadow-md leading-tight">
                  {activeLiveBroadcast?.title || 'Healing Streams Live Healing Services'}
                </h2>
                <p className="text-gray-200 text-xs sm:text-sm mt-0.5 font-medium drop-shadow-sm">
                  {activeLiveBroadcast?.description || 'With Pastor Chris — Worldwide Broadcast'}
                </p>
              </div>

              {/* Mobile Play Button */}
              <Link
                to="/live-tv"
                className="sm:hidden shrink-0 w-12 h-12 rounded-full bg-white text-[#5b3ee4] flex items-center justify-center shadow-lg active:scale-95 transition-transform"
                aria-label="Play Live Service"
              >
                <Play className="w-5 h-5 fill-[#5b3ee4] translate-x-0.5" />
              </Link>
            </div>
          </div>
        </div>

        {/* Right Column: Desktop Stack / Quick Actions */}
        <div className="lg:col-span-5 xl:col-span-5 flex flex-col gap-4 sm:gap-5 min-w-0">
          
          {/* Join the service Card (Desktop) */}
          <div className="hidden lg:flex flex-col justify-between bg-[#f4f6fc] dark:bg-gray-850 rounded-3xl p-6 xl:p-8 border border-indigo-100/60 dark:border-gray-800 flex-1">
            <div>
              <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-[#5b3ee4] dark:text-[#a5b4fc] text-xs font-bold uppercase tracking-wider mb-3">
                <Radio className="w-3.5 h-3.5 text-red-500 animate-pulse" /> 24/7 Praise TV Broadcast
              </div>
              <h3 className="font-serif font-bold text-2xl text-gray-950 dark:text-white mb-2">
                Join the service
              </h3>
              <p className="text-gray-600 dark:text-gray-300 text-sm leading-relaxed mb-6">
                Watch the primary global live broadcast, submit real-time prayer requests to the ministers, and worship together with believers worldwide.
              </p>
            </div>
            <div className="flex flex-wrap items-center gap-3">
              <Link
                to="/live-tv"
                className="inline-flex items-center gap-2 px-6 py-3 bg-[#5b3ee4] hover:bg-[#4f2bee] text-white font-semibold rounded-full text-sm shadow-md shadow-indigo-500/20 transition-all active:scale-95"
              >
                <Play className="w-4 h-4 fill-white" /> Join live now
              </Link>
              <Link
                to="/contact"
                className="inline-flex items-center px-5 py-3 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-750 border border-gray-200 dark:border-gray-700 text-gray-800 dark:text-gray-200 font-medium rounded-full text-sm transition-all shadow-2xs active:scale-95"
              >
                Send a prayer request
              </Link>
            </div>
          </div>

          {/* Quick Action Grid (Desktop: 2x2, Mobile: 1x4 horizontal row) */}
          <div className="grid grid-cols-4 sm:grid-cols-4 lg:grid-cols-2 gap-2.5 sm:gap-4">
            
            {/* 1. Give */}
            <Link
              to="/give"
              className="bg-[#fdf2f8] dark:bg-[#2a1726] hover:bg-[#fce7f3] dark:hover:bg-[#381f33] rounded-2xl p-3 sm:p-5 border border-pink-100/60 dark:border-pink-900/40 flex flex-col items-center lg:items-start justify-center lg:justify-between transition-all group"
            >
              <div className="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-pink-100 dark:bg-pink-900/60 text-[#e11d48] flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                <Gift className="w-5 h-5 sm:w-6 sm:h-6" />
              </div>
              <span className="font-serif font-bold text-xs sm:text-base text-gray-950 dark:text-gray-100 mt-2 lg:mt-4">
                Give
              </span>
            </Link>

            {/* 2. Crusades / Digital crusades */}
            <Link
              to="/crusades"
              className="bg-[#fef3c7]/60 dark:bg-[#2c1e10] hover:bg-[#fef3c7] dark:hover:bg-[#3d2a17] rounded-2xl p-3 sm:p-5 border border-amber-100/60 dark:border-amber-900/40 flex flex-col items-center lg:items-start justify-center lg:justify-between transition-all group"
            >
              <div className="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-400 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                <Video className="w-5 h-5 sm:w-6 sm:h-6" />
              </div>
              <span className="font-serif font-bold text-xs sm:text-base text-gray-950 dark:text-gray-100 mt-2 lg:mt-4 text-center lg:text-left">
                <span className="lg:hidden">Crusades</span>
                <span className="hidden lg:inline">Digital crusades</span>
              </span>
            </Link>

            {/* 3. Go live in Studio */}
            <Link
              to="/dashboard/streaming"
              className="bg-[#eefbf7] dark:bg-[#102622] hover:bg-[#e4f7f2] dark:hover:bg-[#163630] rounded-2xl p-3 sm:p-5 border border-teal-100/60 dark:border-teal-900/40 flex flex-col items-center lg:items-start justify-center lg:justify-between transition-all group"
            >
              <div className="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-teal-100 dark:bg-teal-900/60 text-teal-700 dark:text-teal-400 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                <span className="hidden lg:inline"><Radio className="w-6 h-6" /></span>
                <span className="lg:hidden"><Heart className="w-5 h-5 text-teal-600 dark:text-teal-400" /></span>
              </div>
              <span className="font-serif font-bold text-xs sm:text-base text-gray-950 dark:text-gray-100 mt-2 lg:mt-4 text-center lg:text-left">
                <span className="lg:hidden">Studio</span>
                <span className="hidden lg:inline">Go live in Studio</span>
              </span>
            </Link>

            {/* 4. Explore VOD / Replays */}
            <Link
              to="/videos"
              className="bg-[#f3f0ff] dark:bg-[#1e1b38] hover:bg-[#eae5ff] dark:hover:bg-[#28244c] rounded-2xl p-3 sm:p-5 border border-purple-100/60 dark:border-purple-900/40 flex flex-col items-center lg:items-start justify-center lg:justify-between transition-all group"
            >
              <div className="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-purple-100 dark:bg-purple-900/60 text-[#5b3ee4] dark:text-[#a5b4fc] flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                <span className="hidden lg:inline"><Bookmark className="w-6 h-6" /></span>
                <span className="lg:hidden"><Radio className="w-5 h-5 text-[#5b3ee4] dark:text-[#a5b4fc]" /></span>
              </div>
              <span className="font-serif font-bold text-xs sm:text-base text-gray-950 dark:text-gray-100 mt-2 lg:mt-4 text-center lg:text-left">
                <span className="lg:hidden">Explore</span>
                <span className="hidden lg:inline">Explore VOD</span>
              </span>
            </Link>

          </div>

        </div>

      </div>

      {/* ── 3. Continue Watching (Recorded Program Viewing Progress) ── */}
      <section className="mb-10 sm:mb-14">
        <div className="flex items-center justify-between mb-4 sm:mb-5">
          <div>
            <h2 className="font-serif text-2xl sm:text-3xl font-bold text-gray-950 dark:text-white">
              Continue watching
            </h2>
            <p className="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-0.5">
              Resume recorded programs from your exact saved position
            </p>
          </div>
          <Link
            to="/videos"
            className="text-[#5b3ee4] dark:text-[#818cf8] hover:underline font-semibold text-sm"
          >
            See all
          </Link>
        </div>

        {/* Rail: horizontal swipe rail on mobile, responsive grid on desktop */}
        <div className="flex sm:grid sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 overflow-x-auto sm:overflow-visible pb-4 sm:pb-0 hide-scrollbar -mx-4 px-4 sm:mx-0 sm:px-0 w-full max-w-full">
          {continueWatching.map((item, idx) => {
            const palette = CARD_PALETTES[idx % CARD_PALETTES.length];
            const watchUrl = `/watch/recording/${item.recording_id || item.id}`;
            const percent = Number(item.percent || (item.duration_seconds > 0 ? Math.round((item.progress_seconds / item.duration_seconds) * 100) : 0));
            const timeLabel = item.timeLeft || formatMinutesLeft((item.duration_seconds || 3600) - (item.progress_seconds || 0));

            return (
              <Link
                key={item.recording_id || item.id || idx}
                to={watchUrl}
                className="w-[260px] sm:w-auto group flex flex-col shrink-0 sm:shrink min-w-0"
              >
                <div className={`relative aspect-[16/10] rounded-2xl overflow-hidden ${palette.bg} flex items-center justify-center mb-2.5 shadow-2xs group-hover:shadow-md transition-all`}>
                  {item.thumbnail_url ? (
                    <img 
                      src={item.thumbnail_url} 
                      alt={item.title}
                      className="absolute inset-0 w-full h-full object-cover opacity-60 group-hover:scale-105 transition-transform duration-500" 
                    />
                  ) : null}

                  {/* Organic curved shape backdrop */}
                  <div className={`absolute -right-6 -bottom-6 w-32 h-32 rounded-full ${palette.arc} opacity-70 pointer-events-none`} />
                  
                  {/* Centered White Circle Play Button */}
                  <div className="relative z-10 w-11 h-11 sm:w-12 sm:h-12 rounded-full bg-white text-[#5b3ee4] flex items-center justify-center shadow-lg group-hover:scale-110 active:scale-95 transition-transform">
                    <Play className="w-5 h-5 fill-[#5b3ee4] translate-x-0.5" />
                  </div>

                  {/* Program Tag Badge */}
                  {item.program_title && (
                    <div className="absolute top-2.5 left-2.5 z-10">
                      <span className="px-2 py-0.5 rounded-full bg-black/60 backdrop-blur-xs text-white text-[10px] font-semibold">
                        {item.program_title}
                      </span>
                    </div>
                  )}

                  {/* Persistent Progress Bar for Recorded Programs */}
                  <div className="absolute bottom-0 left-0 right-0 h-1.5 bg-black/40">
                    <div 
                      className="h-full bg-white rounded-r-full transition-all duration-300" 
                      style={{ width: `${Math.min(100, Math.max(0, percent))}%` }} 
                    />
                  </div>
                </div>
                
                <h3 className="font-bold text-sm text-gray-950 dark:text-gray-100 line-clamp-1 group-hover:text-[#5b3ee4] dark:group-hover:text-[#818cf8] transition-colors leading-snug">
                  {item.title}
                </h3>
                <div className="flex items-center gap-2 mt-0.5">
                  <p className="text-xs text-gray-400 dark:text-gray-500 font-medium flex items-center gap-1">
                    <Clock className="w-3 h-3" />
                    {timeLabel}
                  </p>
                  {percent > 0 && (
                    <span className="text-[10px] font-bold text-indigo-600 dark:text-indigo-400">
                      {percent}% watched
                    </span>
                  )}
                </div>
              </Link>
            );
          })}
        </div>
      </section>

      {/* ── 4. Programs & Broadcasts (CMS: Live Now vs Recorded vs Upcoming) ─ */}
      <section className="mb-10 sm:mb-14">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
          <div>
            <div className="flex items-center gap-2 mb-1">
              <span className="w-2.5 h-2.5 rounded-full bg-[#e11d48] animate-pulse" />
              <h2 className="font-serif text-2xl sm:text-3xl font-bold text-gray-950 dark:text-white">
                Programs & Broadcasts
              </h2>
            </div>
            <p className="text-xs sm:text-sm text-gray-500 dark:text-gray-400">
              Clear distinction between live streams, recorded replay episodes, and scheduled events
            </p>
          </div>

          {/* Filter Pills */}
          <div className="flex items-center gap-1.5 p-1 bg-gray-100 dark:bg-gray-800 rounded-xl self-start sm:self-auto overflow-x-auto hide-scrollbar max-w-full">
            <button
              onClick={() => setProgramFilter('all')}
              className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer ${
                programFilter === 'all'
                  ? 'bg-white dark:bg-gray-900 text-gray-950 dark:text-white shadow-xs'
                  : 'text-gray-600 dark:text-gray-400 hover:text-gray-950'
              }`}
            >
              All ({filteredItems.length})
            </button>
            <button
              onClick={() => setProgramFilter('live')}
              className={`px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all cursor-pointer ${
                programFilter === 'live'
                  ? 'bg-white dark:bg-gray-900 text-red-600 dark:text-red-400 shadow-xs'
                  : 'text-gray-600 dark:text-gray-400 hover:text-red-600'
              }`}
            >
              <span className="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse" /> Live Now ({activeLiveBroadcast ? 1 : 0})
            </button>
            <button
              onClick={() => setProgramFilter('recordings')}
              className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer ${
                programFilter === 'recordings'
                  ? 'bg-white dark:bg-gray-900 text-[#5b3ee4] dark:text-[#a5b4fc] shadow-xs'
                  : 'text-gray-600 dark:text-gray-400 hover:text-[#5b3ee4]'
              }`}
            >
              Recordings ({recordings.length})
            </button>
            <button
              onClick={() => setProgramFilter('upcoming')}
              className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer ${
                programFilter === 'upcoming'
                  ? 'bg-white dark:bg-gray-900 text-amber-600 dark:text-amber-400 shadow-xs'
                  : 'text-gray-600 dark:text-gray-400 hover:text-amber-600'
              }`}
            >
              Upcoming ({upcoming.length})
            </button>
          </div>
        </div>

        {/* 4 Cards Grid/Rail */}
        <div className="flex sm:grid sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 overflow-x-auto sm:overflow-visible pb-4 sm:pb-0 hide-scrollbar -mx-4 px-4 sm:mx-0 sm:px-0 w-full max-w-full">
          {filteredItems.map((item, idx) => {
            const palette = CARD_PALETTES[idx % CARD_PALETTES.length];

            // 1. ACTIVE LIVE BROADCAST ITEM
            if (item.itemType === 'live') {
              return (
                <div
                  key="live-now-card"
                  className="w-[270px] sm:w-auto group flex flex-col bg-white dark:bg-gray-900 rounded-2xl border-2 border-red-500/30 dark:border-red-500/40 overflow-hidden shadow-sm hover:shadow-md transition-all shrink-0 sm:shrink min-w-0"
                >
                  <div className="relative aspect-[16/10] bg-gray-950 p-3.5 flex flex-col justify-between overflow-hidden">
                    <img 
                      src={item.thumbnail_url || "https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=600&q=80"}
                      alt={item.title}
                      className="absolute inset-0 w-full h-full object-cover opacity-75 group-hover:scale-105 transition-transform duration-500" 
                    />
                    <div className="absolute inset-0 bg-gradient-to-t from-gray-950 via-gray-950/40 to-black/20" />
                    
                    <span className="relative z-10 self-start inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-[#e11d48] text-white text-[10px] font-bold shadow-xs">
                      <span className="w-1.5 h-1.5 rounded-full bg-white animate-pulse" /> LIVE NOW
                    </span>

                    <span className="relative z-10 self-end text-white/90 text-[10px] bg-black/60 backdrop-blur-xs px-2 py-0.5 rounded-md font-medium">
                      24/7 Primary Stream
                    </span>
                  </div>
                  
                  <div className="p-4 sm:p-5 flex flex-col justify-between flex-1">
                    <div>
                      <span className="text-[10px] font-bold uppercase tracking-wider text-red-500">
                        {item.category || 'Live Service'}
                      </span>
                      <h3 className="font-bold text-sm sm:text-base text-gray-950 dark:text-white group-hover:text-[#5b3ee4] dark:group-hover:text-[#818cf8] transition-colors leading-snug mt-0.5">
                        {item.title}
                      </h3>
                      <p className="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">
                        {item.description || 'Watch live broadcast with believers around the globe.'}
                      </p>
                    </div>

                    <Link
                      to="/live-tv"
                      className="mt-4 inline-flex items-center justify-center gap-1.5 w-full py-2 bg-red-600 hover:bg-red-500 text-white font-semibold rounded-xl text-xs shadow-xs transition-colors"
                    >
                      <Play className="w-3.5 h-3.5 fill-white" /> Watch Live Stream
                    </Link>
                  </div>
                </div>
              );
            }

            // 2. RECORDED PROGRAM ITEM (Opens its individual recording, NOT the live stream)
            if (item.itemType === 'recording') {
              const watchRecordingUrl = `/watch/recording/${item.id}`;
              const durationMins = item.duration_seconds ? Math.round(item.duration_seconds / 60) : 60;
              const formattedDate = item.program_date ? new Date(item.program_date).toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) : null;

              return (
                <div
                  key={`recording-${item.id}`}
                  className="w-[270px] sm:w-auto group flex flex-col bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 overflow-hidden shadow-2xs hover:shadow-md transition-all shrink-0 sm:shrink min-w-0"
                >
                  <div className={`relative aspect-[16/10] ${palette.bg} p-3.5 flex flex-col justify-between overflow-hidden`}>
                    {item.thumbnail_url ? (
                      <img 
                        src={item.thumbnail_url} 
                        alt={item.title}
                        className="absolute inset-0 w-full h-full object-cover opacity-75 group-hover:scale-105 transition-transform duration-500" 
                      />
                    ) : (
                      <div className={`absolute -right-6 -bottom-6 w-32 h-32 rounded-full ${palette.arc} opacity-80 pointer-events-none`} />
                    )}
                    <div className="absolute inset-0 bg-gradient-to-t from-gray-950 via-gray-950/30 to-black/10" />

                    <div className="relative z-10 flex items-center justify-between">
                      <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-indigo-600 text-white text-[10px] font-bold shadow-xs">
                        <Film className="w-3 h-3" /> RECORDING
                      </span>
                      {durationMins > 0 && (
                        <span className="text-white/90 text-[10px] bg-black/60 backdrop-blur-xs px-2 py-0.5 rounded-md font-medium">
                          {durationMins} min
                        </span>
                      )}
                    </div>

                    {formattedDate && (
                      <span className="relative z-10 self-start text-white/95 text-[10px] bg-black/60 backdrop-blur-xs px-2 py-0.5 rounded-md font-medium">
                        Recorded {formattedDate}
                      </span>
                    )}
                  </div>
                  
                  <div className="p-4 sm:p-5 flex flex-col justify-between flex-1">
                    <div>
                      <div className="flex items-center gap-1.5 mb-1">
                        <span className="text-[10px] font-bold uppercase tracking-wider text-[#5b3ee4] dark:text-[#a5b4fc]">
                          {item.program_title || item.category || 'Recorded Program'}
                        </span>
                      </div>
                      <h3 className="font-bold text-sm sm:text-base text-gray-950 dark:text-white group-hover:text-[#5b3ee4] dark:group-hover:text-[#818cf8] transition-colors leading-snug">
                        {item.title}
                      </h3>
                      <p className="text-xs text-gray-400 dark:text-gray-500 mt-1 line-clamp-2">
                        {item.description || 'Full broadcast recording available for on-demand playback.'}
                      </p>
                    </div>

                    <Link
                      to={watchRecordingUrl}
                      className="mt-4 inline-flex items-center justify-center gap-1.5 w-full py-2 bg-[#5b3ee4] hover:bg-[#4f2bee] text-white font-semibold rounded-xl text-xs shadow-xs transition-colors"
                    >
                      <Play className="w-3.5 h-3.5 fill-white" /> Play Recording
                    </Link>
                  </div>
                </div>
              );
            }

            // 3. UPCOMING SCHEDULED PROGRAM ITEM (Informative, no deceptive play button)
            if (item.itemType === 'upcoming') {
              return (
                <div
                  key={`upcoming-${item.id}`}
                  className="w-[270px] sm:w-auto group flex flex-col bg-white dark:bg-gray-900 rounded-2xl border border-dashed border-gray-200 dark:border-gray-700 overflow-hidden shadow-2xs hover:shadow-sm transition-all shrink-0 sm:shrink min-w-0"
                >
                  <div className="relative aspect-[16/10] bg-gray-900 p-3.5 flex flex-col justify-between overflow-hidden">
                    <img 
                      src={item.thumbnail_url || "https://images.unsplash.com/photo-1502086223501-7ea6ecd79368?auto=format&fit=crop&w=600&q=80"}
                      alt={item.title}
                      className="absolute inset-0 w-full h-full object-cover opacity-60 group-hover:scale-105 transition-transform duration-500" 
                    />
                    <div className="absolute inset-0 bg-gradient-to-t from-gray-950 via-gray-950/40 to-black/20" />

                    <div className="relative z-10 flex items-center justify-between">
                      <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-500 text-white text-[10px] font-bold shadow-xs">
                        <Calendar className="w-3 h-3" /> UPCOMING
                      </span>
                    </div>

                    <span className="relative z-10 self-start text-white/95 text-[10px] bg-black/60 backdrop-blur-xs px-2 py-0.5 rounded-md font-medium flex items-center gap-1">
                      <Clock className="w-3 h-3 text-amber-400" />
                      {item.schedule_time || 'Coming Soon'}
                    </span>
                  </div>
                  
                  <div className="p-4 sm:p-5 flex flex-col justify-between flex-1">
                    <div>
                      <span className="text-[10px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                        {item.category || 'Scheduled Event'}
                      </span>
                      <h3 className="font-bold text-sm sm:text-base text-gray-950 dark:text-white leading-snug mt-0.5">
                        {item.title}
                      </h3>
                      <p className="text-xs text-gray-400 dark:text-gray-500 mt-1 line-clamp-2">
                        {item.description || 'Mark your calendar to join this upcoming broadcast.'}
                      </p>
                    </div>

                    <div className="mt-4 inline-flex items-center justify-center gap-1.5 w-full py-2 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 font-medium rounded-xl text-xs">
                      <CalendarDays className="w-3.5 h-3.5 text-gray-400" />
                      Broadcasts {item.schedule_time || 'soon'}
                    </div>
                  </div>
                </div>
              );
            }

            return null;
          })}
        </div>
      </section>

      {/* ── Preserved Meetings & Crusades Features ─────────────────── */}
      {crusades.active.length > 0 && (
        <section className="mb-10">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-lg font-bold text-gray-950 dark:text-white flex items-center gap-2">
              <Radio className="w-5 h-5 text-red-500" /> Active Digital Crusades
            </h3>
            <Link to="/crusades" className="text-sm font-semibold text-[#5b3ee4] dark:text-[#818cf8] hover:underline">
              View All
            </Link>
          </div>
          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {crusades.active.map(c => (
              <Link 
                key={c.id} 
                to={`/crusades/${c.code}`}
                className="p-4 bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl hover:shadow-sm transition-shadow flex items-center justify-between"
              >
                <div>
                  <h4 className="font-bold text-gray-900 dark:text-white">{c.title}</h4>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">{c.total_views || 0} watching now</p>
                </div>
                <Play className="w-5 h-5 text-[#5b3ee4]" />
              </Link>
            ))}
          </div>
        </section>
      )}

      {meetings.length > 0 && (
        <section className="mb-8">
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-lg font-bold text-gray-950 dark:text-white flex items-center gap-2">
              <Video className="w-5 h-5 text-purple-600" /> Your Scheduled Meetings
            </h3>
            <Link to="/meeting/create" className="text-sm font-semibold text-[#5b3ee4] dark:text-[#818cf8] hover:underline">
              Create New
            </Link>
          </div>
          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {meetings.map(m => (
              <Link 
                key={m.id} 
                to={`/meeting/${m.meeting_code}`}
                className="p-4 bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl hover:shadow-sm transition-shadow flex items-center justify-between"
              >
                <div>
                  <h4 className="font-bold text-gray-900 dark:text-white">{m.title}</h4>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">{new Date(m.created_at).toLocaleDateString()}</p>
                </div>
                <ChevronRight className="w-5 h-5 text-gray-400" />
              </Link>
            ))}
          </div>
        </section>
      )}

    </div>
  );
}
