import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { Loader2, ArrowLeft, Users, AlertTriangle, Heart, HandCoins, X, Copy, Check, StopCircle, Clock, Globe } from 'lucide-react';
import { crusadesApi, request } from '../services/api';
import { useAuth } from '@/context/AuthContext';
import { COUNTRIES } from '../constants/countries';

export default function CrusadeRoomPage() {
  const { user } = useAuth();
  const { code } = useParams();
  const [loading, setLoading] = useState(true);
  const [crusade, setCrusade] = useState(null);
  const [error, setError] = useState('');
  const [showGiveLife, setShowGiveLife] = useState(false);
  const [showOfferings, setShowOfferings] = useState(false);
  const [copied, setCopied] = useState(false);

  const getVisitorSessionId = () => {
    let sid = localStorage.getItem('vpr_visitor_session_id');
    if (!sid) {
      sid = 'vsession_' + Math.random().toString(36).substr(2, 9) + Date.now().toString(36);
      localStorage.setItem('vpr_visitor_session_id', sid);
    }
    return sid;
  };

  const [showVisitorModal, setShowVisitorModal] = useState(false);
  const [visitorForm, setVisitorForm] = useState({ name: '', email: '', country: '' });
  const [identifying, setIdentifying] = useState(false);

  // Share / Go Live state
  const [shareData, setShareData] = useState(null);   // { stream_key, share_url }
  const [sharing, setSharing] = useState(false);
  const [shareLoading, setShareLoading] = useState(false);
  const [shareError, setShareError] = useState('');

  useEffect(() => {
    async function fetchCrusade() {
      setLoading(true);
      try {
        const res = await crusadesApi.getById(code);
        if (res.success) {
          setCrusade(res.data);
          
          // Tracking
          const sid = getVisitorSessionId();
          request(`/crusades/${code}/track`, { method: 'POST', body: JSON.stringify({ visitor_session_id: sid }) }).catch(() => {});
          
          const timer = setTimeout(() => {
            request(`/crusades/${code}/track`, { 
              method: 'POST', 
              body: JSON.stringify({ stayed_for_a_minute: true, visitor_session_id: sid }) 
            }).catch(() => {});
          }, 60000);
          
          const modalTimer = setTimeout(() => {
            if (!localStorage.getItem(`identified_${code}`) && res.data.status === 'live') {
              setShowVisitorModal(true);
            }
          }, 120000);
          
          return () => { clearTimeout(timer); clearTimeout(modalTimer); };
        } else {
          setError(res.message || 'Crusade not found.');
        }
      } catch (err) {
        setError('Failed to fetch crusade details.');
        console.error(err);
      } finally {
        setLoading(false);
      }
    }
    fetchCrusade();
  }, [code]);

  // --- Share / Go Live Handlers ---
  const handleGoLive = async () => {
    setShareLoading(true);
    setShareError('');
    try {
      const res = await request(`/crusades/${code}/share`, { method: 'POST' });
      if (res.success) {
        setShareData({ stream_key: res.stream_key, share_url: res.share_url });
        setSharing(true);
      } else {
        setShareError(res.message || 'Failed to generate stream key.');
      }
    } catch (err) {
      setShareError(err.message || 'An error occurred. Do you have an active subscription?');
    } finally {
      setShareLoading(false);
    }
  };

  const handleEndShare = async () => {
    if (!shareData?.stream_key) return;
    if (!window.confirm('Stop sharing your stream to this crusade?')) return;
    try {
      await request('/shared/end', { method: 'POST', body: JSON.stringify({ stream_key: shareData.stream_key }) });
    } catch (_) { /* swallow */ }
    setSharing(false);
    setShareData(null);
    setShowStreamKey(false);
  };


  if (loading) {
    return (
      <div className="flex flex-col justify-center items-center min-h-[70vh]">
        <Loader2 className="w-10 h-10 animate-spin text-[#5b3ee4] mb-4" />
        <p className="text-gray-500 dark:text-gray-400 font-medium text-sm">Entering crusade room...</p>
      </div>
    );
  }

  if (error || !crusade) {
    return (
      <div className="container max-w-4xl mx-auto px-4 py-16 text-center">
        <div className="bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-900/30 p-8 rounded-3xl inline-block max-w-md">
          <AlertTriangle className="w-12 h-12 text-rose-500 mx-auto mb-4" />
          <h2 className="text-xl font-bold text-rose-700 dark:text-rose-400 mb-2">Unavailable</h2>
          <p className="text-rose-600 dark:text-rose-300 text-sm mb-6">{error || 'Crusade not found.'}</p>
          <Link to="/dashboard" className="inline-flex items-center gap-2 text-sm font-semibold text-[#5b3ee4] hover:underline">
            <ArrowLeft className="w-4 h-4" /> Return to Dashboard
          </Link>
        </div>
      </div>
    );
  }

  const isYouTube = crusade.resolved_video_url || crusade.video_url?.includes('youtube.com') || crusade.resolved_video_url || crusade.video_url?.includes('youtu.be');
  let youtubeEmbedUrl = '';
  if (isYouTube) {
    let videoId = '';
    if (crusade.resolved_video_url || crusade.video_url.includes('youtube.com/watch')) {
      const urlParams = new URLSearchParams(crusade.resolved_video_url || crusade.video_url.split('?')[1]);
      videoId = urlParams.get('v');
    } else if (crusade.resolved_video_url || crusade.video_url.includes('youtu.be/')) {
      videoId = crusade.resolved_video_url || crusade.video_url.split('youtu.be/')[1].split('?')[0];
    }
    youtubeEmbedUrl = `https://www.youtube.com/embed/${videoId}?autoplay=1`;
  }

  // Determine status
  const status = crusade.status || 'live';
  const isLive = status === 'live';
  const isScheduled = status === 'scheduled';
  const isEnded = status === 'ended';
  const startTime = crusade.startTime ? new Date(crusade.startTime) : null;

  return (
    <div className="container max-w-6xl mx-auto px-4 py-6 pb-24 md:pb-8">
      {showVisitorModal && (
        <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in">
          <div className="bg-white dark:bg-[#111827] border border-gray-100 dark:border-gray-800 rounded-3xl w-full max-w-md shadow-2xl overflow-hidden relative">
            <div className="bg-gradient-to-r from-purple-500/10 to-indigo-500/10 p-6 border-b border-gray-100 dark:border-gray-800">
              <button
                onClick={() => { setShowVisitorModal(false); localStorage.setItem(`identified_${code}`, 'true'); }}
                className="absolute top-4 right-4 text-gray-400 hover:text-gray-600 dark:hover:text-white p-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
              >
                <X className="w-5 h-5" />
              </button>
              <div className="flex items-center gap-3 mb-1">
                <div className="w-9 h-9 rounded-full bg-[#5b3ee4]/10 text-[#5b3ee4] flex items-center justify-center">
                  <Globe className="w-4 h-4" />
                </div>
                <h3 className="text-lg font-bold text-gray-900 dark:text-white">We're glad you're here!</h3>
              </div>
              <p className="text-gray-500 dark:text-gray-400 text-xs">We'd love to know who is joining us today. This is completely optional.</p>
            </div>
            <form onSubmit={async (e) => {
              e.preventDefault();
              setIdentifying(true);
              await request(`/crusades/${code}/track/identify`, {
                method: 'PATCH',
                body: JSON.stringify({ ...visitorForm, visitor_session_id: getVisitorSessionId() })
              }).catch(() => {});
              setIdentifying(false);
              setShowVisitorModal(false);
              localStorage.setItem(`identified_${code}`, 'true');
            }} className="p-6 space-y-4">
              <div>
                <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Your Name <span className="text-rose-500">*</span></label>
                <input
                  type="text" value={visitorForm.name}
                  onChange={e => setVisitorForm({ ...visitorForm, name: e.target.value })}
                  className="w-full bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white px-4 py-2.5 rounded-xl outline-none focus:border-[#5b3ee4] focus:ring-1 focus:ring-[#5b3ee4] transition-all text-sm"
                  placeholder="e.g. John Doe" required
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Email <span className="text-gray-400 font-normal">(optional)</span></label>
                <input
                  type="email" value={visitorForm.email}
                  onChange={e => setVisitorForm({ ...visitorForm, email: e.target.value })}
                  className="w-full bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white px-4 py-2.5 rounded-xl outline-none focus:border-[#5b3ee4] focus:ring-1 focus:ring-[#5b3ee4] transition-all text-sm"
                  placeholder="your@email.com"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Country <span className="text-gray-400 font-normal">(optional)</span></label>
                <select
                  value={visitorForm.country}
                  onChange={e => setVisitorForm({ ...visitorForm, country: e.target.value })}
                  className="w-full bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white px-4 py-2.5 rounded-xl outline-none focus:border-[#5b3ee4] focus:ring-1 focus:ring-[#5b3ee4] transition-all text-sm"
                >
                  <option value="">Select your country...</option>
                  {COUNTRIES.map(c => (
                    <option key={c.code} value={c.code}>{c.name}</option>
                  ))}
                </select>
              </div>
              <div className="pt-2 flex gap-3">
                <button
                  type="button"
                  onClick={() => { setShowVisitorModal(false); localStorage.setItem(`identified_${code}`, 'true'); }}
                  className="flex-1 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 px-4 py-2.5 rounded-full font-semibold text-xs transition-colors"
                >
                  Continue Watching
                </button>
                <button
                  type="submit" disabled={identifying}
                  className="flex-1 bg-[#5b3ee4] hover:bg-[#4b2ee0] text-white px-4 py-2.5 rounded-full font-bold text-xs shadow-md shadow-purple-500/20 transition-all flex items-center justify-center gap-2"
                >
                  {identifying ? <Loader2 className="w-4 h-4 animate-spin" /> : 'Submit'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
      
      <Link to="/dashboard" className="inline-flex items-center text-sm font-medium text-gray-500 hover:text-[#5b3ee4] mb-4 transition-colors">
        <ArrowLeft className="w-4 h-4 mr-2" />
        Exit Room
      </Link>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main Video Area */}
        <div className="lg:col-span-2 flex flex-col gap-6">
          <div className="bg-black rounded-3xl overflow-hidden aspect-video shadow-xl relative border border-black/10 dark:border-gray-800">
            {isScheduled ? (
              <div className="absolute inset-0 flex flex-col items-center justify-center bg-gray-950 text-white p-6 text-center">
                <CalendarCountdown crusade={crusade} startTime={startTime} code={code} />
              </div>
            ) : isLive ? (
              isYouTube ? (
                <iframe
                  src={youtubeEmbedUrl}
                  title={crusade.title}
                  className="w-full h-full"
                  frameBorder="0"
                  allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                  allowFullScreen
                ></iframe>
              ) : (
                <video
                  controls
                  autoPlay
                  className="w-full h-full"
                  src={crusade.resolved_video_url || crusade.video_url}
                >
                  Your browser does not support the video tag.
                </video>
              )
            ) : (
              <div className="absolute inset-0 flex flex-col items-center justify-center bg-gray-950 text-white p-6 text-center">
                <AlertTriangle className="w-16 h-16 mb-4 text-gray-600" />
                <h2 className="text-2xl font-bold mb-2">Event Concluded</h2>
                <p className="text-gray-400">This stream has ended. Thank you for joining.</p>
              </div>
            )}
          </div>

          <div className="bg-white dark:bg-[#111827] border border-gray-100 dark:border-gray-800 p-6 sm:p-8 rounded-3xl shadow-sm">
            <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-4">
              <div>
                <h1 className="font-serif text-2xl sm:text-3xl font-normal text-gray-900 dark:text-white tracking-tight mb-3">
                  {crusade.title}
                </h1>
                <div className="flex flex-wrap items-center gap-2.5">
                  {isLive && (
                    <span className="flex items-center gap-1.5 bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-400 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider">
                      <span className="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                      Live Now
                    </span>
                  )}
                  <span className="bg-purple-50 text-[#5b3ee4] dark:bg-purple-950/40 dark:text-purple-300 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider">
                    Code: {crusade.crusade_code || crusade.code}
                  </span>
                </div>
              </div>
            </div>

            {crusade.description && (
              <p className="text-gray-600 dark:text-gray-300 text-sm leading-relaxed whitespace-pre-wrap mt-2">{crusade.description}</p>
            )}

            {/* Action Buttons */}
            <div className="mt-8 pt-6 border-t border-gray-100 dark:border-gray-800 flex flex-wrap gap-3">
              <button
                onClick={() => setShowGiveLife(true)}
                className="flex items-center gap-2 bg-[#5b3ee4] hover:bg-[#4b2ee0] text-white text-xs sm:text-sm font-semibold py-2.5 px-5 rounded-full shadow-sm shadow-purple-500/20 transition-all hover:scale-[1.02]"
              >
                <Heart className="w-4 h-4 fill-white" /> Give Your Life to Christ
              </button>
              <button
                onClick={() => setShowOfferings(true)}
                className="flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-semibold py-2.5 px-5 rounded-full shadow-sm transition-all hover:scale-[1.02]"
              >
                <HandCoins className="w-4 h-4" /> Give Offerings &amp; Partnership
              </button>
              <button
                onClick={() => {
                  navigator.clipboard.writeText(window.location.href);
                  setCopied(true);
                  setTimeout(() => setCopied(false), 2000);
                }}
                className="flex items-center gap-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200 text-xs sm:text-sm font-semibold py-2.5 px-5 rounded-full transition-colors"
              >
                {copied ? <Check className="w-4 h-4 text-emerald-500" /> : <Copy className="w-4 h-4" />}
                {copied ? 'Link Copied!' : 'Share Page Link'}
              </button>

              {/* Watch Party button - Host Only */}
              {user?.id == crusade?.user_id && (
                !sharing ? (
                  <button
                    onClick={handleGoLive}
                    disabled={shareLoading}
                    className="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-xs sm:text-sm font-semibold py-2.5 px-5 rounded-full transition-colors"
                  >
                    {shareLoading
                      ? <Loader2 className="w-4 h-4 animate-spin" />
                      : <Users className="w-4 h-4" />
                    }
                    {shareLoading ? 'Generating Link...' : 'Generate Watch Party Link'}
                  </button>
                ) : (
                  <button
                    onClick={handleEndShare}
                    className="flex items-center gap-2 bg-gray-700 hover:bg-gray-600 text-white text-xs sm:text-sm font-semibold py-2.5 px-5 rounded-full transition-colors"
                  >
                    <StopCircle className="w-4 h-4" /> Disable Watch Party Link
                  </button>
                )
              )}
            </div>

            {shareError && (
              <p className="mt-3 text-rose-500 text-xs">{shareError}</p>
            )}

            {/* Watch Party Link Panel */}
            {sharing && shareData && (
              <div className="mt-6 bg-purple-50/50 dark:bg-[#0c0d24] border border-purple-200 dark:border-purple-800/40 rounded-2xl p-5 space-y-3">
                <div className="flex items-center gap-2">
                  <h4 className="font-bold text-[#5b3ee4] dark:text-purple-300 text-xs uppercase tracking-wider">Your Watch Party is Active</h4>
                </div>
                <p className="text-gray-600 dark:text-gray-400 text-xs">Share this unique link with others so they can join your watch party.</p>

                <div className="flex">
                  <input
                    readOnly
                    value={shareData.share_url}
                    className="flex-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-800 dark:text-gray-200 text-xs font-mono px-3 py-2.5 rounded-l-xl outline-none"
                  />
                  <button
                    onClick={() => { 
                      navigator.clipboard.writeText(shareData.share_url);
                      alert('Watch Party Link Copied!');
                    }}
                    className="bg-[#5b3ee4] hover:bg-[#4b2ee0] text-white text-xs px-4 rounded-r-xl transition-colors flex items-center gap-1.5 font-semibold"
                  >
                    <Copy className="w-3.5 h-3.5" /> Copy Link
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>

        {/* Sidebar / Chat */}
        <div className="lg:col-span-1 flex flex-col gap-4">
          <div className="bg-white dark:bg-[#111827] border border-gray-100 dark:border-gray-800 rounded-3xl flex flex-col h-[480px] lg:h-auto lg:flex-1 shadow-sm overflow-hidden">
            <div className="p-4 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center bg-gray-50/50 dark:bg-gray-900/30">
              <h3 className="font-semibold text-sm text-gray-900 dark:text-white flex items-center gap-2">
                <Users className="w-4 h-4 text-[#5b3ee4]" /> Live Chat
              </h3>
            </div>
            <div className="flex-1 p-6 flex items-center justify-center text-center text-gray-400 dark:text-gray-500 text-xs">
              <p>Live chat &amp; community interactions<br/>are active during live sessions.</p>
            </div>
            <div className="p-4 border-t border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/30">
              <input
                type="text"
                placeholder="Say something..."
                disabled
                className="w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-4 py-2.5 rounded-full text-xs text-gray-400 dark:text-gray-500 cursor-not-allowed outline-none"
              />
            </div>
          </div>
        </div>
      </div>

      {/* Give Your Life to Christ Modal */}
      {showGiveLife && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in">
          <div className="bg-white dark:bg-[#111827] border border-gray-100 dark:border-gray-800 rounded-3xl max-w-2xl w-full shadow-2xl overflow-y-auto max-h-[90vh]">
            <div className="flex items-center justify-between p-6 border-b border-gray-100 dark:border-gray-800">
              <h3 className="font-serif text-xl font-normal text-gray-900 dark:text-white flex items-center gap-2">
                <Heart className="w-5 h-5 text-rose-500 fill-rose-500" /> Give Your Life to Christ
              </h3>
              <button onClick={() => setShowGiveLife(false)} className="text-gray-400 hover:text-gray-600 dark:hover:text-white transition-colors p-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800">
                <X className="w-5 h-5" />
              </button>
            </div>
            <div className="p-6 space-y-6 text-center">
              <p className="text-gray-700 dark:text-gray-300 text-base leading-relaxed">
                We invite you to make Jesus Christ the Lord of your life by praying this simple prayer now!
              </p>
              <div className="rounded-2xl overflow-hidden bg-black aspect-video shadow-md">
                <video controls className="w-full h-full">
                  <source src="https://worshiplyrics.s3.amazonaws.com/filler.mp4" type="video/mp4" />
                  Your browser does not support the video tag.
                </video>
              </div>
              <div className="bg-purple-50 dark:bg-purple-950/20 border border-purple-200 dark:border-purple-800/30 rounded-2xl p-6 text-left">
                <p className="text-[#5b3ee4] dark:text-purple-300 leading-relaxed italic text-sm font-serif">
                  "O Lord God, I believe with all my heart in Jesus Christ, Son of the living God. I believe He died for me and God raised Him from the dead. I believe He's alive today. I confess with my mouth that Jesus Christ is the Lord of my life from this day. Through Him and in His Name, I have eternal life; I'm born again. Thank you Lord, for saving my soul! I'm now a child of God. Hallelujah!"
                </p>
              </div>
              <p className="text-gray-700 dark:text-gray-300 text-sm">
                Congratulations! You are now a child of God. We want to send you a ministry resources gift pack to help you grow as a Christian.
              </p>
              <p className="text-gray-500 dark:text-gray-400 text-xs">
                Kindly click the button below to record your decision and get your gift pack.
              </p>
              <a
                href="/salvation"
                className="inline-flex items-center gap-2 bg-[#5b3ee4] hover:bg-[#4b2ee0] text-white font-semibold text-sm py-3 px-8 rounded-full shadow-md shadow-purple-500/20 transition-all hover:scale-105"
              >
                <Heart className="w-4 h-4 fill-white" /> If you said this prayer we have a gift for you
              </a>
            </div>
            <div className="p-4 border-t border-gray-100 dark:border-gray-800 flex justify-end">
              <button onClick={() => setShowGiveLife(false)} className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white text-xs font-semibold px-4 py-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">Close</button>
            </div>
          </div>
        </div>
      )}

      {/* Give Offerings & Partnership Modal */}
      {showOfferings && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in">
          <div className="bg-white dark:bg-[#111827] border border-gray-100 dark:border-gray-800 rounded-3xl max-w-md w-full shadow-2xl overflow-hidden">
            <div className="flex items-center justify-between p-6 border-b border-gray-100 dark:border-gray-800">
              <h3 className="font-serif text-lg font-normal text-gray-900 dark:text-white flex items-center gap-2">
                <HandCoins className="w-5 h-5 text-emerald-600" /> Give Offerings &amp; Partnership
              </h3>
              <button onClick={() => setShowOfferings(false)} className="text-gray-400 hover:text-gray-600 dark:hover:text-white transition-colors p-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800">
                <X className="w-5 h-5" />
              </button>
            </div>
            <div className="p-6 space-y-4">
              <p className="text-gray-600 dark:text-gray-300 text-center text-xs leading-relaxed">
                Thank you for your interest in supporting our ministry. Please use the details below to give your offerings or partner with us.
              </p>
              <div className="bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700/60 rounded-2xl p-5">
                <h4 className="font-bold text-gray-900 dark:text-white mb-3 text-xs uppercase tracking-wider">Bank Account Details</h4>
                <div className="space-y-2 text-xs">
                  <div className="flex justify-between"><span className="text-gray-500 dark:text-gray-400">Account No:</span><span className="text-gray-900 dark:text-white font-mono font-bold">1000316347</span></div>
                  <div className="flex justify-between"><span className="text-gray-500 dark:text-gray-400">Account Name:</span><span className="text-gray-900 dark:text-white font-medium">LMAM - Music App</span></div>
                  <div className="flex justify-between"><span className="text-gray-500 dark:text-gray-400">Bank:</span><span className="text-gray-900 dark:text-white font-medium">Parallex Bank</span></div>
                </div>
              </div>
              <div className="bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700/60 rounded-2xl p-5 text-center">
                <h4 className="font-bold text-gray-900 dark:text-white mb-2 text-xs uppercase tracking-wider">Espees Merchant Code</h4>
                <p className="text-emerald-600 dark:text-emerald-400 font-mono font-bold text-2xl">LMM01</p>
              </div>
              <p className="text-gray-500 dark:text-gray-400 text-xs text-center">
                After giving, please send proof of transaction to{' '}
                <a href="mailto:finance@virtualpraiseroom.com" className="text-[#5b3ee4] hover:underline font-medium">finance@virtualpraiseroom.com</a>
              </p>
            </div>
            <div className="p-4 border-t border-gray-100 dark:border-gray-800 flex justify-end">
              <button onClick={() => setShowOfferings(false)} className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white text-xs font-semibold px-4 py-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">Close</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

// ─── CalendarCountdown ────────────────────────────────────────────────────────
function CalendarCountdown({ crusade, startTime, code }) {
  const [countdown, setCountdown] = useState('');
  const [parts, setParts] = useState({ d: 0, h: 0, m: 0, s: 0 });
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    function tick() {
      const now = new Date();
      const diff = startTime - now;
      if (diff <= 0) {
        setCountdown('Starting now...');
        window.location.reload(); // Refresh to get live status from API
        return;
      }
      const d = Math.floor(diff / 86400000);
      const h = Math.floor((diff % 86400000) / 3600000);
      const m = Math.floor((diff % 3600000) / 60000);
      const s = Math.floor((diff % 60000) / 1000);
      setParts({ d, h, m, s });
      setCountdown(`${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`);
    }
    tick();
    const iv = setInterval(tick, 1000);
    return () => clearInterval(iv);
  }, [startTime]);

  const shareUrl = `${window.location.origin}/crusade/${code}`;

  const copyLink = () => {
    navigator.clipboard.writeText(shareUrl).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    });
  };

  return (
    <div className="flex flex-col items-center justify-center w-full h-full gap-6 px-4">
      <div className="flex items-center gap-2 text-purple-400">
        <Clock className="w-5 h-5" />
        <span className="text-xs font-semibold uppercase tracking-widest">Upcoming Event</span>
      </div>

      <div>
        <h2 className="font-serif text-2xl md:text-3xl font-normal text-white text-center leading-tight mb-2">
          {crusade.title}
        </h2>
        <p className="text-gray-400 text-xs sm:text-sm text-center">
          Scheduled for {startTime.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })} at {startTime.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })}
        </p>
      </div>

      {/* Countdown blocks */}
      <div className="flex items-center gap-3">
        {parts.d > 0 && (
          <div className="flex flex-col items-center bg-white/10 backdrop-blur-sm rounded-2xl px-4 py-3 min-w-[64px]">
            <span className="text-3xl font-bold text-white font-mono">{parts.d}</span>
            <span className="text-[10px] text-gray-400 uppercase tracking-wider">Days</span>
          </div>
        )}
        <div className="flex flex-col items-center bg-white/10 backdrop-blur-sm rounded-2xl px-4 py-3 min-w-[64px]">
          <span className="text-3xl font-bold text-white font-mono">{String(parts.h).padStart(2,'0')}</span>
          <span className="text-[10px] text-gray-400 uppercase tracking-wider">Hours</span>
        </div>
        <div className="flex flex-col items-center bg-white/10 backdrop-blur-sm rounded-2xl px-4 py-3 min-w-[64px]">
          <span className="text-3xl font-bold text-white font-mono">{String(parts.m).padStart(2,'0')}</span>
          <span className="text-[10px] text-gray-400 uppercase tracking-wider">Mins</span>
        </div>
        <div className="flex flex-col items-center bg-[#5b3ee4] rounded-2xl px-4 py-3 min-w-[64px] shadow-lg shadow-purple-500/30">
          <span className="text-3xl font-bold text-white font-mono">{String(parts.s).padStart(2,'0')}</span>
          <span className="text-[10px] text-purple-200 uppercase tracking-wider">Secs</span>
        </div>
      </div>

      {crusade.description && (
        <p className="text-gray-400 text-sm max-w-sm text-center line-clamp-2">{crusade.description}</p>
      )}

      {/* Copy link */}
      <button
        onClick={copyLink}
        className="flex items-center gap-2 bg-white/10 hover:bg-white/20 text-white text-sm font-semibold px-5 py-2.5 rounded-full transition-colors"
      >
        {copied ? <Check className="w-4 h-4 text-green-400" /> : <Copy className="w-4 h-4" />}
        {copied ? 'Link Copied!' : 'Share This Event'}
      </button>
    </div>
  );
}
