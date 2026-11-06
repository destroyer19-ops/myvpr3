import { useState, useEffect } from 'react';
import { Loader2, Tv, Share2, Circle, Radio, ExternalLink } from 'lucide-react';
import { streamsApi } from '../services/api';

export default function LiveTvPage() {
  const [loading, setLoading] = useState(true);
  const [stream, setStream] = useState(null);
  const [error, setError] = useState('');
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    async function fetchLiveStream() {
      setLoading(true);
      try {
        const res = await streamsApi.getLive();
        if (res.success && res.streams && res.streams.length > 0) {
          // Take the most recent active live stream
          setStream(res.streams[0]);
        } else {
          setError('There is no active live broadcast at the moment. Please check back later.');
        }
      } catch (err) {
        setError('Failed to load the live stream. Please try again.');
        console.error(err);
      } finally {
        setLoading(false);
      }
    }
    fetchLiveStream();
  }, []);

  const handleShare = () => {
    if (navigator.share) {
      navigator.share({
        title: stream?.title || 'Virtual Praise Room Live TV',
        url: window.location.href,
      }).catch(() => {});
    } else {
      navigator.clipboard.writeText(window.location.href);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    }
  };

  if (loading) {
    return (
      <div className="flex flex-col justify-center items-center min-h-[70vh]">
        <div className="w-10 h-10 border-4 border-gray-100 dark:border-gray-800 border-t-[#5b3ee4] rounded-full animate-spin mb-4" />
        <p className="text-gray-500 dark:text-gray-400 font-medium text-sm">Tuning in to Live TV...</p>
      </div>
    );
  }

  if (error || !stream) {
    return (
      <div className="max-w-3xl mx-auto px-4 py-16 text-center animate-in fade-in duration-300">
        <div className="bg-white dark:bg-gray-900 p-10 sm:p-14 rounded-3xl border border-gray-100 dark:border-gray-800 shadow-sm">
          <div className="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto mb-4 text-gray-400">
            <Tv className="w-8 h-8 opacity-60" />
          </div>
          <h2 className="font-serif text-3xl font-bold text-gray-950 dark:text-white mb-2">Off Air</h2>
          <p className="text-gray-500 dark:text-gray-400 max-w-md mx-auto text-sm leading-relaxed">
            {error || 'There is no active live broadcast at the moment. Please check back later.'}
          </p>
        </div>
      </div>
    );
  }

  const isYouTube = stream.stream_url?.includes('youtube.com') || stream.stream_url?.includes('youtu.be');
  let youtubeEmbedUrl = '';
  if (isYouTube) {
    let videoId = '';
    if (stream.stream_url.includes('youtube.com/watch')) {
      const urlParams = new URLSearchParams(stream.stream_url.split('?')[1]);
      videoId = urlParams.get('v');
    } else if (stream.stream_url.includes('youtu.be/')) {
      videoId = stream.stream_url.split('youtu.be/')[1].split('?')[0];
    }
    youtubeEmbedUrl = `https://www.youtube.com/embed/${videoId}?autoplay=1&live=1`;
  }

  return (
    <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-8 pb-28 lg:pb-16 animate-in fade-in duration-300">
      
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 sm:mb-8">
        <div>
          <div className="flex items-center gap-3 mb-1">
            <h1 className="font-serif text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight text-gray-950 dark:text-white">
              Live TV
            </h1>
            <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#fdf0f4] dark:bg-rose-950/40 text-[#e11d48] dark:text-rose-400 text-xs font-bold uppercase tracking-wide">
              <span className="w-2 h-2 rounded-full bg-[#e11d48] animate-pulse" />
              On Air
            </span>
          </div>
          <p className="text-gray-500 dark:text-gray-400 text-sm sm:text-base">
            Broadcasting 24/7. Join the global stream with believers everywhere.
          </p>
        </div>
        
        <button 
          onClick={handleShare}
          className="self-start sm:self-auto shrink-0 inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-[#5b3ee4] hover:bg-[#4f2bee] text-white rounded-full transition-all text-sm font-semibold shadow-sm active:scale-95 cursor-pointer"
        >
          <Share2 className="w-4 h-4" />
          {copied ? 'Link Copied!' : 'Share Live Stream'}
        </button>
      </div>

      {/* Video Container */}
      <div className="bg-white dark:bg-gray-900 rounded-3xl overflow-hidden shadow-xl border border-gray-100 dark:border-gray-800">
        <div className="relative aspect-video bg-black">
          {isYouTube ? (
            <iframe
              src={youtubeEmbedUrl}
              title={stream.title}
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
              src={stream.stream_url}
            >
              Your browser does not support the video tag.
            </video>
          )}
        </div>
        
        <div className="p-6 sm:p-8">
          <h2 className="font-serif text-2xl sm:text-3xl font-bold text-gray-950 dark:text-white mb-3">
            {stream.title}
          </h2>
          
          {stream.description ? (
            <div className="text-gray-600 dark:text-gray-300 text-sm sm:text-base leading-relaxed">
              <p className="whitespace-pre-wrap">{stream.description}</p>
            </div>
          ) : (
            <p className="text-gray-400 dark:text-gray-500 text-sm italic">
              24/7 continuous worship, prayer, and ministry broadcast.
            </p>
          )}
        </div>
      </div>

    </div>
  );
}
