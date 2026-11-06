import { request } from "../../services/api";
import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { 
  ArrowLeft, Copy, Eye, EyeOff, AlertTriangle, 
  RefreshCw, CheckCircle2, Server, Key, Radio,
  Sliders, Users, ExternalLink, Check, ChevronDown, ChevronUp
} from 'lucide-react';

export default function StreamingSetupPage() {
  const [channel, setChannel] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [showKey, setShowKey] = useState(false);
  const [copiedUrl, setCopiedUrl] = useState(false);
  const [copiedKey, setCopiedKey] = useState(false);
  const [resetting, setResetting] = useState(false);
  const navigate = useNavigate();

  // Encoder Guide State (1080p vs 720p)
  const [encoderPreset, setEncoderPreset] = useState('1080p');

  // VDO.Ninja Remote Guest State
  const [guestName, setGuestName] = useState('');
  const [guestQuality, setGuestQuality] = useState('1080p');
  const [guestToken, setGuestToken] = useState(() => Math.random().toString(36).substring(2, 10));
  const [guestPassword, setGuestPassword] = useState(() => Math.random().toString(36).substring(2, 12));
  const [copiedGuestUrl, setCopiedGuestUrl] = useState(false);
  const [copiedObsUrl, setCopiedObsUrl] = useState(false);
  const [showObsInstructions, setShowObsInstructions] = useState(false);

  useEffect(() => {
    async function fetchStatus() {
      try {
        const json = await request('/streaming/status');
        if (json.success && json.data.has_active_plan && json.data.channel) {
          setChannel(json.data.channel);
        } else {
          navigate('/dashboard/streaming');
        }
      } catch (e) {
        console.error('Failed to fetch streaming status', e);
        setError(e.message || 'Failed to fetch streaming status.');
      } finally {
        setLoading(false);
      }
    }
    fetchStatus();
  }, [navigate]);

  const handleCopy = (text, type) => {
    navigator.clipboard.writeText(text);
    if (type === 'url') {
      setCopiedUrl(true);
      setTimeout(() => setCopiedUrl(false), 2000);
    } else if (type === 'key') {
      setCopiedKey(true);
      setTimeout(() => setCopiedKey(false), 2000);
    } else if (type === 'guest') {
      setCopiedGuestUrl(true);
      setTimeout(() => setCopiedGuestUrl(false), 2000);
    } else if (type === 'obs') {
      setCopiedObsUrl(true);
      setTimeout(() => setCopiedObsUrl(false), 2000);
    }
  };

  const regenerateGuestSession = () => {
    setGuestToken(Math.random().toString(36).substring(2, 10));
    setGuestPassword(Math.random().toString(36).substring(2, 12));
  };

  const resetStreamKey = async () => {
    if (!window.confirm('Are you sure? Any active broadcasts using the current key will be stopped immediately.')) {
      return;
    }
    
    setResetting(true);
    try {
      const json = await request('/streaming/channel/reset-key', { method: 'POST' });
      if (json.success) {
        setChannel(prev => ({ ...prev, aws_stream_key_arn: json.new_key }));
      } else {
        alert(json.message || 'Failed to reset stream key.');
      }
    } catch (e) {
      alert('Error resetting stream key.');
      console.error(e);
    } finally {
      setResetting(false);
    }
  };

  if (loading) {
    return (
      <div className="min-h-[70vh] flex flex-col items-center justify-center p-12 bg-[var(--bg-canvas)]">
        <div className="w-12 h-12 border-4 border-gray-200 dark:border-gray-700 border-t-gray-900 dark:border-t-white rounded-full animate-spin mb-4"></div>
        <p className="text-gray-500 dark:text-gray-400 font-medium">Loading credentials...</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-[70vh] flex flex-col items-center justify-center p-12 text-center bg-[var(--bg-canvas)]">
        <div className="w-16 h-16 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mb-6">
          <AlertTriangle className="w-8 h-8 text-red-600 dark:text-red-400" />
        </div>
        <h2 className="text-2xl font-bold text-gray-900 dark:text-white mb-2">Error Loading Stream</h2>
        <p className="text-gray-500 dark:text-gray-400 mb-8 max-w-md">{error}</p>
        <button
          onClick={() => navigate('/login')}
          className="px-8 py-3 bg-gray-900 hover:bg-black dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100 text-white font-semibold rounded-full transition-all"
        >
          Sign in again
        </button>
      </div>
    );
  }

  if (!channel) return null; // handled by useEffect redirect

  const streamUrl = `rtmps://${channel.ingest_endpoint}:443/app/`;
  const streamKey = channel.aws_stream_key_arn || 'sk_live_1234567890abcdef';

  // Construct secure VDO.Ninja URLs
  const streamId = `vpr_${guestToken}`;
  const guestLabel = guestName.trim() ? encodeURIComponent(guestName.trim()) : 'RemoteGuest';
  const vdoQualityParam = guestQuality === '1080p' ? '0' : '1';
  const vdoBitrate = guestQuality === '1080p' ? '3500' : '2000';
  
  const guestInviteUrl = `https://vdo.ninja/?push=${streamId}&password=${guestPassword}&label=${guestLabel}&quality=${vdoQualityParam}&bitrate=${vdoBitrate}&codec=h264&webcam&autostart`;
  const obsBrowserSourceUrl = `https://vdo.ninja/?view=${streamId}&password=${guestPassword}&cleanoutput&transparent&scale=${guestQuality === '1080p' ? '1080' : '720'}`;

  return (
    <div className="min-h-screen bg-[var(--bg-canvas)] pt-24 pb-20">
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        {/* Header */}
        <div className="mb-10">
          <button 
            onClick={() => navigate('/dashboard/streaming')}
            className="inline-flex items-center gap-2 text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors mb-6"
          >
            <ArrowLeft className="w-4 h-4" /> Back to Studio
          </button>
          <h1 className="text-3xl md:text-4xl font-extrabold text-gray-950 dark:text-white tracking-tight mb-3">
            Software Setup (OBS / vMix)
          </h1>
          <p className="text-lg text-gray-600 dark:text-gray-400">
            Configure professional broadcasting software like OBS Studio or vMix with genuine 1080p settings and optional remote guest contribution.
          </p>
        </div>

        <div className="space-y-6">
          
          {/* Step 1: Destination / Server */}
          <div className="bg-white dark:bg-gray-900 rounded-3xl p-8 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-gray-100 dark:border-gray-800 relative overflow-hidden">
             <div className="absolute top-0 right-0 p-8 opacity-5 pointer-events-none text-gray-900 dark:text-white">
                <Server className="w-32 h-32" />
             </div>
             
             <div className="flex items-center gap-4 mb-6 relative z-10">
                <div className="w-10 h-10 bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400 rounded-full flex items-center justify-center font-bold text-lg">1</div>
                <div>
                  <h3 className="text-xl font-bold text-gray-950 dark:text-white">Stream URL (Ingest Server)</h3>
                  <p className="text-sm text-gray-500 dark:text-gray-400">The destination address for your broadcast.</p>
                </div>
             </div>

             <div className="relative z-10">
                <div className="flex bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl overflow-hidden focus-within:ring-2 focus-within:ring-[var(--color-brand-accent)] focus-within:border-[var(--color-brand-accent)] transition-all">
                  <input 
                    type="text"
                    value={streamUrl} 
                    readOnly 
                    className="flex-1 bg-transparent px-5 py-4 font-mono text-sm text-gray-800 dark:text-gray-200 outline-none w-full"
                  />
                  <button 
                    onClick={() => handleCopy(streamUrl, 'url')}
                    className="flex items-center justify-center gap-2 px-6 bg-white dark:bg-gray-800/80 border-l border-gray-200 dark:border-gray-700 font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors active:bg-gray-100 shrink-0"
                  >
                    {copiedUrl ? <CheckCircle2 className="w-5 h-5 text-green-500" /> : <Copy className="w-5 h-5" />}
                    <span className="hidden sm:inline">{copiedUrl ? 'Copied' : 'Copy'}</span>
                  </button>
                </div>
             </div>
          </div>

          {/* Step 2: Stream Key */}
          <div className="bg-white dark:bg-gray-900 rounded-3xl p-8 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-gray-100 dark:border-gray-800 relative overflow-hidden">
             <div className="absolute top-0 right-0 p-8 opacity-5 pointer-events-none text-gray-900 dark:text-white">
                <Key className="w-32 h-32" />
             </div>
             
             <div className="flex items-center gap-4 mb-6 relative z-10">
                <div className="w-10 h-10 bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400 rounded-full flex items-center justify-center font-bold text-lg">2</div>
                <div>
                  <h3 className="text-xl font-bold text-gray-950 dark:text-white">Stream Key</h3>
                  <p className="text-sm text-gray-500 dark:text-gray-400">Your unique authentication key.</p>
                </div>
             </div>

             <div className="relative z-10">
                <div className="flex bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl overflow-hidden focus-within:ring-2 focus-within:ring-[var(--color-brand-accent)] focus-within:border-[var(--color-brand-accent)] transition-all mb-4">
                  <input 
                    type={showKey ? 'text' : 'password'}
                    value={streamKey} 
                    readOnly 
                    className="flex-1 bg-transparent px-5 py-4 font-mono text-sm text-gray-800 dark:text-gray-200 tracking-wider outline-none w-full"
                  />
                  <button 
                    onClick={() => setShowKey(!showKey)}
                    className="flex items-center justify-center px-4 text-gray-400 dark:text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 transition-colors bg-transparent border-l border-gray-200 dark:border-gray-700 shrink-0"
                    title={showKey ? 'Hide Key' : 'Show Key'}
                  >
                    {showKey ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                  </button>
                  <button 
                    onClick={() => handleCopy(streamKey, 'key')}
                    className="flex items-center justify-center gap-2 px-6 bg-white dark:bg-gray-800/80 border-l border-gray-200 dark:border-gray-700 font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors active:bg-gray-100 shrink-0"
                  >
                    {copiedKey ? <CheckCircle2 className="w-5 h-5 text-green-500" /> : <Copy className="w-5 h-5" />}
                    <span className="hidden sm:inline">{copiedKey ? 'Copied' : 'Copy'}</span>
                  </button>
                </div>

                <div className="flex items-start gap-3 p-4 bg-red-50 dark:bg-red-950/40 text-red-800 dark:text-red-300 rounded-xl border border-red-100 dark:border-red-900/50">
                  <AlertTriangle className="w-5 h-5 shrink-0 mt-0.5 text-red-600 dark:text-red-400" />
                  <p className="text-sm">
                    <strong>Keep this key secret!</strong> Anyone with this key can broadcast directly to your channel. Do not share it or show it on screen.
                  </p>
                </div>
             </div>
          </div>

          {/* Step 3: Recommended OBS Encoder Settings (1080p vs 720p) */}
          <div className="bg-white dark:bg-gray-900 rounded-3xl p-8 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-gray-100 dark:border-gray-800 relative overflow-hidden">
             <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div className="flex items-center gap-4">
                  <div className="w-10 h-10 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-400 rounded-full flex items-center justify-center font-bold text-lg">3</div>
                  <div>
                    <h3 className="text-xl font-bold text-gray-950 dark:text-white">Recommended OBS Encoder Settings</h3>
                    <p className="text-sm text-gray-500 dark:text-gray-400">Configure OBS output settings to guarantee genuine 1080p playback on AWS IVS.</p>
                  </div>
                </div>

                <div className="inline-flex bg-gray-100 dark:bg-gray-800 p-1 rounded-xl text-sm font-semibold self-start sm:self-auto">
                  <button
                    type="button"
                    onClick={() => setEncoderPreset('1080p')}
                    className={`px-3 py-1.5 rounded-lg transition-all ${encoderPreset === '1080p' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'}`}
                  >
                    1080p (Full HD)
                  </button>
                  <button
                    type="button"
                    onClick={() => setEncoderPreset('720p')}
                    className={`px-3 py-1.5 rounded-lg transition-all ${encoderPreset === '720p' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'}`}
                  >
                    720p (HD)
                  </button>
                </div>
             </div>

             <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                <div className="p-4 bg-gray-50 dark:bg-gray-800/60 rounded-2xl border border-gray-100 dark:border-gray-800">
                  <span className="text-xs uppercase font-bold text-gray-400 dark:text-gray-500 block mb-1">Canvas & Output</span>
                  <p className="text-base font-bold text-gray-900 dark:text-white">{encoderPreset === '1080p' ? '1920 × 1080' : '1280 × 720'}</p>
                  <span className="text-xs text-gray-500 dark:text-gray-400">Aspect Ratio 16:9</span>
                </div>

                <div className="p-4 bg-gray-50 dark:bg-gray-800/60 rounded-2xl border border-gray-100 dark:border-gray-800">
                  <span className="text-xs uppercase font-bold text-gray-400 dark:text-gray-500 block mb-1">Rate Control & Bitrate</span>
                  <p className="text-base font-bold text-gray-900 dark:text-white">{encoderPreset === '1080p' ? '5,000 – 6,000 kbps' : '2,500 – 3,500 kbps'}</p>
                  <span className="text-xs text-gray-500 dark:text-gray-400">CBR (Constant Bitrate)</span>
                </div>

                <div className="p-4 bg-gray-50 dark:bg-gray-800/60 rounded-2xl border border-gray-100 dark:border-gray-800">
                  <span className="text-xs uppercase font-bold text-gray-400 dark:text-gray-500 block mb-1">Keyframe Interval</span>
                  <p className="text-base font-bold text-indigo-600 dark:text-indigo-400">2 seconds</p>
                  <span className="text-xs text-gray-500 dark:text-gray-400">Crucial for IVS ABR</span>
                </div>

                <div className="p-4 bg-gray-50 dark:bg-gray-800/60 rounded-2xl border border-gray-100 dark:border-gray-800">
                  <span className="text-xs uppercase font-bold text-gray-400 dark:text-gray-500 block mb-1">Framerate & Audio</span>
                  <p className="text-base font-bold text-gray-900 dark:text-white">30 or 60 FPS</p>
                  <span className="text-xs text-gray-500 dark:text-gray-400">AAC 160 kbps, 48 kHz</span>
                </div>
             </div>

             <div className="text-xs text-gray-500 dark:text-gray-300 bg-blue-50/50 dark:bg-blue-950/30 text-blue-900 dark:text-blue-300 p-3.5 rounded-xl border border-blue-100/80 dark:border-blue-900/40 flex items-start gap-2">
                <Sliders className="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
                <span>
                  <strong>Tip for OBS users:</strong> In OBS, navigate to <em>Settings → Output → Output Mode: Advanced → Streaming</em>. Ensure <strong>Rate Control is CBR</strong> and <strong>Keyframe Interval is explicitly set to 2s</strong> (not 0/Auto). In <em>Settings → Video</em>, set Base & Output to {encoderPreset === '1080p' ? '1920x1080' : '1280x720'}.
                </span>
             </div>
          </div>

          {/* Step 4: Remote Guest Integration (VDO.Ninja) */}
          <div className="bg-white dark:bg-gray-900 rounded-3xl p-8 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-gray-100 dark:border-gray-800 relative overflow-hidden">
             <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-6">
                <div className="flex items-center gap-4">
                  <div className="w-10 h-10 bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-400 rounded-full flex items-center justify-center font-bold text-lg">
                    <Users className="w-5 h-5" />
                  </div>
                  <div>
                    <div className="flex items-center gap-2">
                      <h3 className="text-xl font-bold text-gray-950 dark:text-white">Remote Guest Studio</h3>
                      <span className="px-2.5 py-0.5 bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 rounded-full text-xs font-bold uppercase tracking-wider">
                        OBS Browser Source
                      </span>
                    </div>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                      Invite a remote speaker or guest into your OBS broadcast via peer-to-peer WebRTC without third-party server fees.
                    </p>
                  </div>
                </div>

                <button
                  type="button"
                  onClick={regenerateGuestSession}
                  className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors shrink-0 self-start sm:self-auto"
                  title="Generate a new secure guest session"
                >
                  <RefreshCw className="w-3.5 h-3.5" /> New Link
                </button>
             </div>

             <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div className="sm:col-span-2">
                  <label className="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                    Guest Name / Label (Optional)
                  </label>
                  <input 
                    type="text"
                    value={guestName}
                    onChange={(e) => setGuestName(e.target.value)}
                    placeholder="e.g. Pastor David or Guest Speaker"
                    className="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 text-sm text-gray-800 dark:text-gray-200 outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">
                    Target Quality
                  </label>
                  <select
                    value={guestQuality}
                    onChange={(e) => setGuestQuality(e.target.value)}
                    className="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2.5 text-sm text-gray-800 dark:text-gray-200 outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                  >
                    <option value="1080p">1080p (Full HD, 3.5 Mbps)</option>
                    <option value="720p">720p (HD, 2.0 Mbps)</option>
                  </select>
                </div>
             </div>

             <div className="space-y-4">
                {/* 1. Guest Invite Link */}
                <div>
                  <div className="flex items-center justify-between mb-1.5">
                    <span className="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                      1. Guest Invite Link (Send to speaker)
                    </span>
                    <a 
                      href={guestInviteUrl} 
                      target="_blank" 
                      rel="noopener noreferrer" 
                      className="text-xs font-semibold text-purple-600 dark:text-purple-400 hover:text-purple-700 dark:hover:text-purple-300 inline-flex items-center gap-1"
                    >
                      Test Guest View <ExternalLink className="w-3 h-3" />
                    </a>
                  </div>
                  <div className="flex bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl overflow-hidden focus-within:ring-2 focus-within:ring-purple-500">
                    <input 
                      type="text" 
                      value={guestInviteUrl} 
                      readOnly 
                      className="flex-1 bg-transparent px-4 py-3 font-mono text-xs text-gray-700 dark:text-gray-300 outline-none truncate"
                    />
                    <button 
                      onClick={() => handleCopy(guestInviteUrl, 'guest')}
                      className="flex items-center gap-1.5 px-4 bg-white dark:bg-gray-800 border-l border-gray-200 dark:border-gray-700 font-semibold text-xs text-purple-700 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-950/40 shrink-0"
                    >
                      {copiedGuestUrl ? <Check className="w-4 h-4 text-green-500" /> : <Copy className="w-4 h-4" />}
                      <span>{copiedGuestUrl ? 'Copied' : 'Copy Guest Link'}</span>
                    </button>
                  </div>
                </div>

                {/* 2. OBS Browser Source URL */}
                <div>
                  <div className="flex items-center justify-between mb-1.5">
                    <span className="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                      2. OBS Browser Source URL (Paste into OBS Studio)
                    </span>
                  </div>
                  <div className="flex bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl overflow-hidden focus-within:ring-2 focus-within:ring-purple-500">
                    <input 
                      type="text" 
                      value={obsBrowserSourceUrl} 
                      readOnly 
                      className="flex-1 bg-transparent px-4 py-3 font-mono text-xs text-gray-700 dark:text-gray-300 outline-none truncate"
                    />
                    <button 
                      onClick={() => handleCopy(obsBrowserSourceUrl, 'obs')}
                      className="flex items-center gap-1.5 px-4 bg-white dark:bg-gray-800 border-l border-gray-200 dark:border-gray-700 font-semibold text-xs text-purple-700 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-950/40 shrink-0"
                    >
                      {copiedObsUrl ? <Check className="w-4 h-4 text-green-500" /> : <Copy className="w-4 h-4" />}
                      <span>{copiedObsUrl ? 'Copied' : 'Copy OBS URL'}</span>
                    </button>
                  </div>
                </div>
             </div>

             {/* Expandable OBS Browser Source instructions */}
             <div className="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800">
                <button
                  type="button"
                  onClick={() => setShowObsInstructions(!showObsInstructions)}
                  className="text-xs font-bold text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white inline-flex items-center gap-1 transition-colors"
                >
                  {showObsInstructions ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
                  {showObsInstructions ? 'Hide OBS Setup Instructions' : 'View Step-by-Step OBS Browser Source Instructions'}
                </button>

                {showObsInstructions && (
                  <div className="mt-3 p-4 bg-gray-50 dark:bg-gray-800/80 rounded-2xl text-xs text-gray-600 dark:text-gray-300 space-y-2 border border-gray-200/60 dark:border-gray-700 animate-in fade-in duration-200">
                    <p className="font-bold text-gray-800 dark:text-white">How to add your remote guest in OBS:</p>
                    <ol className="list-decimal list-inside space-y-1.5 pl-1">
                      <li>In OBS Studio, navigate to the <strong>Sources</strong> dock and click <strong>+ → Browser</strong>.</li>
                      <li>Name the source (e.g. <em>"Remote Guest - {guestName || 'Speaker'}"</em>) and click OK.</li>
                      <li>In the URL field, paste the <strong>OBS Browser Source URL</strong> from above.</li>
                      <li>Set <strong>Width: 1920</strong> and <strong>Height: 1080</strong> (or 1280 × 720 if using 720p preset).</li>
                      <li>Check the box <strong>"Control audio via OBS"</strong> — this places the guest's microphone track in the OBS Audio Mixer so you can adjust volume independently.</li>
                      <li>Check <strong>"Shutdown source when not visible"</strong> to save CPU when switching scenes.</li>
                      <li>Click OK. When the guest opens their link on any device and clicks Start, their feed will appear live in your OBS scene.</li>
                    </ol>
                  </div>
                )}
             </div>
          </div>
          
          {/* Step 5: Go Live */}
          <div className="bg-[var(--color-brand-accent)] text-white rounded-3xl p-8 shadow-[0_8px_30px_rgb(0,0,0,0.1)] relative overflow-hidden flex flex-col md:flex-row items-center justify-between gap-6">
             <div className="absolute top-0 right-0 p-8 opacity-10 pointer-events-none">
                <Radio className="w-32 h-32" />
             </div>
             
             <div className="relative z-10 flex-1">
               <div className="flex items-center gap-4 mb-2">
                  <div className="w-10 h-10 bg-white/20 text-white rounded-full flex items-center justify-center font-bold text-lg">5</div>
                  <h3 className="text-2xl font-bold">Start Streaming</h3>
               </div>
               <p className="text-white/80 text-lg ml-14">
                 Paste these credentials into your software (e.g. OBS Studio) and click "Start Streaming".
               </p>
             </div>
          </div>

          {/* Reset Key Section */}
          <div className="mt-12 pt-8 border-t border-gray-200 dark:border-gray-800">
            <h3 className="text-lg font-bold text-gray-950 dark:text-white mb-2">Need a new stream key?</h3>
            <p className="text-gray-500 dark:text-gray-400 text-sm mb-6 max-w-2xl">
              If your stream key has been compromised, you can generate a new one immediately. Any active broadcasts using the old key will be abruptly stopped.
            </p>
            <button 
              onClick={resetStreamKey}
              disabled={resetting}
              className="inline-flex items-center gap-2 px-6 py-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 hover:border-red-600 dark:hover:border-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 text-gray-700 dark:text-gray-200 hover:text-red-600 dark:hover:text-red-400 font-semibold rounded-full transition-all focus:ring-4 focus:ring-red-100 disabled:opacity-50"
            >
              <RefreshCw className={`w-4 h-4 ${resetting ? 'animate-spin' : ''}`} /> 
              {resetting ? 'Resetting...' : 'Reset Stream Key'}
            </button>
          </div>

        </div>
      </div>
    </div>
  );
}

