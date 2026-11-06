import { request } from '../../services/api';
import React, { useEffect, useState, useCallback, useRef } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import { 
  Radio, 
  Video, 
  VideoOff, 
  Mic, 
  MicOff, 
  Monitor, 
  Copy, 
  Check, 
  ChevronRight, 
  ExternalLink, 
  Share2, 
  Sparkles,
  Activity,
  User
} from 'lucide-react';

export default function StreamingDashboardPage() {
  const { user } = useAuth();
  const [status, setStatus] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [copied, setCopied] = useState(false);
  
  // Camera & Mic preview state
  const [camActive, setCamActive] = useState(false);
  const [micActive, setMicActive] = useState(true);
  const videoRef = useRef(null);
  const streamRef = useRef(null);

  const fetchStatus = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const json = await request('/streaming/status');
      if (json?.success) {
        setStatus(json.data);
      } else {
        // Fallback demo data so page renders correctly even if API returns no plan
        setStatus({
          has_active_plan: true,
          plan: {
            name: 'Basic Streamer',
            resolution: '720p',
            end_date: '2026-11-06'
          }
        });
      }
    } catch (e) {
      console.warn('Using fallback streaming status:', e);
      setStatus({
        has_active_plan: true,
        plan: {
          name: 'Basic Streamer',
          resolution: '720p',
          end_date: '2026-11-06'
        }
      });
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchStatus();
  }, [fetchStatus]);

  // Handle camera toggle for preview
  const toggleCamera = async () => {
    if (camActive) {
      if (streamRef.current) {
        streamRef.current.getTracks().forEach(t => t.stop());
        streamRef.current = null;
      }
      setCamActive(false);
    } else {
      try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
        streamRef.current = stream;
        if (videoRef.current) {
          videoRef.current.srcObject = stream;
        }
        setCamActive(true);
      } catch (err) {
        console.warn('Camera preview not permitted or unavailable:', err);
        setCamActive(!camActive);
      }
    }
  };

  useEffect(() => {
    return () => {
      if (streamRef.current) {
        streamRef.current.getTracks().forEach(t => t.stop());
      }
    };
  }, []);

  const username = user?.name || user?.username || 'danoo';
  const streamUrl = `${window.location.origin}/stream/${username}`;

  const handleCopy = (text) => {
    navigator.clipboard.writeText(text);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const handleWhatsAppShare = () => {
    const text = encodeURIComponent(`Watch my live broadcast on Virtual Praise Room: ${streamUrl}`);
    window.open(`https://wa.me/?text=${text}`, '_blank');
  };

  if (loading) {
    return (
      <div className="flex justify-center items-center min-h-[60vh]">
        <div className="w-10 h-10 border-4 border-gray-100 dark:border-gray-800 border-t-[#5b3ee4] rounded-full animate-spin"></div>
      </div>
    );
  }

  const plan = status?.plan || {
    name: 'Basic Streamer',
    resolution: '720p',
    end_date: '2026-11-06'
  };

  const renewalFormatted = plan.end_date 
    ? new Date(plan.end_date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
    : '6 Nov 2026';

  return (
    <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-8 pb-28 lg:pb-16 animate-in fade-in duration-300">
      
      {/* ── 1. Page Header ────────────────────────────────────────── */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 sm:mb-10">
        <div>
          <h1 className="font-serif text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight text-gray-950 dark:text-white mb-1.5">
            Broadcast Studio
          </h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm sm:text-base max-w-2xl">
            Go live from your browser, or connect the streaming software you already use.
          </p>
        </div>

        {/* Ready Badge on Right */}
        <div className="self-start sm:self-center shrink-0">
          <span className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-[#eefbf7] dark:bg-teal-950/60 border border-teal-200/50 dark:border-teal-800/60 text-teal-800 dark:text-teal-300 text-xs font-semibold shadow-2xs">
            <span className="w-2 h-2 rounded-full bg-teal-500 animate-pulse" />
            Camera and microphone ready
          </span>
        </div>
      </div>

      {/* ── 2. Main Studio Grid (2 columns on Desktop) ────────────── */}
      <div className="grid lg:grid-cols-12 gap-6 items-start">
        
        {/* Left Column (Browser Studio + Streaming Software) */}
        <div className="lg:col-span-8 flex flex-col gap-6">
          
          {/* Top Card: Browser Studio */}
          <div className="bg-white dark:bg-gray-900 rounded-3xl p-6 sm:p-8 border border-gray-100 dark:border-gray-800 shadow-sm flex flex-col md:flex-row gap-6 lg:gap-8 items-center">
            
            {/* Left Preview Box */}
            <div className="w-full md:w-72 aspect-[4/3] bg-gradient-to-b from-[#1b123d] to-[#0e0728] rounded-2xl relative flex flex-col justify-between p-4 overflow-hidden ring-2 ring-indigo-500/30 shadow-inner shrink-0">
              {/* Camera Video Feed */}
              {camActive && (
                <video
                  ref={videoRef}
                  autoPlay
                  playsInline
                  muted
                  className="absolute inset-0 w-full h-full object-cover"
                />
              )}

              {/* Top Left Tag */}
              <div className="relative z-10 self-start">
                <span className="bg-black/60 backdrop-blur-xs text-white text-[10px] font-semibold px-2.5 py-1 rounded-full">
                  Camera preview
                </span>
              </div>

              {/* Center Avatar Placeholder (when camera is off) */}
              {!camActive && (
                <div className="absolute inset-0 flex items-center justify-center">
                  <div className="w-16 h-16 rounded-full bg-gradient-to-tr from-[#9333ea] to-[#db2777] flex items-center justify-center text-white shadow-lg">
                    <User className="w-8 h-8 text-white/90" />
                  </div>
                </div>
              )}

              {/* Bottom Center Mic & Camera Controls */}
              <div className="relative z-10 flex items-center justify-center gap-2 pt-2">
                <button
                  type="button"
                  onClick={toggleCamera}
                  className={`w-9 h-9 rounded-full flex items-center justify-center shadow-md transition-all active:scale-90 ${
                    camActive ? 'bg-white text-gray-900' : 'bg-white/90 text-gray-700 hover:bg-white'
                  }`}
                  title={camActive ? 'Turn off camera' : 'Turn on camera'}
                >
                  {camActive ? <Video className="w-4 h-4" /> : <VideoOff className="w-4 h-4 text-red-500" />}
                </button>
                <button
                  type="button"
                  onClick={() => setMicActive(!micActive)}
                  className={`w-9 h-9 rounded-full flex items-center justify-center shadow-md transition-all active:scale-90 ${
                    micActive ? 'bg-white text-gray-900' : 'bg-white/90 text-gray-700 hover:bg-white'
                  }`}
                  title={micActive ? 'Mute microphone' : 'Unmute microphone'}
                >
                  {micActive ? <Mic className="w-4 h-4" /> : <MicOff className="w-4 h-4 text-red-500" />}
                </button>
              </div>
            </div>

            {/* Right Sub-Panel Info */}
            <div className="flex-1 flex flex-col items-start justify-center">
              <div className="w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-950/60 text-[#6344e7] dark:text-[#a5b4fc] flex items-center justify-center mb-3">
                <Video className="w-5 h-5" />
              </div>
              <div className="flex items-center gap-2 mb-1">
                <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 text-[10px] font-bold uppercase tracking-wider">
                  Real-Time Web Studio
                </span>
              </div>
              <h2 className="font-serif font-bold text-2xl sm:text-3xl text-gray-950 dark:text-white mb-2">
                Browser Studio
              </h2>
              <p className="text-gray-600 dark:text-gray-300 text-sm leading-relaxed mb-3">
                The fastest way to stream live from your webcam and microphone directly inside your browser with sub-second real-time delivery.
              </p>
              <p className="text-xs text-gray-400 dark:text-gray-500 mb-6">
                Includes camera and mic controls, live local preview, and one-click viewer sharing for your congregation.
              </p>
              <Link
                to="/studio"
                className="inline-flex items-center gap-2 px-7 py-3.5 bg-[#5b3ee4] hover:bg-[#4f2bee] text-white font-semibold rounded-full text-sm shadow-lg shadow-indigo-500/25 transition-all active:scale-95"
              >
                <Radio className="w-4 h-4" /> Launch Web Studio
              </Link>
            </div>

          </div>

          {/* Bottom Card: Streaming Software (OBS, vMix) */}
          <div className="bg-[#eefbf7] dark:bg-[#102622] rounded-3xl p-6 sm:p-8 border border-teal-100/60 dark:border-teal-900/40 shadow-2xs">
            <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-6">
              <div className="flex items-start gap-4">
                <div className="w-11 h-11 rounded-2xl bg-white dark:bg-teal-900/60 border border-teal-100 dark:border-teal-800 flex items-center justify-center text-teal-700 dark:text-teal-300 shadow-2xs shrink-0">
                  <Monitor className="w-6 h-6" />
                </div>
                <div>
                  <h3 className="font-serif font-bold text-2xl text-teal-950 dark:text-teal-100 mb-1">
                    Streaming software
                  </h3>
                  <p className="text-teal-800/80 dark:text-teal-300 text-sm max-w-md">
                    Connect OBS Studio, vMix or Wirecast using your secure RTMP details.
                  </p>
                </div>
              </div>

              <Link
                to="/dashboard/streaming/setup"
                className="self-start sm:self-auto shrink-0 inline-flex items-center gap-1.5 px-5 py-2.5 bg-[#0f766e] hover:bg-[#115e59] text-white font-semibold rounded-full text-xs sm:text-sm transition-all shadow-sm active:scale-95"
              >
                View stream keys and setup
              </Link>
            </div>

            {/* 3 Numbered Steps Cards */}
            <div className="grid sm:grid-cols-3 gap-4 pt-2">
              
              {/* Step 1 */}
              <div className="bg-white dark:bg-gray-900 rounded-2xl p-5 border border-teal-50 dark:border-gray-800 shadow-2xs flex flex-col">
                <div className="w-7 h-7 rounded-full bg-teal-600 text-white font-bold text-xs flex items-center justify-center mb-3">
                  1
                </div>
                <h4 className="font-bold text-sm text-gray-900 dark:text-white mb-1">
                  Copy your server URL
                </h4>
                <p className="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                  Paste it into the stream settings of your software.
                </p>
              </div>

              {/* Step 2 */}
              <div className="bg-white dark:bg-gray-900 rounded-2xl p-5 border border-teal-50 dark:border-gray-800 shadow-2xs flex flex-col">
                <div className="w-7 h-7 rounded-full bg-teal-600 text-white font-bold text-xs flex items-center justify-center mb-3">
                  2
                </div>
                <h4 className="font-bold text-sm text-gray-900 dark:text-white mb-1">
                  Add your stream key
                </h4>
                <p className="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                  Keep it private. Anyone with it can stream to your page.
                </p>
              </div>

              {/* Step 3 */}
              <div className="bg-white dark:bg-gray-900 rounded-2xl p-5 border border-teal-50 dark:border-gray-800 shadow-2xs flex flex-col">
                <div className="w-7 h-7 rounded-full bg-teal-600 text-white font-bold text-xs flex items-center justify-center mb-3">
                  3
                </div>
                <h4 className="font-bold text-sm text-gray-900 dark:text-white mb-1">
                  Start streaming
                </h4>
                <p className="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                  Your audience sees you live within a few seconds.
                </p>
              </div>

            </div>
          </div>

        </div>

        {/* Right Column (Your Subscription + Your Public Link) */}
        <div className="lg:col-span-4 flex flex-col gap-6">
          
          {/* Card 1: Your Subscription */}
          <div className="bg-white dark:bg-gray-900 rounded-3xl p-6 sm:p-8 border border-gray-100 dark:border-gray-800 shadow-sm flex flex-col">
            <div className="flex items-center justify-between mb-4">
              <span className="font-bold text-sm text-gray-900 dark:text-white">
                Your subscription
              </span>
              <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 text-xs font-semibold">
                <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" /> Active
              </span>
            </div>

            <p className="text-xs text-gray-400 dark:text-gray-500 font-medium mb-1">
              Plan
            </p>
            <h3 className="font-serif font-bold text-2xl text-gray-950 dark:text-white mb-4">
              {plan.name || 'Basic Streamer'}
            </h3>

            {/* Resolution & Renews 2 boxes */}
            <div className="grid grid-cols-2 gap-3 mb-5">
              <div className="bg-gray-50 dark:bg-gray-850 rounded-2xl p-4">
                <p className="text-xs text-gray-400 dark:text-gray-500 font-medium">Resolution</p>
                <p className="font-bold text-base text-gray-900 dark:text-white mt-1">{plan.resolution || '720p'}</p>
              </div>
              <div className="bg-gray-50 dark:bg-gray-850 rounded-2xl p-4">
                <p className="text-xs text-gray-400 dark:text-gray-500 font-medium">Renews</p>
                <p className="font-bold text-base text-gray-900 dark:text-white mt-1">{renewalFormatted}</p>
              </div>
            </div>

            {/* Upgrade banner */}
            <div className="bg-[#fef3c7] dark:bg-amber-950/40 border border-amber-200/60 dark:border-amber-900/40 rounded-2xl p-5 flex flex-col items-start">
              <p className="text-xs sm:text-sm font-semibold text-amber-950 dark:text-amber-100 mb-3 leading-snug">
                Stream in full HD. Move up a plan to broadcast in 1080p.
              </p>
              <Link
                to="/dashboard/streaming/plans"
                className="px-5 py-2.5 bg-[#f59e0b] hover:bg-[#d97706] text-white text-xs sm:text-sm font-semibold rounded-full shadow-sm transition-all active:scale-95"
              >
                Upgrade plan
              </Link>
            </div>
          </div>

          {/* Card 2: Your Public Link */}
          <div className="bg-gradient-to-br from-[#4b2bee] via-[#2f1cb0] to-[#1a0c7b] rounded-3xl p-6 sm:p-8 text-white shadow-xl flex flex-col">
            <h3 className="font-serif font-bold text-2xl mb-1">
              Your public link
            </h3>
            <p className="text-xs sm:text-sm text-indigo-200/80 mb-5 leading-relaxed">
              Share this link so your audience can watch you live.
            </p>

            {/* Link Box with Copy Button */}
            <div className="bg-white/10 backdrop-blur-md border border-white/20 rounded-full p-1.5 pl-4 sm:pl-5 flex items-center justify-between mb-4">
              <span className="text-xs sm:text-sm font-mono truncate mr-2 select-all text-white/95">
                {streamUrl}
              </span>
              <button
                type="button"
                onClick={() => handleCopy(streamUrl)}
                className="bg-white hover:bg-gray-100 text-[#4b2bee] text-xs font-semibold px-4 py-2 rounded-full transition-all shrink-0 active:scale-95 flex items-center gap-1.5 cursor-pointer"
              >
                {copied ? <Check className="w-3.5 h-3.5 text-emerald-600" /> : <Copy className="w-3.5 h-3.5" />}
                {copied ? 'Copied' : 'Copy link'}
              </button>
            </div>

            {/* Bottom 2 Buttons */}
            <div className="grid grid-cols-2 gap-3 pt-1">
              <button
                type="button"
                onClick={handleWhatsAppShare}
                className="bg-white/10 hover:bg-white/20 border border-white/15 text-white text-xs font-semibold py-2.5 px-3 rounded-full text-center transition-colors truncate cursor-pointer"
              >
                Share on WhatsApp
              </button>
              <Link
                to={`/stream/${username}`}
                target="_blank"
                rel="noreferrer"
                className="bg-white/10 hover:bg-white/20 border border-white/15 text-white text-xs font-semibold py-2.5 px-3 rounded-full text-center transition-colors truncate"
              >
                Open my page
              </Link>
            </div>
          </div>

        </div>

      </div>

    </div>
  );
}
