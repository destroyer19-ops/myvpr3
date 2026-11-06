import React, { useEffect, useState, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/FormElements';
import { useAuth } from '@/context/AuthContext';
import { request } from '../../services/api';
import { Stage, LocalStageStream } from 'amazon-ivs-web-broadcast';
import { MonitorUp, MonitorOff, Camera } from 'lucide-react';

export default function RealtimeStudioPage() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [setupState, setSetupState] = useState('initial'); // 'initial', 'requesting', 'ready', 'error'
  const [isLive, setIsLive] = useState(false);
  const [isScreenSharing, setIsScreenSharing] = useState(false);
  const [error, setError] = useState('');
  
  const videoRef = useRef(null);
  const stageRef = useRef(null);
  
  const cameraTrackRef = useRef(null);
  const screenTrackRef = useRef(null);
  const audioTrackRef = useRef(null);
  
  const localAudioStreamRef = useRef(null);
  const localVideoStreamRef = useRef(null);

  useEffect(() => {
    return () => {
      if (stageRef.current) {
        stageRef.current.leave();
        stageRef.current = null;
      }
      stopTracks();
    };
  }, []);

  const stopTracks = () => {
    if (cameraTrackRef.current) { cameraTrackRef.current.stop(); cameraTrackRef.current = null; }
    if (screenTrackRef.current) { screenTrackRef.current.stop(); screenTrackRef.current = null; }
    if (audioTrackRef.current) { audioTrackRef.current.stop(); audioTrackRef.current = null; }
  };

  const startSetup = async () => {
    setSetupState('requesting');
    setError('');
    
    try {
      const initialStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
      cameraTrackRef.current = initialStream.getVideoTracks()[0];
      audioTrackRef.current = initialStream.getAudioTracks()[0];

      localVideoStreamRef.current = new LocalStageStream(cameraTrackRef.current);
      localAudioStreamRef.current = new LocalStageStream(audioTrackRef.current);
      
      if (videoRef.current) {
        videoRef.current.srcObject = new MediaStream([cameraTrackRef.current]);
      }
      
      setSetupState('ready');
    } catch (err) {
      console.error('Error getting media:', err);
      setSetupState('error');
      setError('Camera and microphone access is blocked. Allow access in your browser settings and try again.');
    }
  };

  const toggleScreenShare = async () => {
    try {
      if (isScreenSharing) {
        // Stop screen share, revert to camera
        if (screenTrackRef.current) {
          screenTrackRef.current.stop();
          screenTrackRef.current = null;
        }
        
        // Ensure camera track is alive, otherwise re-request
        if (!cameraTrackRef.current || cameraTrackRef.current.readyState === 'ended') {
           const stream = await navigator.mediaDevices.getUserMedia({ video: true });
           cameraTrackRef.current = stream.getVideoTracks()[0];
        }

        if (videoRef.current) {
          videoRef.current.srcObject = new MediaStream([cameraTrackRef.current]);
        }
        
        localVideoStreamRef.current = new LocalStageStream(cameraTrackRef.current);
        
        if (stageRef.current && isLive) {
          stageRef.current.refreshStrategy();
        }
        setIsScreenSharing(false);
      } else {
        // Start screen share
        const screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: false });
        screenTrackRef.current = screenStream.getVideoTracks()[0];
        
        // Listen for user stopping screen share via browser UI
        screenTrackRef.current.onended = () => {
           toggleScreenShare(); // revert to camera
        };

        if (videoRef.current) {
          videoRef.current.srcObject = new MediaStream([screenTrackRef.current]);
        }
        
        localVideoStreamRef.current = new LocalStageStream(screenTrackRef.current);
        
        if (stageRef.current && isLive) {
          stageRef.current.refreshStrategy();
        }
        setIsScreenSharing(true);
      }
    } catch (err) {
      console.error('Failed to toggle screen share:', err);
      // User likely cancelled the screen share prompt, just silently fail
    }
  };

  const toggleBroadcast = async () => {
    if (isLive) {
      if (stageRef.current) {
        stageRef.current.leave();
        stageRef.current = null;
      }
      await request('/realtime/end', { method: 'POST' });
      setIsLive(false);
    } else {
      try {
        const resp = await request('/realtime/provision', {
          method: 'POST',
          body: JSON.stringify({ role: 'broadcaster' })
        });
        
        if (!resp.success || !resp.token) {
          throw new Error('Failed to provision realtime stream.');
        }

        const strategy = {
          stageStreamsToPublish() {
            return [localVideoStreamRef.current, localAudioStreamRef.current];
          },
          shouldPublishParticipant() {
            return true;
          },
          shouldSubscribeToParticipant() {
            // Broadcaster doesn't subscribe to viewers in this one-way test
            return false;
          }
        };

        const stage = new Stage(resp.token, strategy);
        stageRef.current = stage;
        
        await stage.join();
        setIsLive(true);
      } catch (err) {
        console.error('Failed to start realtime broadcast:', err);
        alert(`Failed to start realtime broadcast: ${err.message}`);
      }
    }
  };

  return (
    <div className="max-w-6xl mx-auto p-4 lg:p-8 relative animate-in fade-in duration-300 pb-28 lg:pb-16 text-gray-950 dark:text-white">
      {setupState !== 'ready' && (
        <div className="absolute inset-0 z-50 flex flex-col items-center justify-center p-4 bg-black/60 dark:bg-black/80 backdrop-blur-sm rounded-3xl">
          <div className="max-w-md w-full bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-3xl p-8 text-center shadow-2xl">
            <h2 className="font-serif text-2xl font-bold text-gray-950 dark:text-white mb-3">Real-Time Studio Setup</h2>
            {setupState === 'initial' && (
              <>
                <p className="text-gray-600 dark:text-gray-400 mb-8 text-sm leading-relaxed">
                  This is the experimental WebRTC Real-Time Studio.
                </p>
                <Button onClick={startSetup} className="w-full bg-[#5b3ee4] hover:bg-[#4f2bee] py-4 text-base font-bold shadow-md">
                  Enable Camera & Microphone
                </Button>
              </>
            )}
            {setupState === 'requesting' && (
              <div className="text-gray-600 dark:text-gray-400 text-sm py-6">Requesting permissions...</div>
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
          <h1 className="font-serif text-3xl sm:text-4xl font-bold text-gray-950 dark:text-white mb-1.5 tracking-tight">Real-Time Studio (Test)</h1>
          <p className="text-gray-600 dark:text-gray-400 text-sm sm:text-base">Low-latency WebRTC broadcasting.</p>
        </div>
        <div className="flex flex-wrap items-center gap-3">
          <Button variant="outline" onClick={() => navigate('/dashboard/streaming')} disabled={isLive}>
            Exit Studio
          </Button>
          <Button 
            variant="outline"
            className="flex items-center gap-2"
            onClick={toggleScreenShare}
            disabled={setupState !== 'ready'}
          >
            {isScreenSharing ? <><Camera size={18} /> Switch to Camera</> : <><MonitorUp size={18} /> Share Screen</>}
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
        <div className="lg:col-span-3 bg-black rounded-3xl overflow-hidden relative shadow-2xl aspect-video border border-gray-200 dark:border-gray-800">
          <video ref={videoRef} autoPlay playsInline muted className="w-full h-full object-contain" />
          {isLive && (
            <div className="absolute top-4 left-4 bg-red-600 text-white text-xs font-bold px-3 py-1.5 rounded-full uppercase tracking-wider animate-pulse flex items-center gap-2 shadow-lg">
              <span className="w-2 h-2 bg-white rounded-full"></span> LIVE
            </div>
          )}
          {isScreenSharing && (
            <div className="absolute top-4 right-4 bg-black/70 backdrop-blur-md text-white text-xs font-bold px-3 py-1.5 rounded-full uppercase tracking-wider flex items-center gap-2">
              <MonitorUp size={14} /> SCREEN SHARING
            </div>
          )}
        </div>
        <div className="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-3xl p-6 flex flex-col gap-6 shadow-sm">
          <div className="pt-2">
            <h3 className="font-serif font-bold text-gray-950 dark:text-white mb-2 text-base">Public Link</h3>
            {(() => {
              const viewerUrl = `${window.location.origin}/watch/realtime-test/${user?.username}`;
              return (
                <div className="flex flex-col gap-2">
                  <div className="bg-gray-50 dark:bg-gray-800 p-3 rounded-2xl text-xs text-gray-700 dark:text-gray-300 font-mono break-all select-all border border-gray-200 dark:border-gray-700">
                    {viewerUrl}
                  </div>
                  <Button 
                    variant="outline" 
                    className="w-full text-xs py-2 mt-1"
                    onClick={() => {
                      navigator.clipboard.writeText(viewerUrl);
                      alert('Link copied to clipboard!');
                    }}
                  >
                    Copy Link
                  </Button>
                </div>
              );
            })()}
          </div>
          
          <div className="pt-6 border-t border-gray-100 dark:border-gray-800">
             <h3 className="font-semibold text-white mb-2 text-sm uppercase tracking-wider">Status</h3>
             <div className="space-y-3">
               <div className="flex justify-between items-center text-sm">
                 <span className="text-slate-400">Connection</span>
                 <span className={isLive ? 'text-green-400 font-medium' : 'text-slate-500'}>
                   {isLive ? 'Connected' : 'Offline'}
                 </span>
               </div>
               <div className="flex justify-between items-center text-sm">
                 <span className="text-slate-400">Video Source</span>
                 <span className="text-slate-300">
                   {isScreenSharing ? 'Screen' : 'Camera'}
                 </span>
               </div>
             </div>
          </div>
        </div>
      </div>
    </div>
  );
}
