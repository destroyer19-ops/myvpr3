import React, { useEffect, useState, useRef } from 'react';
import { useParams } from 'react-router-dom';
import { Stage, SubscribeType } from 'amazon-ivs-web-broadcast';
import { request } from '../../services/api';

export default function RealtimeViewerPage() {
  const { username } = useParams();
  const [error, setError] = useState('');
  const [isPlaying, setIsPlaying] = useState(false);
  const [needsInteraction, setNeedsInteraction] = useState(false);
  const containerRef = useRef(null);
  const stageRef = useRef(null);

  useEffect(() => {
    let activeStage = null;
    
    const initViewer = async () => {
      try {
        const resp = await request('/realtime/provision', {
          method: 'POST',
          body: JSON.stringify({ role: 'viewer', broadcaster_username: username })
        });
        
        if (!resp.success || !resp.token) {
          setError('Stream not found or offline.');
          return;
        }

        const strategy = {
          stageStreamsToPublish() {
            return [];
          },
          shouldPublishParticipant() {
            return false;
          },
          shouldSubscribeToParticipant() {
            return SubscribeType.AUDIO_VIDEO;
          }
        };

        const stage = new Stage(resp.token, strategy);
        activeStage = stage;
        stageRef.current = stage;

        stage.on('stageParticipantStreamsAdded', (participant, streams) => {
          let videoEl = document.getElementById(`video-${participant.id}`);
          if (!videoEl) {
            videoEl = document.createElement('video');
            videoEl.id = `video-${participant.id}`;
            videoEl.autoplay = true;
            videoEl.playsInline = true;
            videoEl.className = 'w-full h-full object-contain bg-black';
            if (containerRef.current) {
              containerRef.current.appendChild(videoEl);
            }
          }
          
          const mediaStream = new MediaStream();
          streams.forEach(stream => {
            mediaStream.addTrack(stream.mediaStreamTrack);
          });
          
          videoEl.srcObject = mediaStream;
          
          videoEl.play().then(() => {
            setIsPlaying(true);
            setNeedsInteraction(false);
          }).catch(err => {
            console.warn('Autoplay failed, user interaction needed', err);
            setNeedsInteraction(true);
            videoEl.muted = true;
            videoEl.play().then(() => {
               setIsPlaying(true);
            }).catch(e => console.error('Muted autoplay failed', e));
          });
        });
        
        stage.on('stageParticipantStreamsRemoved', (participant) => {
          const videoEl = document.getElementById(`video-${participant.id}`);
          if (videoEl) {
            videoEl.remove();
          }
        });

        await stage.join();
        
      } catch (err) {
        console.error('Failed to initialize viewer', err);
        setError('Stream not found or offline.');
      }
    };

    initViewer();

    return () => {
      if (activeStage) {
        activeStage.leave();
      }
    };
  }, [username]);

  const handleUnmute = () => {
    if (containerRef.current) {
      const videos = containerRef.current.querySelectorAll('video');
      videos.forEach(v => {
        v.muted = false;
        v.play().catch(e => console.error('Play failed after interaction', e));
      });
      setNeedsInteraction(false);
    }
  };

  return (
    <div className="max-w-7xl mx-auto p-0 lg:p-4 h-[calc(100vh-64px)] flex flex-col lg:flex-row gap-4">
      {/* Main Video Section */}
      <div className="flex-1 bg-black flex flex-col lg:rounded-xl overflow-hidden relative shadow-2xl">
        <div className="absolute inset-0 z-0" ref={containerRef}></div>
        
        {/* Stream Info Overlay / Banner */}
        <div className="absolute top-0 left-0 right-0 z-10 bg-gradient-to-b from-black/80 to-transparent p-4 flex justify-between items-start pointer-events-none">
          <div>
            {!error && (
              <div className="inline-block bg-red-600 text-white text-xs font-bold px-2 py-1 rounded uppercase tracking-wider mb-2 animate-pulse shadow-sm">
                REAL-TIME LIVE
              </div>
            )}
            <h1 className="text-xl font-bold text-white drop-shadow-md">{username}'s Broadcast</h1>
          </div>
        </div>

        {error && (
          <div className="absolute inset-0 z-20 flex items-center justify-center">
            <div className="text-white bg-red-600/80 px-6 py-3 rounded-lg backdrop-blur">
              {error}
            </div>
          </div>
        )}
        
        {needsInteraction && !error && (
          <div className="absolute inset-0 z-20 flex items-center justify-center bg-black/50">
            <button 
              onClick={handleUnmute}
              className="bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-8 rounded-full text-xl shadow-2xl transition-transform hover:scale-105"
            >
              Click to Unmute & Play
            </button>
          </div>
        )}
      </div>

      {/* Engagement / Chat Sidebar placeholder */}
      <div className="w-full lg:w-96 bg-white dark:bg-gray-900 lg:rounded-2xl border border-gray-100 dark:border-gray-800 flex flex-col shadow-sm shrink-0">
        <div className="p-4 border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-850">
          <h2 className="font-bold text-gray-950 dark:text-white">Live Chat</h2>
          <p className="text-xs text-gray-500 dark:text-gray-400">Experimental Real-Time Mode</p>
        </div>
        
        <div className="flex-1 bg-gray-50/30 dark:bg-gray-950 flex items-center justify-center p-6 text-center">
          <p className="text-gray-500 dark:text-gray-400 text-sm">
            Chat is disabled in this Real-Time comparison view. Focus on latency and video quality testing.
          </p>
        </div>

        {/* Giving / Salvation Action Buttons */}
        <div className="p-4 border-t border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-850 flex gap-2">
          <a 
            href="/give" 
            target="_blank" 
            rel="noopener noreferrer"
            className="flex-1 bg-[#5b3ee4] hover:bg-[#4f2bee] text-white font-bold py-2.5 px-4 rounded-full transition-all text-xs text-center shadow-xs"
          >
            Give Offering
          </a>
          <a 
            href="/salvation" 
            target="_blank" 
            rel="noopener noreferrer"
            className="flex-1 bg-[#0d9488] hover:bg-[#0f766e] text-white font-bold py-2.5 px-4 rounded-full transition-all text-xs text-center shadow-xs"
          >
            Salvation
          </a>
        </div>
      </div>
    </div>
  );
}
