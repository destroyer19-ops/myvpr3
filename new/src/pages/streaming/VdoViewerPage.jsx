import { useState, useRef } from 'react';
import { useParams, useSearchParams, Link } from 'react-router-dom';
import { 
  Radio, Volume2, VolumeX, Maximize2, Minimize2, Share2, Heart, 
  Gift, MessageCircle, RefreshCw, Check, Sparkles,
  Lock, MessageSquare
} from 'lucide-react';

export default function VdoViewerPage() {
  const { streamId } = useParams();
  const [searchParams] = useSearchParams();
  
  // Extract password from URL if provided (?pwd=... or ?password=...)
  const urlPassword = searchParams.get('pwd') || searchParams.get('password') || '';
  const [password, setPassword] = useState(urlPassword);
  const [enteredPin, setEnteredPin] = useState('');
  const [showPinModal, setShowPinModal] = useState(false);
  const [isMuted, setIsMuted] = useState(true);
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [copied, setCopied] = useState(false);
  const [iframeKey, setIframeKey] = useState(0);

  const playerContainerRef = useRef(null);
  const iframeRef = useRef(null);

  const cleanStreamId = (streamId || 'live').trim();

  // Clean, beautiful title formatting from Stream ID
  const displayTitle = cleanStreamId
    .replace(/^vpr[_-]/i, '')
    .split(/[_-]/)
    .filter(Boolean)
    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ') || 'Live Congregation';

  // Embed URL for real-time viewer:
  // &mutespeaker bypasses browser autoplay blocking immediately so video plays instantly without black screen
  const pwdParam = password ? `&password=${encodeURIComponent(password)}` : '';
  const embedUrl = `https://vdo.ninja/?view=${encodeURIComponent(cleanStreamId)}${pwdParam}&mutespeaker&cleanoutput&transparent&autostart`;

  // Listen to postMessage responses or unmute triggers
  const handleUnmute = () => {
    try {
      // Send command to un-mute speaker
      iframeRef.current?.contentWindow?.postMessage({
        action: 'speaker',
        value: true
      }, '*');
    } catch (err) {
      console.warn('Unmute message error:', err);
    }
    setIsMuted(false);
  };

  const toggleAudio = () => {
    const nextMuted = !isMuted;
    try {
      iframeRef.current?.contentWindow?.postMessage({
        action: 'speaker',
        value: !nextMuted
      }, '*');
    } catch (err) {
      console.warn('Audio toggle error:', err);
    }
    setIsMuted(nextMuted);
  };

  const toggleFullscreen = () => {
    if (!playerContainerRef.current) return;
    if (!document.fullscreenElement) {
      playerContainerRef.current.requestFullscreen().catch((err) => {
        console.warn('Fullscreen request failed:', err);
      });
      setIsFullscreen(true);
    } else {
      document.exitFullscreen().catch((err) => {
        console.warn('Fullscreen exit failed:', err);
      });
      setIsFullscreen(false);
    }
  };

  const handleShare = () => {
    if (navigator.share) {
      navigator.share({
        title: `${displayTitle} — Live on MyVPR`,
        text: `Watch ${displayTitle} live on MyVPR Praise Platform`,
        url: window.location.href,
      }).catch((err) => {
        console.warn('Native share failed:', err);
      });
    } else {
      navigator.clipboard.writeText(window.location.href);
      setCopied(true);
      setTimeout(() => setCopied(false), 2500);
    }
  };

  const handleWhatsAppShare = () => {
    const text = encodeURIComponent(`Watch ${displayTitle} live on MyVPR: ${window.location.href}`);
    window.open(`https://wa.me/?text=${text}`, '_blank');
  };

  const handlePinSubmit = (e) => {
    e.preventDefault();
    if (enteredPin.trim()) {
      setPassword(enteredPin.trim());
      setShowPinModal(false);
      setIframeKey(prev => prev + 1); // Reload iframe with passcode
    }
  };

  const handleReload = () => {
    setIframeKey(prev => prev + 1);
  };

  return (
    <div className="min-h-screen bg-[#fcfcfd] dark:bg-gray-950 text-gray-950 dark:text-white animate-in fade-in duration-300 pb-28 lg:pb-16">
      
      {/* ── Top Bar Notification ───────────────────────────────────── */}
      <div className="bg-gradient-to-r from-[#5b3ee4] via-[#7c5cf7] to-indigo-600 text-white py-2 px-4 text-center text-xs font-medium shadow-xs">
        <span className="inline-flex items-center gap-1.5">
          <Sparkles className="w-3.5 h-3.5 animate-pulse" />
          Virtual Praise Room Real-time Live Broadcast — Join believers worldwide in praise and worship
        </span>
      </div>

      <div className="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-8">
        
        {/* ── Main Layout: Video Player + Interactive Sidebar ───────── */}
        <div className="grid lg:grid-cols-12 gap-6 lg:gap-8 items-start">
          
          {/* Left: Player + Metadata (8 Cols) */}
          <div className="lg:col-span-8 flex flex-col gap-6">
            
            {/* The Cinema Video Player Container */}
            <div 
              ref={playerContainerRef}
              className="relative w-full aspect-video bg-black rounded-2xl sm:rounded-3xl overflow-hidden shadow-2xl border border-gray-900 flex items-center justify-center group"
            >
              {/* Clean White-labeled Live Stream Embed */}
              <iframe
                key={iframeKey}
                ref={iframeRef}
                src={embedUrl}
                title="MyVPR Live Broadcast"
                className="w-full h-full border-0 object-contain bg-black"
                allow="autoplay; camera; microphone; display-capture; fullscreen; picture-in-picture; web-share"
                allowFullScreen
              />

              {/* Top Gradient Overlay Bar */}
              <div className="absolute top-0 left-0 right-0 p-3 sm:p-5 bg-gradient-to-b from-black/85 via-black/40 to-transparent flex items-center justify-between pointer-events-none z-15">
                <div className="flex items-center gap-2 pointer-events-auto">
                  <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#e11d48] text-white text-xs font-bold shadow-md">
                    <span className="w-2 h-2 rounded-full bg-white animate-pulse" /> LIVE NOW
                  </span>
                  <span className="hidden sm:inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-black/60 backdrop-blur-md text-white/90 text-xs font-medium border border-white/10">
                    <Radio className="w-3 h-3 text-emerald-400" /> Real-time
                  </span>
                </div>

                <div className="flex items-center gap-2 pointer-events-auto">
                  {/* Audio Mute/Unmute Quick Toggle */}
                  <button
                    type="button"
                    onClick={toggleAudio}
                    className={`p-2 rounded-full backdrop-blur-md text-xs transition-colors cursor-pointer border border-white/10 ${
                      isMuted ? 'bg-amber-500/80 hover:bg-amber-500 text-white' : 'bg-black/60 hover:bg-black/80 text-white'
                    }`}
                    title={isMuted ? 'Click to unmute audio' : 'Mute audio'}
                  >
                    {isMuted ? <VolumeX className="w-4 h-4 text-white" /> : <Volume2 className="w-4 h-4" />}
                  </button>

                  {/* Reload Stream Feed */}
                  <button
                    type="button"
                    onClick={handleReload}
                    className="p-2 rounded-full bg-black/60 hover:bg-black/80 backdrop-blur-md text-white text-xs transition-colors cursor-pointer border border-white/10"
                    title="Reload live stream feed"
                  >
                    <RefreshCw className="w-4 h-4" />
                  </button>

                  {/* Fullscreen Button */}
                  <button
                    type="button"
                    onClick={toggleFullscreen}
                    className="p-2 rounded-full bg-black/60 hover:bg-black/80 backdrop-blur-md text-white text-xs transition-colors cursor-pointer border border-white/10"
                    title={isFullscreen ? 'Exit Fullscreen' : 'Fullscreen'}
                  >
                    {isFullscreen ? <Minimize2 className="w-4 h-4" /> : <Maximize2 className="w-4 h-4" />}
                  </button>
                </div>
              </div>

              {/* Bottom Interactive Unmute Banner (Auto-dismisses when unmuted) */}
              {isMuted && (
                <div className="absolute bottom-4 left-4 right-4 sm:left-auto sm:right-auto sm:left-1/2 sm:-translate-x-1/2 z-20 pointer-events-auto animate-in fade-in slide-in-from-bottom-2">
                  <button
                    type="button"
                    onClick={handleUnmute}
                    className="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 bg-gray-950/90 hover:bg-black backdrop-blur-md text-white text-xs sm:text-sm font-semibold px-5 py-2.5 rounded-full border border-white/20 shadow-2xl transition-all active:scale-95 cursor-pointer ring-2 ring-[#5b3ee4]/50 hover:ring-[#5b3ee4]"
                  >
                    <div className="w-6 h-6 rounded-full bg-[#5b3ee4] flex items-center justify-center text-white shrink-0">
                      <Volume2 className="w-3.5 h-3.5" />
                    </div>
                    <span>Live video is playing • Click to unmute audio</span>
                  </button>
                </div>
              )}

              {/* PIN Entry Modal Overlay (For password-protected broadcasts) */}
              {showPinModal && (
                <div className="absolute inset-0 z-30 bg-black/85 backdrop-blur-sm flex items-center justify-center p-4">
                  <div className="bg-gray-900 border border-gray-800 rounded-3xl p-6 sm:p-7 max-w-sm w-full text-center shadow-2xl animate-in zoom-in-95">
                    <div className="w-12 h-12 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-[#5b3ee4] flex items-center justify-center mx-auto mb-3">
                      <Lock className="w-6 h-6" />
                    </div>
                    <h3 className="font-serif font-bold text-lg text-white mb-1">
                      Private Broadcast
                    </h3>
                    <p className="text-xs text-gray-400 mb-4">
                      This broadcast is protected. Please enter the passcode to view the live video.
                    </p>
                    <form onSubmit={handlePinSubmit} className="space-y-3">
                      <input
                        type="text"
                        autoFocus
                        value={enteredPin}
                        onChange={e => setEnteredPin(e.target.value)}
                        placeholder="Enter broadcast passcode"
                        className="w-full px-4 py-2.5 rounded-xl bg-gray-800 border border-gray-700 text-sm text-white placeholder-gray-500 text-center font-mono outline-none focus:ring-2 focus:ring-[#5b3ee4]/40"
                      />
                      <div className="flex gap-2">
                        <button
                          type="button"
                          onClick={() => setShowPinModal(false)}
                          className="flex-1 py-2.5 rounded-xl bg-gray-800 hover:bg-gray-750 text-gray-300 text-xs font-semibold"
                        >
                          Cancel
                        </button>
                        <button
                          type="submit"
                          className="flex-1 py-2.5 rounded-xl bg-[#5b3ee4] hover:bg-[#4f2bee] text-white text-xs font-semibold shadow-md"
                        >
                          Unlock Stream
                        </button>
                      </div>
                    </form>
                  </div>
                </div>
              )}

            </div>

            {/* Broadcast Information & Metadata Bar */}
            <div className="bg-white dark:bg-gray-900 rounded-3xl p-6 sm:p-8 border border-gray-100 dark:border-gray-800 shadow-sm">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-gray-100 dark:border-gray-800">
                <div>
                  <div className="flex items-center gap-2 mb-2">
                    <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-[#5b3ee4] dark:text-[#a5b4fc] text-xs font-bold uppercase tracking-wider">
                      Live Broadcast
                    </span>
                    <span className="text-xs text-gray-400 font-mono">
                      {cleanStreamId}
                    </span>
                  </div>
                  <h1 className="font-serif text-2xl sm:text-3xl font-bold text-gray-950 dark:text-white leading-tight">
                    {displayTitle} Live Worship Service
                  </h1>
                  <p className="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Virtual Praise Room Live Congregation Broadcast
                  </p>
                </div>

                {/* Share Actions */}
                <div className="flex items-center gap-2 shrink-0">
                  <button
                    type="button"
                    onClick={handleShare}
                    className="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 text-xs sm:text-sm font-semibold transition-colors cursor-pointer"
                  >
                    {copied ? <Check className="w-4 h-4 text-emerald-500" /> : <Share2 className="w-4 h-4" />}
                    {copied ? 'Link Copied!' : 'Share Stream'}
                  </button>

                  <button
                    type="button"
                    onClick={handleWhatsAppShare}
                    className="inline-flex items-center gap-2 px-4 py-2.5 rounded-full bg-emerald-50 dark:bg-emerald-950/50 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 text-xs sm:text-sm font-semibold transition-colors cursor-pointer"
                    title="Share on WhatsApp"
                  >
                    <MessageSquare className="w-4 h-4 text-emerald-500" />
                    <span className="hidden sm:inline">WhatsApp</span>
                  </button>
                </div>
              </div>

              {/* Service Description */}
              <div className="pt-6">
                <h3 className="font-serif font-bold text-base text-gray-950 dark:text-white mb-2">
                  About This Broadcast
                </h3>
                <p className="text-gray-600 dark:text-gray-300 text-sm leading-relaxed">
                  Join in prayers, praises, and powerful ministrations in the presence of the Lord. 
                  Experience unbroken communion with believers across nations.
                </p>
              </div>
            </div>

          </div>

          {/* Right: Ministry Action Hub (4 Cols) */}
          <div className="lg:col-span-4 flex flex-col gap-6">
            
            {/* Quick Giving & Offerings Card */}
            <div className="bg-gradient-to-br from-pink-500/10 via-pink-50/50 to-transparent dark:from-pink-950/30 dark:via-gray-900 dark:to-gray-900 rounded-3xl p-6 border border-pink-100 dark:border-pink-950/60 shadow-sm">
              <div className="w-11 h-11 rounded-2xl bg-pink-100 dark:bg-pink-900/60 text-[#e11d48] flex items-center justify-center mb-3">
                <Gift className="w-6 h-6" />
              </div>
              <h3 className="font-serif font-bold text-xl text-gray-950 dark:text-white mb-1">
                Tithe & Offerings
              </h3>
              <p className="text-xs text-gray-600 dark:text-gray-400 mb-4 leading-relaxed">
                Support the preaching of the gospel and worship sessions with your gift today.
              </p>
              <Link
                to="/give"
                className="inline-flex items-center justify-center gap-2 w-full py-3 bg-[#e11d48] hover:bg-[#be123c] text-white font-semibold rounded-2xl text-xs sm:text-sm shadow-md shadow-pink-500/20 transition-all active:scale-95"
              >
                <Heart className="w-4 h-4 fill-white" /> Give an Offering
              </Link>
            </div>

            {/* Salvation Decision Card */}
            <div className="bg-gradient-to-br from-indigo-500/10 via-indigo-50/50 to-transparent dark:from-indigo-950/30 dark:via-gray-900 dark:to-gray-900 rounded-3xl p-6 border border-indigo-100 dark:border-indigo-950/60 shadow-sm">
              <div className="w-11 h-11 rounded-2xl bg-indigo-100 dark:bg-indigo-900/60 text-[#5b3ee4] flex items-center justify-center mb-3">
                <Heart className="w-6 h-6" />
              </div>
              <h3 className="font-serif font-bold text-xl text-gray-950 dark:text-white mb-1">
                Give Your Heart to Christ
              </h3>
              <p className="text-xs text-gray-600 dark:text-gray-400 mb-4 leading-relaxed">
                If you made a decision for Jesus during this service, let us pray with you and send you free resources.
              </p>
              <Link
                to="/salvation"
                className="inline-flex items-center justify-center gap-2 w-full py-3 bg-[#5b3ee4] hover:bg-[#4f2bee] text-white font-semibold rounded-2xl text-xs sm:text-sm shadow-md shadow-indigo-500/20 transition-all active:scale-95"
              >
                <Sparkles className="w-4 h-4" /> I Accepted Jesus Today
              </Link>
            </div>

            {/* Prayer Request Card */}
            <div className="bg-white dark:bg-gray-900 rounded-3xl p-6 border border-gray-100 dark:border-gray-800 shadow-sm">
              <div className="w-11 h-11 rounded-2xl bg-teal-50 dark:bg-teal-950/60 text-teal-700 dark:text-teal-400 flex items-center justify-center mb-3">
                <MessageCircle className="w-6 h-6" />
              </div>
              <h3 className="font-serif font-bold text-lg text-gray-950 dark:text-white mb-1">
                Prayer Request
              </h3>
              <p className="text-xs text-gray-500 dark:text-gray-400 mb-4 leading-relaxed">
                Need prayer for healing, breakthrough, or family? Submit your prayer request to our ministers.
              </p>
              <Link
                to="/contact"
                className="inline-flex items-center justify-center gap-2 w-full py-2.5 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 font-medium rounded-xl text-xs transition-colors"
              >
                Send Request to Ministers
              </Link>
            </div>

          </div>

        </div>

      </div>
    </div>
  );
}
