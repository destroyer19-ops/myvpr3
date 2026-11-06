import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Play, Plus, Check, Loader2, Video, ChevronDown, Layers, Search, Globe } from 'lucide-react';
import { videosApi, streamsApi } from '../services/api';

export default function VideosPage() {
  const [videos, setVideos] = useState([]);
  const [languages, setLanguages] = useState([]);
  const [loading, setLoading] = useState(true);
  const [activeCategory, setActiveCategory] = useState('All');
  const [selectedLanguage, setSelectedLanguage] = useState('');
  const [liveStream, setLiveStream] = useState(null);
  const [inMyList, setInMyList] = useState(false);

  useEffect(() => {
    async function fetchData() {
      try {
        setLoading(true);
        const params = {};
        if (selectedLanguage) params.language = selectedLanguage;
        if (activeCategory === 'Children') params.children_outreach = '1';
        if (activeCategory === 'Movies') params.movie_outreach = '1';
        if (activeCategory === 'Ministrations') params.ministrations = '1';

        const [videosRes, langRes, liveRes] = await Promise.all([
          videosApi.list(params).catch(() => null),
          videosApi.getLanguages().catch(() => null),
          streamsApi.getLive().catch(() => null)
        ]);
        
        if (videosRes?.success && Array.isArray(videosRes.data)) {
          setVideos(videosRes.data);
        }
        if (langRes?.success && Array.isArray(langRes.data)) {
          setLanguages(langRes.data);
        }
        if (liveRes?.success && liveRes.streams?.length > 0) {
          setLiveStream(liveRes.streams[0]);
        }
      } catch (error) {
        console.error('Failed to fetch videos:', error);
      } finally {
        setLoading(false);
      }
    }
    fetchData();
  }, [activeCategory, selectedLanguage]);

  // PM sample 10 trending titles to match Explore.png exactly
  const sampleTrending = [
    {
      id: 's1',
      badge: 'Ministration',
      badgeTextColor: 'text-[#5b3ee4]',
      coverTitle: 'Day of Bliss',
      title: 'A Day of Bliss with Pastor Chris (Samoa)',
      languages: 'English',
      bgClass: 'bg-[#5B3EE4]',
      arcClass: 'bg-[#7C5CF7]'
    },
    {
      id: 's2',
      badge: 'Ministration',
      badgeTextColor: 'text-[#4f2bee]',
      coverTitle: 'Healing Streams',
      title: 'Healing Streams Live Healing Services',
      languages: 'English · French',
      bgClass: 'bg-[#4F2BEE]',
      arcClass: 'bg-[#6366F1]'
    },
    {
      id: 's3',
      badge: 'Music',
      badgeTextColor: 'text-[#e11d48]',
      coverTitle: 'Praise Night',
      title: 'Praise and worship medley',
      languages: 'English',
      bgClass: 'bg-[#E11D48]',
      arcClass: 'bg-[#F43F5E]'
    },
    {
      id: 's4',
      badge: 'Children',
      badgeTextColor: 'text-[#0d9488]',
      coverTitle: 'Bible Stories',
      title: "Children's Bible stories",
      languages: 'English · Spanish',
      bgClass: 'bg-[#0D9488]',
      arcClass: 'bg-[#14B8A6]'
    },
    {
      id: 's5',
      badge: 'Teaching',
      badgeTextColor: 'text-[#d97706]',
      coverTitle: 'Sunday Service',
      title: 'Sunday Service highlights',
      languages: 'English',
      bgClass: 'bg-[#F59E0B]',
      arcClass: 'bg-[#FBBF24]'
    },
    {
      id: 's6',
      badge: 'Movie',
      badgeTextColor: 'text-indigo-950',
      coverTitle: 'Feature Film',
      title: 'Faith-based feature film',
      languages: 'English',
      bgClass: 'bg-[#1E1B4B]',
      arcClass: 'bg-[#312E81]'
    },
    {
      id: 's7',
      badge: 'Children',
      badgeTextColor: 'text-[#0d9488]',
      coverTitle: "Kids' Praise",
      title: 'Kids praise and dance',
      languages: 'English',
      bgClass: 'bg-[#0D9488]',
      arcClass: 'bg-[#14B8A6]'
    },
    {
      id: 's8',
      badge: 'Teaching',
      badgeTextColor: 'text-[#d97706]',
      coverTitle: 'On Prayer',
      title: 'Teaching series on prayer',
      languages: 'English · Portuguese',
      bgClass: 'bg-[#F59E0B]',
      arcClass: 'bg-[#FBBF24]'
    },
    {
      id: 's9',
      badge: 'Music',
      badgeTextColor: 'text-[#e11d48]',
      coverTitle: 'Choir',
      title: 'Choir ministration',
      languages: 'English',
      bgClass: 'bg-[#E11D48]',
      arcClass: 'bg-[#F43F5E]'
    },
    {
      id: 's10',
      badge: 'Movie',
      badgeTextColor: 'text-indigo-950',
      coverTitle: 'Family Drama',
      title: 'Short drama for the family',
      languages: 'English · French',
      bgClass: 'bg-[#1E1B4B]',
      arcClass: 'bg-[#312E81]'
    }
  ];

  const categoriesList = ['All', 'Ministrations', 'Movies', 'Children', 'Music', 'Teachings'];

  // Match actual video if available or fallback
  const heroItem = videos.length > 0 ? videos[0] : {
    id: 1,
    title: 'A Day of Bliss with Pastor Chris (Samoa)',
    description: 'Relive a day of worship, the Word and miracles from the Samoa meeting.',
    thumbnail_url: 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=1200&q=80'
  };

  const displayList = videos.length > 0 
    ? videos.map((v, i) => {
        const sample = sampleTrending[i % sampleTrending.length];
        return {
          id: v.id,
          badge: v.category || (v.category_ministrations ? 'Ministration' : v.category_movie_outreach ? 'Movie' : v.category_children_outreach ? 'Children' : sample.badge),
          badgeTextColor: sample.badgeTextColor,
          coverTitle: v.title.split(' ')[0] + ' ' + (v.title.split(' ')[1] || ''),
          title: v.title,
          languages: v.language || sample.languages,
          thumbnail_url: v.thumbnail_url,
          bgClass: sample.bgClass,
          arcClass: sample.arcClass
        };
      })
    : sampleTrending;

  return (
    <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-8 pb-28 lg:pb-16 animate-in fade-in duration-300">
      
      {/* ── 1. Hero Featured Grid ─────────────────────────────────── */}
      <div className="grid lg:grid-cols-12 gap-6 items-stretch mb-8 sm:mb-12">
        
        {/* Left Large Featured Card (65% width) */}
        <div className="lg:col-span-8 flex flex-col min-w-0">
          <div className="relative rounded-3xl overflow-hidden bg-gray-950 shadow-xl w-full min-w-0 max-w-full aspect-[16/10] sm:aspect-[16/9] lg:aspect-auto lg:h-full min-h-[340px] sm:min-h-[400px] p-6 sm:p-10 lg:p-12 flex flex-col justify-end text-white group h-full">
            <img
              src={heroItem.thumbnail_url || "https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=1200&q=80"}
              alt={heroItem.title}
              className="absolute inset-0 w-full h-full object-cover object-center opacity-70 group-hover:scale-105 transition-transform duration-700"
            />
            {/* Multi-stop gradient for maximum text contrast across any image */}
            <div className="absolute inset-0 bg-gradient-to-t from-gray-950 via-gray-950/65 to-gray-950/20" />

            {/* Featured Badge */}
            <div className="relative z-10 mb-3 sm:mb-4">
              <span className="inline-flex items-center px-3.5 py-1 rounded-full bg-[#f59e0b] text-white text-xs font-bold tracking-wide shadow-xs">
                Featured ministration
              </span>
            </div>

            {/* Title & Description */}
            <div className="relative z-10 max-w-2xl">
              <h2 className="font-serif font-bold text-2xl sm:text-4xl lg:text-5xl text-white mb-2 sm:mb-3 leading-tight drop-shadow-md">
                {heroItem.title}
              </h2>
              <p className="text-white/90 text-sm sm:text-base max-w-xl mb-6 sm:mb-8 leading-relaxed font-normal drop-shadow-sm">
                {heroItem.description || 'Relive a day of worship, the Word and miracles from the Samoa meeting.'}
              </p>

              {/* Action Buttons */}
              <div className="flex flex-wrap items-center gap-3">
                <Link
                  to={`/watch/${heroItem.id}`}
                  className="inline-flex items-center gap-2 px-6 sm:px-7 py-3 sm:py-3.5 bg-white hover:bg-gray-100 text-gray-950 font-semibold rounded-full text-sm shadow-md transition-all active:scale-95 cursor-pointer"
                >
                  <Play className="w-4 h-4 fill-gray-950 translate-x-0.5" /> Play now
                </Link>
                <button
                  type="button"
                  onClick={() => setInMyList(!inMyList)}
                  className="inline-flex items-center gap-1.5 px-5 sm:px-6 py-3 sm:py-3.5 bg-white/15 hover:bg-white/25 border border-white/30 text-white font-semibold rounded-full text-sm backdrop-blur-md transition-all active:scale-95 cursor-pointer"
                >
                  {inMyList ? <Check className="w-4 h-4 text-emerald-400" /> : <Plus className="w-4 h-4" />}
                  {inMyList ? 'Added to list' : 'Add to my list'}
                </button>
              </div>
            </div>
          </div>
        </div>

        {/* Right Stack of 2 Cards (35% width) */}
        <div className="lg:col-span-4 flex flex-col gap-5 sm:gap-6 min-w-0">
          
          {/* Card 1: Live Now Service (Purple) */}
          <Link
            to="/live-tv"
            className="relative overflow-hidden bg-gradient-to-br from-[#5b3ee4] to-[#4327ca] hover:from-[#4f2bee] hover:to-[#3b20ba] rounded-3xl p-6 sm:p-8 text-white flex flex-col justify-between shadow-sm min-h-[190px] transition-all group flex-1"
          >
            <div className="absolute -right-8 -bottom-8 w-44 h-44 rounded-full bg-[#7c5cf7] opacity-60 pointer-events-none" />
            <div className="relative z-10">
              <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#e11d48] text-white text-xs font-bold shadow-xs">
                <span className="w-1.5 h-1.5 rounded-full bg-white animate-pulse" /> Live now
              </span>
              <h3 className="font-serif font-bold text-xl sm:text-2xl text-white mt-4 mb-2 leading-snug drop-shadow-sm">
                {liveStream?.title || 'Healing Streams Live Healing Services'}
              </h3>
            </div>
            <span className="relative z-10 text-indigo-100 group-hover:text-white font-semibold text-sm underline underline-offset-4 transition-colors">
              Join the live service
            </span>
          </Link>

          {/* Card 2: Continue Series (Mint) */}
          <div className="bg-[#eefbf7] dark:bg-[#102622] rounded-3xl p-6 sm:p-8 border border-teal-100/60 dark:border-teal-900/40 flex flex-col justify-between min-h-[190px] flex-1">
            <div className="w-11 h-11 rounded-2xl bg-teal-100 dark:bg-teal-900/60 text-teal-700 dark:text-teal-300 flex items-center justify-center">
              <Layers className="w-5 h-5" />
            </div>
            <div>
              <h3 className="font-serif font-bold text-xl sm:text-2xl text-teal-950 dark:text-teal-100 mb-1 leading-snug">
                Continue your series
              </h3>
              <p className="text-teal-800/80 dark:text-teal-300 text-sm">
                Pick up teachings you started this week.
              </p>
            </div>
          </div>

        </div>

      </div>

      {/* ── 2. Filter Pills Row ───────────────────────────────────── */}
      <div className="flex items-center gap-2.5 sm:gap-3 mb-8 sm:mb-10 overflow-x-auto pb-2 -mx-4 px-4 sm:mx-0 sm:px-0 hide-scrollbar w-full max-w-full">
        
        {/* Language Dropdown Pill */}
        <div className="relative shrink-0">
          <select
            value={selectedLanguage}
            onChange={(e) => setSelectedLanguage(e.target.value)}
            className="appearance-none bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 font-medium pl-8 pr-9 py-2 rounded-full text-sm shadow-2xs focus:outline-none focus:ring-2 focus:ring-[#5b3ee4]/20 cursor-pointer"
          >
            <option value="">All languages</option>
            {languages.map(l => (
              <option key={l} value={l}>{l}</option>
            ))}
          </select>
          <Globe className="w-3.5 h-3.5 text-gray-500 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
          <ChevronDown className="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" />
        </div>

        {/* Category Pills */}
        {categoriesList.map(cat => {
          const isActive = activeCategory === cat;
          return (
            <button
              key={cat}
              type="button"
              onClick={() => setActiveCategory(cat)}
              className={`shrink-0 px-4 sm:px-5 py-2 rounded-full text-sm font-semibold transition-all cursor-pointer ${
                isActive
                  ? 'bg-[#5b3ee4] text-white shadow-sm'
                  : 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-gray-400 dark:hover:border-gray-500'
              }`}
            >
              {cat}
            </button>
          );
        })}

      </div>

      {/* ── 3. Trending this week Grid ────────────────────────────── */}
      <section>
        <div className="flex items-center justify-between mb-5 sm:mb-6">
          <h2 className="font-serif text-2xl sm:text-3xl font-bold text-gray-950 dark:text-white">
            Trending this week
          </h2>
          <span className="text-xs sm:text-sm text-gray-400 font-medium">
            {displayList.length} titles
          </span>
        </div>

        {loading ? (
          <div className="flex justify-center items-center py-20">
            <Loader2 className="w-8 h-8 animate-spin text-[#5b3ee4]" />
          </div>
        ) : displayList.length === 0 ? (
          <div className="text-center py-16 bg-gray-50 dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800">
            <Search className="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-3" />
            <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-1">No titles found</h3>
            <p className="text-sm text-gray-500 dark:text-gray-400">Try changing the category or language filter.</p>
          </div>
        ) : (
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 sm:gap-6">
            {displayList.map((item, idx) => (
              <Link
                key={item.id || idx}
                to={`/watch/${item.id}`}
                className="group flex flex-col"
              >
                {/* Tall rounded card cover with organic circular shapes */}
                <div className={`relative aspect-[3/4] rounded-2xl overflow-hidden ${item.bgClass} p-4 flex flex-col justify-between text-white shadow-sm group-hover:shadow-xl group-hover:scale-[1.02] transition-all duration-300 mb-2.5`}>
                  {item.arcClass && (
                    <div className={`absolute -right-8 -bottom-8 w-36 h-36 rounded-full ${item.arcClass} opacity-80 pointer-events-none`} />
                  )}
                  {item.thumbnail_url && (
                    <img
                      src={item.thumbnail_url}
                      alt={item.title}
                      className="absolute inset-0 w-full h-full object-cover object-center opacity-55 group-hover:scale-105 transition-transform duration-500"
                    />
                  )}
                  <div className="absolute inset-0 bg-gradient-to-t from-gray-950/90 via-gray-950/30 to-black/10 pointer-events-none" />

                  {/* Top left badge (white pill with colored text) */}
                  <div className="relative z-10 self-start">
                    <span className={`text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-white/95 backdrop-blur-xs ${item.badgeTextColor || 'text-gray-900'} shadow-xs`}>
                      {item.badge}
                    </span>
                  </div>

                  {/* Bottom inside title in bold serif */}
                  <div className="relative z-10">
                    <span className="font-serif font-bold text-lg sm:text-xl leading-tight block text-white drop-shadow-md">
                      {item.coverTitle}
                    </span>
                  </div>
                </div>

                {/* Sub-card details */}
                <h3 className="font-bold text-sm text-gray-950 dark:text-gray-100 line-clamp-2 leading-snug group-hover:text-[#5b3ee4] dark:group-hover:text-[#818cf8] transition-colors">
                  {item.title}
                </h3>
                <p className="text-xs text-gray-400 dark:text-gray-500 mt-1 font-medium">
                  {item.languages}
                </p>
              </Link>
            ))}
          </div>
        )}
      </section>

    </div>
  );
}
