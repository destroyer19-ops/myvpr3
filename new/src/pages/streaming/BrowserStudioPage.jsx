import { request } from "../../services/api";
import React, { useEffect, useState, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/FormElements';
import { useAuth } from '@/context/AuthContext';

export default function BrowserStudioPage() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [channel, setChannel] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  
  // Studio state
  const [setupState, setSetupState] = useState('initial'); // 'initial', 'requesting', 'ready', 'error'
  const [isLive, setIsLive] = useState(false);
  const [cameras, setCameras] = useState([]);
  const [mics, setMics] = useState([]);
  const [selectedCamera, setSelectedCamera] = useState('');
  const [selectedMic, setSelectedMic] = useState('');
  const [cameraResolution, setCameraResolution] = useState('');
  
  const videoRef = useRef(null);
  const broadcastClientRef = useRef(null);
  const currentCameraRef = useRef(null);
  const currentMicRef = useRef(null);

  // Helper: Request camera with graceful 1080p -> 720p -> unconstrained fallback
  const getCameraStream = async (deviceId = null) => {
    // Tier 1: Request 1080p (ideal) for genuine Full HD broadcast
    try {
      const constraints = {
        width: { ideal: 1920 },
        height: { ideal: 1080 },
        frameRate: { ideal: 30, max: 30 }
      };
      if (deviceId) {
        constraints.deviceId = { exact: deviceId };
      }
      const stream = await navigator.mediaDevices.getUserMedia({ video: constraints });
      const track = stream.getVideoTracks()[0];
      if (track) {
        const s = track.getSettings();
        setCameraResolution(s.width && s.height ? `${s.width}×${s.height}` : '1080p (ideal)');
      }
      return stream;
    } catch (err1080) {
      console.warn("1080p camera constraint not supported, falling back to 720p:", err1080);
    }

    // Tier 2: Fall back to 720p (ideal)
    try {
      const constraints = {
        width: { ideal: 1280 },
        height: { ideal: 720 },
        frameRate: { ideal: 30, max: 30 }
      };
      if (deviceId) {
        constraints.deviceId = { exact: deviceId };
      }
      const stream = await navigator.mediaDevices.getUserMedia({ video: constraints });
      const track = stream.getVideoTracks()[0];
      if (track) {
        const s = track.getSettings();
        setCameraResolution(s.width && s.height ? `${s.width}×${s.height}` : '720p (fallback)');
      }
      return stream;
    } catch (err720) {
      console.warn("720p camera constraint not supported, falling back to unconstrained:", err720);
    }

    // Tier 3: Unconstrained fallback to guarantee access on any legacy/mobile hardware
    const fallbackConstraints = deviceId ? { deviceId: { exact: deviceId } } : true;
    const stream = await navigator.mediaDevices.getUserMedia({ video: fallbackConstraints });
    const track = stream.getVideoTracks()[0];
    if (track) {
      const s = track.getSettings();
      setCameraResolution(s.width && s.height ? `${s.width}×${s.height}` : 'Active');
    }
    return stream;
  };

  // 1. Fetch channel credentials
  useEffect(() => {
    async function fetchStatus() {
      try {
        const json = await request('/streaming/status');
        if (json.success && json.data.has_active_plan) {
          if (json.data.channel) {
            setChannel(json.data.channel);
          } else {
            // Provision missing channel
            const prov = await request('/streaming/provision', { method: 'POST' });
            if (prov.success) {
              const retry = await request('/streaming/status');
              if (retry.success && retry.data.channel) {
                setChannel(retry.data.channel);
                return;
              }
            }
            alert('Failed to provision a new streaming channel for your account.');
            navigate('/dashboard/streaming');
          }
        } else {
          navigate('/dashboard/streaming');
        }
      } catch (e) {
        setError('Failed to fetch streaming status.');
      } finally {
        setLoading(false);
      }
    }
    fetchStatus();
  }, [navigate]);

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      if (broadcastClientRef.current) {
        broadcastClientRef.current.stopBroadcast();
        broadcastClientRef.current.detachPreview();
        broadcastClientRef.current = null;
      }
    };
  }, []);

  // 2. Initialize AWS IVS Web Broadcast SDK explicitly
  const startSetup = async () => {
    if (!channel) return;
    
    setSetupState('requesting');
    setError('');
    
    try {
      // 1. Get initial camera stream with graceful 1080p fallback
      const initialVideoStream = await getCameraStream();
      // 2. Get initial audio stream
      const initialAudioStream = await navigator.mediaDevices.getUserMedia({ 
        audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true } 
      });
      
      // Load the IVS Web Broadcast SDK dynamically if not present
      if (!window.IVSBroadcastClient) {
        await new Promise((resolve, reject) => {
          const script = document.createElement('script');
          script.src = 'https://web-broadcast.live-video.net/1.8.0/amazon-ivs-web-broadcast.js';
          script.onload = resolve;
          script.onerror = reject;
          document.body.appendChild(script);
        });
      }

      const IVSBroadcastClient = window.IVSBroadcastClient;
      
      let ingest = channel.ingest_endpoint || '';
      if (ingest.startsWith('rtmps://')) {
          ingest = ingest.replace('rtmps://', '').split('/')[0].split(':')[0];
      }
      
      if (!ingest || ingest.trim() === '') {
          console.error("Missing or invalid ingest endpoint from channel:", channel);
          throw new Error("Missing valid IVS ingest endpoint");
      }
      
      // STANDARD_LANDSCAPE defines { width: 1920, height: 1080, maxFramerate: 30, maxBitrate: 8500 }
      const client = IVSBroadcastClient.create({
        streamConfig: IVSBroadcastClient.STANDARD_LANDSCAPE,
        ingestEndpoint: ingest
      });
      
      broadcastClientRef.current = client;

      // Attach local preview to the video element
      client.attachPreview(videoRef.current);

      // Add camera and microphone to the broadcast
      await client.addVideoInputDevice(initialVideoStream, 'camera', { index: 0 });
      await client.addAudioInputDevice(initialAudioStream, 'mic');

      // Get all devices now that we have permissions
      const devices = await navigator.mediaDevices.enumerateDevices();
      const videoDevices = devices.filter(d => d.kind === 'videoinput');
      const audioDevices = devices.filter(d => d.kind === 'audioinput');
      
      setCameras(videoDevices);
      setMics(audioDevices);
      
      // Select the active devices from the initial streams
      const activeVideoTrack = initialVideoStream.getVideoTracks()[0];
      const activeAudioTrack = initialAudioStream.getAudioTracks()[0];
      
      if (activeVideoTrack) {
        const deviceId = activeVideoTrack.getSettings().deviceId;
        setSelectedCamera(deviceId);
        currentCameraRef.current = deviceId;
      }
      if (activeAudioTrack) {
        const deviceId = activeAudioTrack.getSettings().deviceId;
        setSelectedMic(deviceId);
        currentMicRef.current = deviceId;
      }
      
      setSetupState('ready');
    } catch (err) {
      console.error('Error initializing broadcast client:', err);
      setSetupState('error');
      setError('Camera and microphone access is blocked. Allow access in your browser settings and try again.');
    }
  };

  // 3. Handle device selection changes
  useEffect(() => {
    const updateDevices = async () => {
      const client = broadcastClientRef.current;
      if (!client) return;

      try {
        if (selectedCamera !== currentCameraRef.current) {
          const activeVideo = client.getVideoInputDevice('camera');
          if (activeVideo) {
            client.removeVideoInputDevice('camera');
          }
          if (selectedCamera) {
            const stream = await getCameraStream(selectedCamera);
            await client.addVideoInputDevice(stream, 'camera', { index: 0 });
          }
          currentCameraRef.current = selectedCamera;
        }

        if (selectedMic !== currentMicRef.current) {
          const activeAudio = client.getAudioInputDevice('mic');
          if (activeAudio) {
            client.removeAudioInputDevice('mic');
          }
          if (selectedMic) {
            const stream = await navigator.mediaDevices.getUserMedia({ 
              audio: { deviceId: { exact: selectedMic }, echoCancellation: true, noiseSuppression: true } 
            });
            await client.addAudioInputDevice(stream, 'mic');
          }
          currentMicRef.current = selectedMic;
        }
      } catch (err) {
        console.error('Failed to change device:', err);
      }
    };

    if (selectedCamera || selectedMic) {
      updateDevices();
    }
  }, [selectedCamera, selectedMic]);

  // 4. Start/Stop Broadcast
  const toggleBroadcast = async () => {
    const client = broadcastClientRef.current;
    if (!client) return;

    if (isLive) {
      try {
        client.stopBroadcast();
        await request('/streaming/broadcast/stop', { method: 'POST' });
        setIsLive(false);
      } catch (err) {
        console.error('Failed to stop broadcast securely:', err);
      }
    } else {
      try {
        await client.startBroadcast(channel.aws_stream_key_arn);
        await request('/streaming/broadcast/start', { method: 'POST' });
        setIsLive(true);
      } catch (err) {
        console.error('Failed to start broadcast:', err);
        const isDummy = channel.aws_stream_key_arn?.includes('dummy') || channel.ingest_endpoint?.includes('dummy');
        if (isDummy) {
            alert('Broadcast failed: Your account has a dummy IVS channel. Please configure real AWS credentials in your backend and reprovision your channel.');
        } else {
            alert(`Failed to start broadcast: ${err.message || 'Check your stream key and connection.'}`);
        }
      }
    }
  };

  if (loading) return <div className="p-12 text-center text-gray-950 dark:text-white font-medium">Loading Studio...</div>;

  return (
    <div className="max-w-6xl mx-auto p-4 lg:p-8 relative animate-in fade-in duration-300 pb-28 lg:pb-16 text-gray-950 dark:text-white">
      {setupState !== 'ready' && (
        <div className="absolute inset-0 z-50 flex flex-col items-center justify-center p-4 bg-black/60 dark:bg-black/80 backdrop-blur-sm rounded-3xl">
          <div className="max-w-md w-full bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-3xl p-8 text-center shadow-2xl">
            <h2 className="font-serif text-2xl font-bold text-gray-950 dark:text-white mb-3">Studio Setup</h2>
            
            {setupState === 'initial' && (
              <>
                <p className="text-gray-600 dark:text-gray-400 mb-8 text-sm leading-relaxed">
                  Ready to go live? We'll need access to your camera and microphone to begin broadcasting.
                </p>
                <Button onClick={startSetup} className="w-full bg-[#5b3ee4] hover:bg-[#4f2bee] py-4 text-base font-bold shadow-md">
                  Enable Camera & Microphone
                </Button>
              </>
            )}

            {setupState === 'requesting' && (
              <div className="flex flex-col items-center py-6">
                <div className="w-12 h-12 rounded-full border-4 border-gray-200 dark:border-gray-700 border-t-[#5b3ee4] animate-spin mb-4" />
                <p className="text-gray-600 dark:text-gray-400 text-sm">Requesting permissions and initializing Studio...</p>
              </div>
            )}

            {setupState === 'error' && (
              <>
                <div className="text-red-600 dark:text-red-400 mb-6 bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900/40 p-4 rounded-2xl text-sm text-left">
                  {error}
                </div>
                <Button onClick={startSetup} className="w-full bg-gray-900 hover:bg-black dark:bg-gray-800 dark:hover:bg-gray-700 text-white">
                  Try Again
                </Button>
              </>
            )}
          </div>
        </div>
      )}

      <div className="flex flex-col md:flex-row items-start md:items-center justify-between mb-8 gap-4">
        <div>
          <h1 className="font-serif text-3xl sm:text-4xl font-bold text-gray-950 dark:text-white mb-1.5 tracking-tight">Browser Studio</h1>
          <p className="text-gray-600 dark:text-gray-400 text-sm sm:text-base">Broadcast directly from your browser to your public viewing page.</p>
        </div>
        <div className="flex flex-wrap items-center gap-3">
          <Button variant="outline" onClick={() => navigate('/dashboard/streaming')} disabled={isLive}>
            Exit Studio
          </Button>
          <Button 
            className={`${isLive ? 'bg-red-600 hover:bg-red-700' : 'bg-[#5b3ee4] hover:bg-[#4f2bee]'} min-w-[150px] font-bold shadow-md`}
            onClick={toggleBroadcast}
          >
            {isLive ? 'END BROADCAST' : 'GO LIVE'}
          </Button>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
        
        {/* Main Preview */}
        <div className="lg:col-span-3 bg-black rounded-3xl overflow-hidden relative shadow-2xl aspect-video border border-gray-200 dark:border-gray-800">
          <canvas ref={videoRef} className="w-full h-full object-contain" />
          
          <div className="absolute top-4 left-4 flex items-center gap-2">
            {isLive ? (
              <div className="bg-red-600 text-white text-xs font-bold px-3 py-1.5 rounded-full uppercase tracking-wider animate-pulse flex items-center gap-2 shadow-md">
                <span className="w-2 h-2 bg-white rounded-full"></span> LIVE
              </div>
            ) : (
              <div className="bg-black/60 backdrop-blur-md text-white text-xs font-semibold px-3 py-1 rounded-full border border-white/20">
                PREVIEW
              </div>
            )}
            {cameraResolution && (
              <div className="bg-[#5b3ee4]/90 text-white text-xs font-bold px-3 py-1 rounded-full shadow-sm">
                {cameraResolution}
              </div>
            )}
          </div>
        </div>

        {/* Controls Sidebar */}
        <div className="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-3xl p-6 flex flex-col gap-6 shadow-sm">
          
          <div>
            <h3 className="font-serif font-bold text-gray-950 dark:text-white mb-4 text-base">Device Settings</h3>
            
            <div className="space-y-4">
              <div>
                <div className="flex items-center justify-between mb-1.5">
                  <label className="block text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Camera</label>
                  {cameraResolution && (
                    <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-[#5b3ee4] dark:text-[#a5b4fc]">
                      {cameraResolution}
                    </span>
                  )}
                </div>
                <select 
                  className="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-2.5 text-xs sm:text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-[#5b3ee4]/20"
                  value={selectedCamera}
                  onChange={(e) => setSelectedCamera(e.target.value)}
                  disabled={isLive}
                >
                  {cameras.length === 0 && <option value="">No camera found</option>}
                  {cameras.map(cam => (
                    <option key={cam.deviceId} value={cam.deviceId}>{cam.label || `Camera ${cam.deviceId.slice(0,5)}`}</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-1.5">Microphone</label>
                <select 
                  className="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-2.5 text-xs sm:text-sm text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-[#5b3ee4]/20"
                  value={selectedMic}
                  onChange={(e) => setSelectedMic(e.target.value)}
                  disabled={isLive}
                >
                  {mics.length === 0 && <option value="">No microphone found</option>}
                  {mics.map(mic => (
                    <option key={mic.deviceId} value={mic.deviceId}>{mic.label || `Mic ${mic.deviceId.slice(0,5)}`}</option>
                  ))}
                </select>
              </div>
            </div>
          </div>

          <div className="pt-6 border-t border-gray-100 dark:border-gray-800">
            <h3 className="font-serif font-bold text-gray-950 dark:text-white mb-1.5 text-base">Public Link</h3>
            <p className="text-xs text-gray-500 dark:text-gray-400 mb-3">Share this link with your audience:</p>
            
            {(() => {
              const viewerUrl = `${window.location.origin}/stream/${user?.name || user?.username}`;
              return (
                <div className="flex flex-col gap-2">
                  <div className="bg-gray-50 dark:bg-gray-800 p-3 rounded-2xl text-xs text-gray-700 dark:text-gray-300 font-mono break-all select-all border border-gray-200 dark:border-gray-700">
                    {viewerUrl}
                  </div>
                  <div className="flex gap-2 mt-1">
                    <Button 
                      variant="outline" 
                      className="flex-1 text-xs py-1 h-8"
                      onClick={(e) => {
                        const btn = e.currentTarget;
                        navigator.clipboard.writeText(viewerUrl).then(() => {
                          const originalText = btn.innerText;
                          btn.innerText = 'Copied ✓';
                          setTimeout(() => { btn.innerText = originalText; }, 2000);
                        });
                      }}
                    >
                      Copy
                    </Button>
                    <Button 
                      variant="outline" 
                      className="flex-1 text-xs py-1 h-8"
                      onClick={() => {
                        if (navigator.share) {
                          navigator.share({
                            title: `${user?.name || user?.username}'s Live Broadcast`,
                            text: 'Join my live broadcast!',
                            url: viewerUrl
                          }).catch(console.error);
                        } else {
                          navigator.clipboard.writeText(viewerUrl).then(() => alert('Link copied to clipboard!'));
                        }
                      }}
                    >
                      Share
                    </Button>
                  </div>
                </div>
              );
            })()}
          </div>

        </div>
      </div>
    </div>
  );
}
