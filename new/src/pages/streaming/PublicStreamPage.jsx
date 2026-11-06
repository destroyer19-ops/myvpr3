import React, { useEffect, useState, useRef } from 'react';
import { useParams } from 'react-router-dom';
import { Settings, Check } from 'lucide-react';

export default function PublicStreamPage() {
  const { username } = useParams();
  const [streamData, setStreamData] = useState(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  const videoRef = useRef(null);
  const playerRef = useRef(null);

  // Player quality state
  const [qualities, setQualities] = useState([]);
  const [activeQuality, setActiveQuality] = useState(null);
  const [isAuto, setIsAuto] = useState(true);
  const [showQualityMenu, setShowQualityMenu] = useState(false);

  useEffect(() => {
    async function fetchStream() {
      try {
        const res = await fetch(`/api/streaming/watch/${username}`);
        const json = await res.json();
        if (json.success) {
          setStreamData(json.data);
        } else {
          setError(json.message || 'Stream not found.');
        }
      } catch (err) {
        setError('Failed to fetch stream details.');
      } finally {
        setLoading(false);
      }
    }
    fetchStream();
  }, [username]);

  // Initialize IVS Player once we have the playback_url
  useEffect(() => {
    if (!streamData?.playback_url || !videoRef.current) return;

    const loadPlayer = async () => {
      // Check if IVS player script is already loaded
      if (!window.IVSPlayer) {
        await new Promise((resolve, reject) => {
          const script = document.createElement('script');
          script.src = 'https://player.live-video.net/1.31.0/amazon-ivs-player.min.js';
          script.onload = resolve;
          script.onerror = reject;
          document.body.appendChild(script);
        });
      }

      if (window.IVSPlayer.isPlayerSupported) {
        const player = window.IVSPlayer.create();
        player.attachHTMLVideoElement(videoRef.current);

        // Quality and state listeners
        const { PlayerEventType, PlayerState } = window.IVSPlayer;
        player.addEventListener(PlayerEventType.QUALITY_CHANGED, (quality) => {
          setActiveQuality(quality);
        });
        player.addEventListener(PlayerEventType.STATE_CHANGED, (state) => {
          if (state === PlayerState.PLAYING) {
            try {
              const qList = player.getQualities() || [];
              setQualities(qList);
              setIsAuto(player.isAutoQualityMode());
              setActiveQuality(player.getQuality());
            } catch (qErr) {
              console.warn("Could not query player qualities:", qErr);
            }
          }
        });

        player.load(streamData.playback_url);
        player.play();
        playerRef.current = player;
      }
    };

    loadPlayer();

    return () => {
      if (playerRef.current) {
        playerRef.current.delete();
        playerRef.current = null;
      }
    };
  }, [streamData]);

  const handleSelectQuality = (q) => {
    const player = playerRef.current;
    if (!player) return;

    if (q === 'auto') {
      player.setAutoQualityMode(true);
      setIsAuto(true);
      setActiveQuality(player.getQuality());
    } else {
      player.setAutoQualityMode(false);
      player.setQuality(q, true);
      setIsAuto(false);
      setActiveQuality(q);
    }
    setShowQualityMenu(false);
  };

  // Initialize N-Gage Chat
  useEffect(() => {
    if (!streamData?.chat_room_id) return;

    const loadNgage = async () => {
      // Load script if not present
      if (!window.Ngage) {
        await new Promise((resolve, reject) => {
          const script = document.createElement('script');
          script.src = '/N-gage/Ngage/Ngage.js';
          script.onload = resolve;
          script.onerror = reject;
          document.body.appendChild(script);
        });
      }
      
      // Load CSS if not present
      if (!document.querySelector('link[href="/N-gage/Ngage/Ngage.css"]')) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = '/N-gage/Ngage/Ngage.css';
        document.head.appendChild(link);
      }

      // We use a dummy guest user for public viewer for now, or you can pass actual logged-in user state
      const myCommentBox = new window.Ngage({
        anchor: "#ngage-chat-container",
        id: streamData.chat_room_id,
        userID: "guest_" + Math.floor(Math.random() * 10000), 
        username: "Viewer",
        endpoint: "/N-gage/Ngage/Ngage.php"
      });
      
      myCommentBox.init();
    };

    loadNgage();
  }, [streamData]);

  if (loading) {
    return <div className="flex justify-center p-12 text-white">Loading stream...</div>;
  }

  if (error || !streamData) {
    return (
      <div className="flex flex-col items-center justify-center p-12 text-center text-gray-950 dark:text-white min-h-[60vh]">
        <div className="text-6xl mb-4">📺</div>
        <h1 className="font-serif text-2xl font-bold mb-2">Stream Offline</h1>
        <p className="text-gray-500 dark:text-gray-400">{error || 'This user is not currently broadcasting.'}</p>
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto p-0 lg:p-4 h-[calc(100vh-64px)] flex flex-col lg:flex-row gap-4">
      {/* Main Video Section */}
      <div className="flex-1 bg-black flex flex-col lg:rounded-2xl overflow-hidden relative shadow-2xl">
        <video 
          ref={videoRef} 
          className="w-full h-full object-contain bg-black" 
          controls 
          playsInline
        />
        
        {/* Stream Info Overlay / Banner */}
        <div className="absolute top-0 left-0 right-0 bg-gradient-to-b from-black/80 to-transparent p-4 flex justify-between items-start pointer-events-none z-10">
          <div>
            <div className="inline-block bg-red-600 text-white text-xs font-bold px-2.5 py-1 rounded-full uppercase tracking-wider mb-2 animate-pulse shadow-sm">
              LIVE
            </div>
            <h1 className="text-xl font-bold text-white drop-shadow-md">{username}'s Broadcast</h1>
          </div>

          {/* Quality Selector */}
          {qualities.length > 0 && (
            <div className="relative pointer-events-auto">
              <button
                type="button"
                onClick={() => setShowQualityMenu(!showQualityMenu)}
                className="bg-black/70 hover:bg-black/90 text-white text-xs font-semibold px-3 py-1.5 rounded-full border border-white/20 backdrop-blur-md flex items-center gap-1.5 transition-all shadow-md cursor-pointer"
                title="Select Playback Quality"
              >
                <Settings className="w-3.5 h-3.5 text-blue-400" />
                <span>{isAuto ? `Auto (${activeQuality?.name || 'ABR'})` : (activeQuality?.name || 'HD')}</span>
              </button>

              {showQualityMenu && (
                <div className="absolute right-0 mt-1.5 w-36 bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl shadow-2xl py-1 text-xs text-gray-900 dark:text-white z-30 animate-in fade-in duration-150">
                  <button
                    type="button"
                    onClick={() => handleSelectQuality('auto')}
                    className={`w-full text-left px-3 py-2 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors ${isAuto ? 'text-[#5b3ee4] dark:text-[#818cf8] font-bold' : 'text-gray-700 dark:text-gray-300'}`}
                  >
                    <span>Auto (ABR)</span>
                    {isAuto && <Check className="w-3.5 h-3.5" />}
                  </button>
                  {qualities.map((q) => {
                    const isSelected = !isAuto && activeQuality?.name === q.name;
                    return (
                      <button
                        key={q.name}
                        type="button"
                        onClick={() => handleSelectQuality(q)}
                        className={`w-full text-left px-3 py-2 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors ${isSelected ? 'text-[#5b3ee4] dark:text-[#818cf8] font-bold' : 'text-gray-700 dark:text-gray-300'}`}
                      >
                        <span>{q.name}</span>
                        {isSelected && <Check className="w-3.5 h-3.5" />}
                      </button>
                    );
                  })}
                </div>
              )}
            </div>
          )}
        </div>
      </div>

      {/* Engagement / Chat Sidebar */}
      <div className="w-full lg:w-96 bg-white dark:bg-gray-900 lg:rounded-2xl border border-gray-100 dark:border-gray-800 flex flex-col shadow-sm shrink-0">
        <div className="p-4 border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-850">
          <h2 className="font-bold text-gray-950 dark:text-white">Live Chat</h2>
          <p className="text-xs text-gray-500 dark:text-gray-400">Room: {streamData.chat_room_id}</p>
        </div>
        
        <div className="flex-1 bg-white dark:bg-gray-950" id="ngage-chat-container">
          {/* Ngage dynamically injects chat here */}
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
