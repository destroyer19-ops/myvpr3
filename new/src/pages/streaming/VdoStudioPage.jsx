import { useState, useEffect, useRef, useCallback } from 'react';
import { 
  Radio, Video, VideoOff, Mic, MicOff, Copy, Check, ExternalLink, 
  Settings, RefreshCw, CheckCircle2, ChevronDown,
  Volume2, VolumeX, Maximize2, Minimize2, AlertCircle,
  Share2, MessageSquare, Laptop
} from 'lucide-react';
import { useAuth } from '@/context/AuthContext';

export default function VdoStudioPage() {
  const { user } = useAuth();

  // ── 1. Broadcast Identity & Configuration ──────────────────────────────
  const sanitizedUsername = (user?.username || user?.name || 'broadcaster')
    .toLowerCase()
    .replace(/[^a-z0-9]/g, '');
  const initialStreamIdRef = useRef(
    `vpr_${sanitizedUsername || 'studio'}_${Math.random().toString(36).substring(2, 7)}`
  );

  const [streamId, setStreamId] = useState(initialStreamIdRef.current);
  const [password, setPassword] = useState('');
  const [quality, setQuality] = useState('720p'); // '1080p' or '720p'
  const [isBroadcasting, setIsBroadcasting] = useState(false);
  const [connectionState, setConnectionState] = useState('initializing'); // 'initializing' | 'preview' | 'connecting' | 'live' | 'ended' | 'permission_denied' | 'device_error'
  const [errorMessage, setErrorMessage] = useState('');

  // Hardware Controls
  const [isCameraOn, setIsCameraOn] = useState(true);
  const [isMicOn, setIsMicOn] = useState(true);
  const [videoDevices, setVideoDevices] = useState([]);
  const [audioDevices, setAudioDevices] = useState([]);
  const [selectedVideoDevice, setSelectedVideoDevice] = useState('');
  const [selectedAudioDevice, setSelectedAudioDevice] = useState('');
  const [audioLevel, setAudioLevel] = useState(0);

  // UI States
  const [copiedType, setCopiedType] = useState('');
  const [streamSeconds, setStreamSeconds] = useState(0);
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [showAdvancedSettings, setShowAdvancedSettings] = useState(false);

  // Refs
  const previewVideoRef = useRef(null);
  const previewStreamRef = useRef(null);
  const iframeRef = useRef(null);
  const stageContainerRef = useRef(null);
  const audioContextRef = useRef(null);
  const audioAnalyserRef = useRef(null);
  const animFrameRef = useRef(null);

  // ── 2. Stream Timer ────────────────────────────────────────────────────
  useEffect(() => {
    let interval = null;
    if (isBroadcasting && connectionState === 'live') {
      interval = setInterval(() => {
        setStreamSeconds(prev => prev + 1);
      }, 1000);
    } else if (!isBroadcasting) {
      setStreamSeconds(0);
    }
    return () => {
      if (interval) clearInterval(interval);
    };
  }, [isBroadcasting, connectionState]);

  const formatTimer = (totalSecs) => {
    const hrs = Math.floor(totalSecs / 3600);
    const mins = Math.floor((totalSecs % 3600) / 60);
    const secs = totalSecs % 60;
    if (hrs > 0) {
      return `${hrs.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
  };

  // ── 3. Device Enumeration & Local Camera Preview ─────────────────────────
  const enumerateDevicesList = async () => {
    try {
      const devices = await navigator.mediaDevices.enumerateDevices();
      const vInputs = devices.filter(d => d.kind === 'videoinput');
      const aInputs = devices.filter(d => d.kind === 'audioinput');

      setVideoDevices(vInputs);
      setAudioDevices(aInputs);

      if (!selectedVideoDevice && vInputs.length > 0) {
        setSelectedVideoDevice(vInputs[0].deviceId);
      }
      if (!selectedAudioDevice && aInputs.length > 0) {
        setSelectedAudioDevice(aInputs[0].deviceId);
      }
    } catch (err) {
      console.warn('Could not enumerate media devices:', err);
    }
  };

  const stopPreviewTracks = useCallback(() => {
    if (previewStreamRef.current) {
      previewStreamRef.current.getTracks().forEach(track => track.stop());
      previewStreamRef.current = null;
    }
    if (previewVideoRef.current) {
      previewVideoRef.current.srcObject = null;
    }
    if (audioContextRef.current) {
      try {
        audioContextRef.current.close().catch(() => {});
      } catch (err) {
        console.warn('AudioContext close error:', err);
      }
      audioContextRef.current = null;
    }
    if (animFrameRef.current) {
      cancelAnimationFrame(animFrameRef.current);
      animFrameRef.current = null;
    }
    setAudioLevel(0);
  }, []);

  const setupAudioMeter = (stream) => {
    try {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return;

      const audioTracks = stream.getAudioTracks();
      if (!audioTracks || audioTracks.length === 0) return;

      const audioCtx = new AudioCtx();
      audioContextRef.current = audioCtx;

      const analyser = audioCtx.createAnalyser();
      analyser.fftSize = 64;
      analyser.smoothingTimeConstant = 0.5;
      audioAnalyserRef.current = analyser;

      const source = audioCtx.createMediaStreamSource(stream);
      source.connect(analyser);

      const dataArray = new Uint8Array(analyser.frequencyBinCount);

      const updateMeter = () => {
        if (!analyser || !audioContextRef.current) return;
        analyser.getByteFrequencyData(dataArray);
        let sum = 0;
        for (let i = 0; i < dataArray.length; i++) {
          sum += dataArray[i];
        }
        const average = sum / dataArray.length;
        // Map average (0 - 128) to 0 - 100%
        const normalized = Math.min(100, Math.round((average / 128) * 100));
        setAudioLevel(normalized);
        animFrameRef.current = requestAnimationFrame(updateMeter);
      };

      updateMeter();
    } catch (e) {
      console.warn('Audio meter initialization error:', e);
    }
  };

  const startLocalPreview = useCallback(async (videoDeviceId = null, audioDeviceId = null) => {
    stopPreviewTracks();
    setConnectionState('initializing');
    setErrorMessage('');

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      setConnectionState('device_error');
      setErrorMessage('Browser media access is not supported on this browser or connection.');
      return;
    }

    const videoConstraints = {
      width: { ideal: quality === '1080p' ? 1920 : 1280 },
      height: { ideal: quality === '1080p' ? 1080 : 720 },
      facingMode: 'user'
    };
    if (videoDeviceId) {
      videoConstraints.deviceId = { exact: videoDeviceId };
    }

    const audioConstraints = {
      echoCancellation: true,
      noiseSuppression: true,
      autoGainControl: true
    };
    if (audioDeviceId) {
      audioConstraints.deviceId = { exact: audioDeviceId };
    }

    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        video: isCameraOn ? videoConstraints : false,
        audio: isMicOn ? audioConstraints : false
      });

      previewStreamRef.current = stream;
      if (previewVideoRef.current) {
        previewVideoRef.current.srcObject = stream;
        previewVideoRef.current.play().catch(() => {});
      }

      setupAudioMeter(stream);
      await enumerateDevicesList();
      setConnectionState('preview');
    } catch (err) {
      console.warn('getUserMedia error:', err);
      if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
        setConnectionState('permission_denied');
        setErrorMessage('Camera or microphone permission was not granted. Please enable permissions in your browser address bar.');
      } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
        setConnectionState('device_error');
        setErrorMessage('No camera or microphone was detected on this device. Please connect a webcam.');
      } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
        setConnectionState('device_error');
        setErrorMessage('Your camera is currently in use by another application. Please close other video apps.');
      } else {
        // Fallback: try audio or video only
        try {
          const fallbackStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
          previewStreamRef.current = fallbackStream;
          if (previewVideoRef.current) {
            previewVideoRef.current.srcObject = fallbackStream;
            previewVideoRef.current.play().catch(() => {});
          }
          await enumerateDevicesList();
          setConnectionState('preview');
        } catch (fallbackErr) {
          setConnectionState('device_error');
          setErrorMessage(err.message || 'Unable to access camera or microphone.');
        }
      }
    }
  }, [quality, isCameraOn, isMicOn, stopPreviewTracks]);

  // Initial mount: start preview
  useEffect(() => {
    if (!isBroadcasting) {
      startLocalPreview(selectedVideoDevice, selectedAudioDevice);
    }
    return () => {
      stopPreviewTracks();
    };
  }, []); // Run on mount

  // ── 4. Toggle Media State ──────────────────────────────────────────────
  const toggleCamera = () => {
    const nextState = !isCameraOn;
    setIsCameraOn(nextState);

    if (isBroadcasting) {
      // Send command to live broadcast frame
      iframeRef.current?.contentWindow?.postMessage({
        action: 'camera',
        value: nextState
      }, '*');
    } else {
      if (previewStreamRef.current) {
        const videoTracks = previewStreamRef.current.getVideoTracks();
        videoTracks.forEach(t => {
          t.enabled = nextState;
        });
      }
    }
  };

  const toggleMic = () => {
    const nextState = !isMicOn;
    setIsMicOn(nextState);

    if (isBroadcasting) {
      // Send command to live broadcast frame
      iframeRef.current?.contentWindow?.postMessage({
        action: 'mic',
        value: nextState
      }, '*');
    } else {
      if (previewStreamRef.current) {
        const audioTracks = previewStreamRef.current.getAudioTracks();
        audioTracks.forEach(t => {
          t.enabled = nextState;
        });
      }
      if (!nextState) {
        setAudioLevel(0);
      }
    }
  };

  const handleVideoDeviceChange = (deviceId) => {
    setSelectedVideoDevice(deviceId);
    if (!isBroadcasting) {
      startLocalPreview(deviceId, selectedAudioDevice);
    }
  };

  const handleAudioDeviceChange = (deviceId) => {
    setSelectedAudioDevice(deviceId);
    if (!isBroadcasting) {
      startLocalPreview(selectedVideoDevice, deviceId);
    }
  };

  // ── 5. Clean URLs Generation ────────────────────────────────────────────
  const cleanStreamId = streamId.trim().toLowerCase().replace(/[^a-z0-9_]/g, '') || 'myvpr_live';
  const qualityParam = quality === '1080p' ? '1' : '2';
  const bitrate = quality === '1080p' ? '4000' : '2500';
  const pwdParam = password.trim() ? `&password=${encodeURIComponent(password.trim())}` : '';

  // Clean White-labeled Push URL (No 3rd-party UI, no forced codec conflict, no invalid parameters)
  const cleanPushUrl = `https://vdo.ninja/?push=${encodeURIComponent(cleanStreamId)}${pwdParam}&quality=${qualityParam}&bitrate=${bitrate}&webcam&autostart&cleanoutput&hidemenu&transparent&rounded=0&noheader&novolume&fadein=0`;

  // Native MyVPR Audience Watch URL
  const origin = typeof window !== 'undefined' ? window.location.origin : 'https://myvpr.org';
  const myvprViewerUrl = `${origin}/watch/vdo/${cleanStreamId}${password.trim() ? `?pwd=${encodeURIComponent(password.trim())}` : ''}`;

  // OBS Studio Browser Source Link (For advanced production setups)
  const obsUrl = `https://vdo.ninja/?view=${encodeURIComponent(cleanStreamId)}${pwdParam}&cleanoutput&transparent&scale=${quality === '1080p' ? '1080' : '720'}`;

  // ── 6. Start / End Broadcast Actions ───────────────────────────────────
  const handleStartBroadcast = () => {
    // Stop local preview so hardware camera is released for the broadcast session
    stopPreviewTracks();
    setConnectionState('connecting');
    setIsBroadcasting(true);

    // After mounting iframe, mark live once session connects
    setTimeout(() => {
      setConnectionState('live');
    }, 2000);
  };

  const handleEndBroadcast = () => {
    try {
      iframeRef.current?.contentWindow?.postMessage({ close: true }, '*');
    } catch (err) {
      console.warn('End broadcast message error:', err);
    }

    setIsBroadcasting(false);
    setConnectionState('ended');

    // Return to local camera preview seamlessly
    setTimeout(() => {
      startLocalPreview(selectedVideoDevice, selectedAudioDevice);
    }, 600);
  };

  // ── 7. Helpers & Utilities ─────────────────────────────────────────────
  const copyToClipboard = (text, type) => {
    navigator.clipboard.writeText(text);
    setCopiedType(type);
    setTimeout(() => setCopiedType(''), 2500);
  };

  const handleWhatsAppShare = () => {
    const text = encodeURIComponent(`Watch my live broadcast on MyVPR: ${myvprViewerUrl}`);
    window.open(`https://wa.me/?text=${text}`, '_blank');
  };

  const handleNativeShare = async () => {
    if (navigator.share) {
      try {
        await navigator.share({
          title: 'MyVPR Live Broadcast',
          text: 'Watch my live broadcast on MyVPR',
          url: myvprViewerUrl
        });
      } catch (err) {
        console.warn('Native share error:', err);
      }
    } else {
      copyToClipboard(myvprViewerUrl, 'myvpr');
    }
  };

  const toggleStageFullscreen = () => {
    if (!stageContainerRef.current) return;
    if (!document.fullscreenElement) {
      stageContainerRef.current.requestFullscreen().catch((err) => {
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

  return (
    <div className="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-8 pb-28 lg:pb-16 animate-in fade-in duration-300 w-full min-w-0">
      
      {/* ── 1. Page Header ────────────────────────────────────────────── */}
      <div className="mb-6 sm:mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-gray-200 dark:border-gray-800 pb-5">
        <div>
          <div className="flex items-center gap-2 mb-1.5">
            <span className="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 text-xs font-semibold">
              <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
              {isBroadcasting && connectionState === 'live' ? 'Live On Air' : 'Studio Ready'}
            </span>
            <span className="text-xs text-gray-500 dark:text-gray-400 font-medium">
              Direct Browser Broadcasting
            </span>
          </div>
          <h1 className="font-serif text-3xl sm:text-4xl font-bold tracking-tight text-gray-950 dark:text-white">
            MyVPR Browser Studio
          </h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm mt-1 max-w-2xl">
            Broadcast live directly from your web browser to your congregation. No external software or complex setup required.
          </p>
        </div>

        {/* Action Header Button: Start/Stop quick toggle */}
        <div className="flex items-center gap-2.5">
          {isBroadcasting ? (
            <button
              type="button"
              onClick={handleEndBroadcast}
              className="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-500 text-white text-xs sm:text-sm font-semibold rounded-full shadow-md transition-all active:scale-95 cursor-pointer"
            >
              <div className="w-2.5 h-2.5 rounded-xs bg-white" />
              End Broadcast
            </button>
          ) : (
            <button
              type="button"
              onClick={handleStartBroadcast}
              disabled={connectionState === 'permission_denied' || connectionState === 'device_error'}
              className="inline-flex items-center gap-2 px-6 py-2.5 bg-[#5b3ee4] hover:bg-[#4f2bee] disabled:opacity-50 text-white text-xs sm:text-sm font-semibold rounded-full shadow-md shadow-indigo-500/20 transition-all active:scale-95 cursor-pointer"
            >
              <Radio className="w-4 h-4 animate-pulse" />
              Start Live Broadcast
            </button>
          )}
        </div>
      </div>

      {/* ── 2. Studio Layout ─────────────────────────────────────────── */}
      <div className="grid lg:grid-cols-12 gap-6 lg:gap-8 items-start mb-10">
        
        {/* ── LEFT: Main Video Stage (8 cols on desktop) ────────────── */}
        <div className="lg:col-span-8 flex flex-col gap-4">
          
          {/* Main 16:9 Cinema Stage */}
          <div 
            ref={stageContainerRef}
            className="relative w-full aspect-video rounded-2xl sm:rounded-3xl overflow-hidden bg-gray-950 shadow-2xl border border-gray-800 flex items-center justify-center group"
          >
            {/* Live Broadcast Feed */}
            {isBroadcasting && (
              <iframe
                ref={iframeRef}
                src={cleanPushUrl}
                title="MyVPR Studio Live Broadcast"
                className="w-full h-full border-0 object-contain bg-black"
                allow="camera; microphone; display-capture; autoplay; encrypted-media; fullscreen; picture-in-picture"
                allowFullScreen
              />
            )}

            {/* Local Camera Preview (Active) */}
            {!isBroadcasting && connectionState === 'preview' && (
              <video
                ref={previewVideoRef}
                autoPlay
                playsInline
                muted
                className={`w-full h-full object-contain bg-black transition-opacity duration-300 ${isCameraOn ? 'opacity-100' : 'opacity-0'}`}
              />
            )}

            {/* Camera Disabled State (When camera is toggled off) */}
            {!isBroadcasting && connectionState === 'preview' && !isCameraOn && (
              <div className="absolute inset-0 flex flex-col items-center justify-center bg-gray-950 text-center p-6 animate-in fade-in">
                <div className="w-20 h-20 rounded-full bg-gradient-to-tr from-indigo-900/60 to-purple-900/60 border border-indigo-500/30 flex items-center justify-center mb-4 text-indigo-300 shadow-xl">
                  <VideoOff className="w-8 h-8 text-indigo-400" />
                </div>
                <h3 className="font-serif font-bold text-xl text-white mb-1">
                  Camera is Turned Off
                </h3>
                <p className="text-gray-400 text-xs sm:text-sm max-w-xs mb-4">
                  Your video is disabled. Click the camera icon below to turn your video on before starting.
                </p>
                <button
                  type="button"
                  onClick={toggleCamera}
                  className="px-4 py-2 rounded-full bg-white/10 hover:bg-white/20 text-white text-xs font-semibold transition-colors cursor-pointer"
                >
                  Turn Camera On
                </button>
              </div>
            )}

            {/* Connecting State */}
            {isBroadcasting && connectionState === 'connecting' && (
              <div className="absolute inset-0 flex flex-col items-center justify-center bg-black/90 backdrop-blur-xs text-center p-6 z-10 animate-in fade-in">
                <div className="w-12 h-12 rounded-full border-3 border-white/20 border-t-[#5b3ee4] animate-spin mb-4" />
                <h3 className="font-serif font-bold text-xl text-white mb-1">
                  Connecting to Broadcast...
                </h3>
                <p className="text-gray-400 text-xs sm:text-sm">
                  Initializing your audio and video streams. You will be live in a moment.
                </p>
              </div>
            )}

            {/* Initializing / Loading State */}
            {!isBroadcasting && connectionState === 'initializing' && (
              <div className="flex flex-col items-center justify-center text-center p-6">
                <div className="w-10 h-10 rounded-full border-3 border-gray-700 border-t-[#5b3ee4] animate-spin mb-3" />
                <p className="text-gray-300 text-sm font-medium">Connecting camera and microphone...</p>
              </div>
            )}

            {/* Permission Denied State */}
            {!isBroadcasting && connectionState === 'permission_denied' && (
              <div className="flex flex-col items-center justify-center text-center p-6 max-w-md animate-in fade-in">
                <div className="w-16 h-16 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-400 flex items-center justify-center mb-4">
                  <AlertCircle className="w-8 h-8" />
                </div>
                <h3 className="font-serif font-bold text-xl text-white mb-2">
                  Camera Access Required
                </h3>
                <p className="text-gray-400 text-xs sm:text-sm leading-relaxed mb-5">
                  {errorMessage || 'MyVPR needs access to your camera and microphone so you can stream. Please click the permissions icon in your browser address bar to allow access.'}
                </p>
                <button
                  type="button"
                  onClick={() => startLocalPreview(selectedVideoDevice, selectedAudioDevice)}
                  className="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-[#5b3ee4] hover:bg-[#4f2bee] text-white text-xs font-semibold shadow-lg transition-all active:scale-95 cursor-pointer"
                >
                  <RefreshCw className="w-3.5 h-3.5" /> Request Access Again
                </button>
              </div>
            )}

            {/* Device Error State */}
            {!isBroadcasting && connectionState === 'device_error' && (
              <div className="flex flex-col items-center justify-center text-center p-6 max-w-md animate-in fade-in">
                <div className="w-16 h-16 rounded-2xl bg-red-500/10 border border-red-500/30 text-red-400 flex items-center justify-center mb-4">
                  <VideoOff className="w-8 h-8" />
                </div>
                <h3 className="font-serif font-bold text-xl text-white mb-2">
                  Camera Unavailable
                </h3>
                <p className="text-gray-400 text-xs sm:text-sm leading-relaxed mb-5">
                  {errorMessage || 'Unable to connect to a video capture device. Make sure your webcam is plugged in and not in use by another app.'}
                </p>
                <button
                  type="button"
                  onClick={() => startLocalPreview(selectedVideoDevice, selectedAudioDevice)}
                  className="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-white/10 hover:bg-white/20 text-white text-xs font-semibold transition-all active:scale-95 cursor-pointer"
                >
                  <RefreshCw className="w-3.5 h-3.5" /> Check for Devices
                </button>
              </div>
            )}

            {/* ── Top Status HUD Bar ──────────────────────────────── */}
            <div className="absolute top-0 left-0 right-0 p-3 sm:p-5 bg-gradient-to-b from-black/85 via-black/40 to-transparent flex items-center justify-between z-20 pointer-events-none">
              <div className="flex items-center gap-2 sm:gap-2.5 pointer-events-auto">
                {isBroadcasting && connectionState === 'live' ? (
                  <>
                    <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-red-600 text-white text-xs font-bold shadow-md animate-pulse">
                      <span className="w-2 h-2 rounded-full bg-white" /> ON AIR
                    </span>
                    <span className="px-3 py-1 rounded-full bg-black/60 backdrop-blur-md text-white font-mono text-xs font-semibold border border-white/10">
                      {formatTimer(streamSeconds)}
                    </span>
                  </>
                ) : (
                  <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-black/60 backdrop-blur-md text-gray-200 text-xs font-semibold border border-white/10">
                    <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" /> Camera Preview
                  </span>
                )}

                <span className="hidden sm:inline-flex px-2.5 py-1 rounded-full bg-black/60 backdrop-blur-md text-gray-300 text-xs font-mono border border-white/10">
                  {quality} HD
                </span>
              </div>

              {/* Top Right: Mic Meter & Fullscreen */}
              <div className="flex items-center gap-2 pointer-events-auto">
                {/* Audio Level VU Meter */}
                <div 
                  className="hidden sm:flex items-center gap-1 px-3 py-1 rounded-full bg-black/60 backdrop-blur-md border border-white/10 text-xs text-gray-300"
                  title={isMicOn ? `Microphone level: ${audioLevel}%` : 'Microphone muted'}
                >
                  {isMicOn ? (
                    <Volume2 className="w-3.5 h-3.5 text-emerald-400" />
                  ) : (
                    <VolumeX className="w-3.5 h-3.5 text-red-400" />
                  )}
                  <div className="w-12 h-1.5 bg-gray-800 rounded-full overflow-hidden">
                    <div 
                      className={`h-full transition-all duration-75 ${
                        !isMicOn 
                          ? 'w-0' 
                          : audioLevel > 75 
                          ? 'bg-amber-400' 
                          : 'bg-emerald-400'
                      }`}
                      style={{ width: isMicOn ? `${Math.min(100, audioLevel * 1.5)}%` : '0%' }}
                    />
                  </div>
                </div>

                {/* Stage Fullscreen Toggle */}
                <button
                  type="button"
                  onClick={toggleStageFullscreen}
                  className="p-1.5 sm:p-2 rounded-full bg-black/60 hover:bg-black/80 backdrop-blur-md text-white/80 hover:text-white transition-colors cursor-pointer border border-white/10"
                  title={isFullscreen ? 'Exit Fullscreen' : 'Fullscreen Stage'}
                >
                  {isFullscreen ? <Minimize2 className="w-4 h-4" /> : <Maximize2 className="w-4 h-4" />}
                </button>
              </div>
            </div>

            {/* ── Bottom Floating Studio Dock ────────────────────── */}
            <div className="absolute bottom-4 sm:bottom-6 left-1/2 -translate-x-1/2 z-20 pointer-events-auto flex items-center gap-2 sm:gap-3 bg-gray-950/85 backdrop-blur-md border border-white/15 rounded-full px-4 sm:px-6 py-2 sm:py-2.5 shadow-2xl">
              
              {/* Camera Toggle Button */}
              <button
                type="button"
                onClick={toggleCamera}
                className={`w-10 h-10 rounded-full flex items-center justify-center transition-all active:scale-95 cursor-pointer ${
                  isCameraOn 
                    ? 'bg-white/10 hover:bg-white/20 text-white' 
                    : 'bg-red-500/20 text-red-400 border border-red-500/40 hover:bg-red-500/30'
                }`}
                title={isCameraOn ? 'Turn off camera' : 'Turn on camera'}
              >
                {isCameraOn ? <Video className="w-4 h-4" /> : <VideoOff className="w-4 h-4" />}
              </button>

              {/* Microphone Toggle Button */}
              <button
                type="button"
                onClick={toggleMic}
                className={`w-10 h-10 rounded-full flex items-center justify-center transition-all active:scale-95 cursor-pointer ${
                  isMicOn 
                    ? 'bg-white/10 hover:bg-white/20 text-white' 
                    : 'bg-red-500/20 text-red-400 border border-red-500/40 hover:bg-red-500/30'
                }`}
                title={isMicOn ? 'Mute microphone' : 'Unmute microphone'}
              >
                {isMicOn ? <Mic className="w-4 h-4" /> : <MicOff className="w-4 h-4" />}
              </button>

              <div className="w-[1px] h-6 bg-white/20" />

              {/* Start / End Broadcast Main Action */}
              {isBroadcasting ? (
                <button
                  type="button"
                  onClick={handleEndBroadcast}
                  className="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-500 text-white text-xs sm:text-sm font-bold rounded-full shadow-lg transition-all active:scale-95 cursor-pointer"
                >
                  <div className="w-3 h-3 rounded-xs bg-white" />
                  <span>End Stream</span>
                </button>
              ) : (
                <button
                  type="button"
                  onClick={handleStartBroadcast}
                  disabled={connectionState === 'permission_denied' || connectionState === 'device_error'}
                  className="inline-flex items-center gap-2 px-5 sm:px-6 py-2.5 bg-[#5b3ee4] hover:bg-[#4f2bee] disabled:opacity-50 text-white text-xs sm:text-sm font-bold rounded-full shadow-lg shadow-indigo-500/30 transition-all active:scale-95 cursor-pointer"
                >
                  <Radio className="w-4 h-4" />
                  <span>Go Live</span>
                </button>
              )}

            </div>

          </div>

          {/* Quick Guidance Sub-Bar */}
          <div className="flex flex-wrap items-center justify-between text-xs text-gray-500 dark:text-gray-400 px-2 py-1">
            <span className="flex items-center gap-1.5">
              <CheckCircle2 className="w-3.5 h-3.5 text-emerald-500" />
              Real-time WebRTC broadcast with sub-second latency
            </span>
            <span>
              {isBroadcasting ? 'Live stream active' : 'Click "Go Live" when you are ready to stream'}
            </span>
          </div>

        </div>

        {/* ── RIGHT: Stream Information & Sharing Hub (4 cols) ─────── */}
        <div className="lg:col-span-4 flex flex-col gap-6">
          
          {/* ── Card 1: Share Your Broadcast ───────────────────────── */}
          <div className="bg-white dark:bg-gray-900 rounded-3xl p-6 sm:p-7 border border-gray-100 dark:border-gray-800 shadow-sm space-y-4">
            <div className="flex items-start justify-between">
              <div>
                <div className="flex items-center gap-2 mb-1">
                  <span className={`w-2 h-2 rounded-full ${isBroadcasting ? 'bg-red-500 animate-pulse' : 'bg-emerald-500'}`} />
                  <span className="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    {isBroadcasting ? 'Live Link' : 'Congregation Link'}
                  </span>
                </div>
                <h2 className="font-serif font-bold text-xl text-gray-950 dark:text-white">
                  Share Your Broadcast
                </h2>
              </div>
              <div className="w-10 h-10 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-[#5b3ee4] dark:text-[#a5b4fc] flex items-center justify-center shrink-0">
                <Share2 className="w-5 h-5" />
              </div>
            </div>

            <p className="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
              Share this watch link with your church members. Viewers can open it on any phone, tablet, or computer to watch in real time.
            </p>

            {/* Watch Link Input & Copy Pill */}
            <div className="space-y-2">
              <label className="block text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Viewer Watch Link
              </label>
              <div className="relative">
                <input
                  type="text"
                  readOnly
                  value={myvprViewerUrl}
                  className="w-full pl-3.5 pr-24 py-3 rounded-2xl bg-gray-50 dark:bg-gray-800/80 border border-gray-200 dark:border-gray-700 text-xs font-semibold text-gray-900 dark:text-white select-all outline-none"
                />
                <button
                  type="button"
                  onClick={() => copyToClipboard(myvprViewerUrl, 'myvpr')}
                  className="absolute right-1.5 top-1.5 bottom-1.5 px-3 rounded-xl bg-[#5b3ee4] hover:bg-[#4f2bee] text-white text-xs font-semibold flex items-center gap-1.5 transition-all active:scale-95 cursor-pointer"
                >
                  {copiedType === 'myvpr' ? <Check className="w-3.5 h-3.5 text-emerald-300" /> : <Copy className="w-3.5 h-3.5" />}
                  <span>{copiedType === 'myvpr' ? 'Copied' : 'Copy'}</span>
                </button>
              </div>
            </div>

            {/* Quick Sharing Action Buttons */}
            <div className="grid grid-cols-2 gap-2 pt-1">
              <a
                href={myvprViewerUrl}
                target="_blank"
                rel="noreferrer"
                className="py-2.5 px-3 rounded-xl bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-750 text-gray-800 dark:text-gray-200 text-xs font-semibold flex items-center justify-center gap-1.5 transition-colors"
                title="Open viewer page in a new browser tab"
              >
                <ExternalLink className="w-3.5 h-3.5" />
                <span>Open Viewer</span>
              </a>

              <button
                type="button"
                onClick={handleWhatsAppShare}
                className="py-2.5 px-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 text-xs font-semibold flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
              >
                <MessageSquare className="w-3.5 h-3.5 text-emerald-500" />
                <span>WhatsApp</span>
              </button>
            </div>

            <button
              type="button"
              onClick={handleNativeShare}
              className="w-full py-2.5 px-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-semibold flex items-center justify-center gap-2 transition-colors cursor-pointer"
            >
              <Share2 className="w-3.5 h-3.5 text-[#5b3ee4]" />
              <span>Share Link with Audience</span>
            </button>
          </div>

          {/* ── Card 2: Broadcast Settings & Device Selection ─────── */}
          <div className="bg-white dark:bg-gray-900 rounded-3xl p-6 sm:p-7 border border-gray-100 dark:border-gray-800 shadow-sm space-y-4">
            <h3 className="font-serif font-bold text-lg text-gray-950 dark:text-white flex items-center gap-2">
              <Settings className="w-4 h-4 text-gray-400" /> Broadcast Settings
            </h3>

            {/* Broadcast Name / Room ID */}
            <div>
              <label className="block text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">
                Broadcast Name / Stream ID
              </label>
              <input
                type="text"
                disabled={isBroadcasting}
                value={streamId}
                onChange={e => setStreamId(e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, ''))}
                placeholder="e.g. sunday_service"
                className="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs font-semibold text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-[#5b3ee4]/20 disabled:opacity-50"
              />
              <p className="text-[10px] text-gray-400 mt-1">
                Letters, numbers, and underscores only. Used in your watch link.
              </p>
            </div>

            {/* Passcode (Optional) */}
            <div>
              <label className="block text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">
                Passcode / PIN (Optional)
              </label>
              <input
                type="text"
                disabled={isBroadcasting}
                value={password}
                onChange={e => setPassword(e.target.value)}
                placeholder="Leave blank for public stream"
                className="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs text-gray-900 dark:text-white outline-none disabled:opacity-50"
              />
            </div>

            {/* Video Quality */}
            <div>
              <label className="block text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">
                Video Resolution
              </label>
              <div className="grid grid-cols-2 gap-2">
                {[
                  { id: '720p', label: '720p HD (Recommended)', bit: '2.5 Mbps' },
                  { id: '1080p', label: '1080p Full HD', bit: '4.0 Mbps' }
                ].map(q => (
                  <button
                    key={q.id}
                    type="button"
                    disabled={isBroadcasting}
                    onClick={() => setQuality(q.id)}
                    className={`py-2 px-3 rounded-xl text-center border transition-all cursor-pointer disabled:opacity-50 ${
                      quality === q.id
                        ? 'bg-purple-50 dark:bg-purple-950/60 border-[#5b3ee4] text-[#5b3ee4] dark:text-[#a5b4fc] font-bold shadow-2xs'
                        : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:border-gray-300'
                    }`}
                  >
                    <p className="text-xs font-semibold">{q.label}</p>
                    <p className="text-[10px] opacity-75">{q.bit}</p>
                  </button>
                ))}
              </div>
            </div>

            {/* Hardware Device Selectors */}
            {videoDevices.length > 0 && (
              <div>
                <label className="block text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">
                  Camera Device
                </label>
                <div className="relative">
                  <select
                    disabled={isBroadcasting}
                    value={selectedVideoDevice}
                    onChange={e => handleVideoDeviceChange(e.target.value)}
                    className="w-full appearance-none px-3.5 py-2.5 pr-8 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs font-medium text-gray-900 dark:text-white outline-none cursor-pointer disabled:opacity-50"
                  >
                    {videoDevices.map(d => (
                      <option key={d.deviceId} value={d.deviceId}>
                        {d.label || `Camera ${d.deviceId.substring(0, 5)}`}
                      </option>
                    ))}
                  </select>
                  <ChevronDown className="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                </div>
              </div>
            )}

            {audioDevices.length > 0 && (
              <div>
                <label className="block text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">
                  Microphone Device
                </label>
                <div className="relative">
                  <select
                    disabled={isBroadcasting}
                    value={selectedAudioDevice}
                    onChange={e => handleAudioDeviceChange(e.target.value)}
                    className="w-full appearance-none px-3.5 py-2.5 pr-8 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs font-medium text-gray-900 dark:text-white outline-none cursor-pointer disabled:opacity-50"
                  >
                    {audioDevices.map(d => (
                      <option key={d.deviceId} value={d.deviceId}>
                        {d.label || `Microphone ${d.deviceId.substring(0, 5)}`}
                      </option>
                    ))}
                  </select>
                  <ChevronDown className="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                </div>
              </div>
            )}

            {/* Collapsible Advanced OBS Source */}
            <div className="pt-2 border-t border-gray-100 dark:border-gray-800">
              <button
                type="button"
                onClick={() => setShowAdvancedSettings(!showAdvancedSettings)}
                className="w-full flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 font-medium py-1 cursor-pointer"
              >
                <span className="flex items-center gap-1.5">
                  <Laptop className="w-3.5 h-3.5 text-gray-400" />
                  Advanced OBS Source URL
                </span>
                <ChevronDown className={`w-3.5 h-3.5 transition-transform ${showAdvancedSettings ? 'rotate-180' : ''}`} />
              </button>

              {showAdvancedSettings && (
                <div className="mt-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 text-xs space-y-2 animate-in fade-in">
                  <p className="text-[11px] text-gray-500 dark:text-gray-400">
                    Paste this link as a Browser Source in OBS Studio to mix your browser camera into a live production switcher:
                  </p>
                  <div className="flex items-center gap-2">
                    <input
                      type="text"
                      readOnly
                      value={obsUrl}
                      className="w-full px-2.5 py-1.5 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-[11px] font-mono text-gray-700 dark:text-gray-300 select-all outline-none"
                    />
                    <button
                      type="button"
                      onClick={() => copyToClipboard(obsUrl, 'obs')}
                      className="px-3 py-1.5 rounded-lg bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-650 text-gray-800 dark:text-gray-200 text-[11px] font-semibold shrink-0 cursor-pointer"
                    >
                      {copiedType === 'obs' ? 'Copied' : 'Copy'}
                    </button>
                  </div>
                </div>
              )}
            </div>

          </div>

        </div>

      </div>

    </div>
  );
}
