import { useState, useEffect, useRef, useCallback } from 'react';
import { useParams, useLocation, Link } from 'react-router-dom';
import { 
  ArrowLeft, Calendar, Share2, Plus, Check, Clock, Radio, 
  RotateCcw, Sparkles, CheckCircle2, Film
} from 'lucide-react';
import { videosApi, programsApi, watchHistoryApi } from '../services/api';

function formatTime(seconds) {
  if (!seconds || isNaN(seconds)) return '0:00';
  const hrs = Math.floor(seconds / 3600);
  const mins = Math.floor((seconds % 3600) / 60);
  const secs = Math.floor(seconds % 60);
  if (hrs > 0) {
    return `${hrs}:${mins < 10 ? '0' : ''}${mins}:${secs < 10 ? '0' : ''}${secs}`;
  }
  return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
}

export default function WatchPage() {
  const { id } = useParams();
  const location = useLocation();
  const [loading, setLoading] = useState(true);
  const [video, setVideo] = useState(null);
  const [isRecording, setIsRecording] = useState(false);
  const [error, setError] = useState('');
  const [inList, setInList] = useState(false);
  const [copied, setCopied] = useState(false);
  const [resumeToast, setResumeToast] = useState('');

  const videoRef = useRef(null);
  const lastSaveTimeRef = useRef(0);
  const hasResumedRef = useRef(false);

  useEffect(() => {
    async function fetchVideo() {
      setLoading(true);
      setError('');
      hasResumedRef.current = false;
      lastSaveTimeRef.current = 0;

      try {
        const isRecordingPath = location.pathname.includes('/watch/recording/');

        if (isRecordingPath) {
          const recRes = await programsApi.getRecording(id).catch(() => null);
          if (recRes?.success && recRes.data) {
            setIsRecording(true);
            setVideo(recRes.data);
            return;
          }
        }

        // Try standard video API
        const res = await videosApi.getById(id).catch(() => null);
        if (res?.success && res.data) {
          setIsRecording(false);
          setVideo(res.data);
          return;
        }

        // If not found in standard videos, try recording API
        const recRes = await programsApi.getRecording(id).catch(() => null);
        if (recRes?.success && recRes.data) {
          setIsRecording(true);
          setVideo(recRes.data);
          return;
        }

        // Offline / Demo fallback
        setIsRecording(true);
        setVideo({
          id: id || 1,
          title: 'Night of Unbroken Praise - Session 1',
          program_title: 'Praise Room',
          description: 'Deep worship and unbroken praise ministration recorded live at the Virtual Praise Room auditorium. Experience the power of worship in His presence.',
          thumbnail_url: 'https://images.unsplash.com/photo-1465847899084-d164df4dedc6?auto=format&fit=crop&w=800&q=80',
          video_url: 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
          duration_seconds: 3600,
          program_date: '2026-11-04',
          category: 'Worship & Praise',
          saved_progress: 1240
        });
      } catch (err) {
        console.error('Error fetching video/recording details:', err);
        setError('Failed to fetch video details.');
      } finally {
        setLoading(false);
      }
    }

    fetchVideo();
  }, [id, location.pathname]);

  // Persist viewing progress for recorded programs only (not live HLS streams)
  const saveViewingProgress = useCallback((currentTime, duration, completed = false) => {
    if (!isRecording || !video?.id) return;
    const progressSeconds = Math.floor(currentTime);
    const durationSeconds = Math.floor(duration || video.duration_seconds || 0);

    watchHistoryApi.saveProgress({
      recording_id: video.id,
      progress_seconds: progressSeconds,
      duration_seconds: durationSeconds,
      completed: completed ? 1 : 0
    }).catch(err => {
      console.warn('Failed to save viewing progress:', err);
    });
  }, [isRecording, video?.id, video?.duration_seconds]);

  // Auto-resume from saved progress once video metadata is ready
  const handleLoadedMetadata = () => {
    if (!videoRef.current || hasResumedRef.current) return;
    hasResumedRef.current = true;

    const savedProgress = Number(video?.saved_progress || 0);
    const duration = videoRef.current.duration || video?.duration_seconds || 0;

    // Only resume if progress is between 5s and 95% of total duration
    if (savedProgress > 5 && (savedProgress < duration * 0.95)) {
      videoRef.current.currentTime = savedProgress;
      setResumeToast(`Resumed from ${formatTime(savedProgress)}`);
      setTimeout(() => setResumeToast(''), 4000);
    }
  };

  // Throttled timeupdate progress recording
  const handleTimeUpdate = () => {
    if (!videoRef.current || !isRecording) return;
    const current = Math.floor(videoRef.current.currentTime);
    const duration = Math.floor(videoRef.current.duration || 0);

    // Save every 8 seconds of playback change
    if (Math.abs(current - lastSaveTimeRef.current) >= 8) {
      lastSaveTimeRef.current = current;
      const isCompleted = duration > 0 && (current / duration >= 0.92);
      saveViewingProgress(current, duration, isCompleted);
    }
  };

  const handlePause = () => {
    if (!videoRef.current || !isRecording) return;
    const current = Math.floor(videoRef.current.currentTime);
    const duration = Math.floor(videoRef.current.duration || 0);
    lastSaveTimeRef.current = current;
    saveViewingProgress(current, duration, duration > 0 && (current / duration >= 0.92));
  };

  const handleEnded = () => {
    if (!videoRef.current || !isRecording) return;
    const duration = Math.floor(videoRef.current.duration || 0);
    saveViewingProgress(duration, duration, true);
    setResumeToast('Completed recording!');
    setTimeout(() => setResumeToast(''), 3000);
  };

  const handleRestart = () => {
    if (videoRef.current) {
      videoRef.current.currentTime = 0;
      videoRef.current.play();
      saveViewingProgress(0, videoRef.current.duration, false);
      setResumeToast('Started from beginning');
      setTimeout(() => setResumeToast(''), 3000);
    }
  };

  const handleShare = () => {
    if (navigator.share) {
      navigator.share({
        title: video?.title || 'Virtual Praise Room',
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
      <div className="flex justify-center items-center min-h-[60vh]">
        <div className="w-10 h-10 border-4 border-gray-100 dark:border-gray-800 border-t-[#5b3ee4] rounded-full animate-spin" />
      </div>
    );
  }

  if (error || !video) {
    return (
      <div className="max-w-2xl mx-auto px-4 py-16 text-center animate-in fade-in duration-300">
        <div className="bg-white dark:bg-gray-900 p-8 sm:p-12 rounded-3xl border border-gray-100 dark:border-gray-800 shadow-sm mb-4">
          <p className="text-red-500 font-semibold mb-3">{error || 'Video not found.'}</p>
          <Link 
            to="/dashboard" 
            className="inline-flex items-center gap-2 px-6 py-2.5 bg-[#5b3ee4] text-white rounded-full text-sm font-semibold hover:bg-[#4f2bee] transition-all"
          >
            ← Back to Dashboard
          </Link>
        </div>
      </div>
    );
  }

  const isYouTube = video.video_url?.includes('youtube.com') || video.video_url?.includes('youtu.be');
  let youtubeEmbedUrl = '';
  if (isYouTube) {
    let videoId = '';
    if (video.video_url.includes('youtube.com/watch')) {
      const urlParams = new URLSearchParams(video.video_url.split('?')[1]);
      videoId = urlParams.get('v');
    } else if (video.video_url.includes('youtu.be/')) {
      videoId = video.video_url.split('youtu.be/')[1].split('?')[0];
    }
    youtubeEmbedUrl = `https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0&modestbranding=1`;
  }

  const categoryName = video.category || (
    video.category_children_outreach ? 'Children' : 
    video.category_movie_outreach ? 'Movie' : 
    video.category_ministrations ? 'Ministration' : 'Teaching'
  );

  return (
    <div className="min-h-screen bg-[#fcfcfd] dark:bg-gray-950 text-gray-950 dark:text-white animate-in fade-in duration-300 pb-28 lg:pb-16">
      
      {/* Video Player Section */}
      <div className="w-full bg-black relative aspect-video max-h-[75vh] mx-auto overflow-hidden shadow-2xl">
        
        {/* Back Button Overlay */}
        <div className="absolute top-0 left-0 right-0 p-4 sm:p-6 z-20 bg-gradient-to-b from-black/80 via-black/30 to-transparent flex justify-between items-center">
          <Link 
            to="/dashboard" 
            className="inline-flex items-center gap-2 text-white/90 hover:text-white transition-colors bg-black/40 hover:bg-black/60 backdrop-blur-md px-4 py-2 rounded-full text-sm font-medium"
          >
            <ArrowLeft className="w-4 h-4" />
            Dashboard
          </Link>
          <div className="flex items-center gap-2">
            {isRecording && video.saved_progress > 5 && (
              <button
                type="button"
                onClick={handleRestart}
                className="text-white/90 hover:text-white bg-black/40 hover:bg-black/60 backdrop-blur-md px-3.5 py-2 rounded-full text-xs sm:text-sm font-medium flex items-center gap-1.5 cursor-pointer"
                title="Restart playback from 0:00"
              >
                <RotateCcw className="w-3.5 h-3.5" />
                <span className="hidden sm:inline">Restart</span>
              </button>
            )}
            <button 
              onClick={handleShare}
              className="text-white/90 hover:text-white bg-black/40 hover:bg-black/60 backdrop-blur-md px-4 py-2 rounded-full text-sm font-medium flex items-center gap-1.5 cursor-pointer"
            >
              <Share2 className="w-4 h-4" />
              {copied ? 'Copied' : 'Share'}
            </button>
          </div>
        </div>

        {/* Resume Toast */}
        {resumeToast && (
          <div className="absolute bottom-16 sm:bottom-20 left-1/2 -translate-x-1/2 z-30 bg-black/85 backdrop-blur-md text-white text-xs sm:text-sm px-4 py-2 rounded-full shadow-xl border border-white/10 flex items-center gap-2 animate-in fade-in slide-in-from-bottom-2 duration-200">
            <CheckCircle2 className="w-4 h-4 text-emerald-400" />
            <span>{resumeToast}</span>
          </div>
        )}

        {/* Player Container */}
        <div className="absolute inset-0 flex items-center justify-center">
          {isYouTube ? (
            <iframe
              src={youtubeEmbedUrl}
              title={video.title}
              className="w-full h-full"
              frameBorder="0"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowFullScreen
            />
          ) : (
            <video 
              ref={videoRef}
              controls 
              autoPlay 
              className="w-full h-full object-contain"
              src={video.video_url}
              poster={video.thumbnail_url}
              onLoadedMetadata={handleLoadedMetadata}
              onTimeUpdate={handleTimeUpdate}
              onPause={handlePause}
              onEnded={handleEnded}
            >
              Your browser does not support the video tag.
            </video>
          )}
        </div>
      </div>

      {/* Content Metadata Section */}
      <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
        <div className="grid lg:grid-cols-12 gap-8 lg:gap-12 items-start">
          
          {/* Main Info */}
          <div className="lg:col-span-8">
            <div className="flex flex-wrap items-center gap-2.5 mb-3">
              {isRecording ? (
                <span className="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-purple-50 dark:bg-purple-950/60 text-[#5b3ee4] dark:text-[#a5b4fc] text-xs font-bold uppercase tracking-wider">
                  <Film className="w-3.5 h-3.5" /> Recorded Replay
                </span>
              ) : (
                <span className="inline-flex items-center px-3 py-1 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-[#5b3ee4] dark:text-[#a5b4fc] text-xs font-bold uppercase tracking-wider">
                  VOD Video
                </span>
              )}

              <span className="inline-flex items-center px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xs font-medium">
                {categoryName}
              </span>

              {video.program_title && (
                <span className="inline-flex items-center px-3 py-1 rounded-full bg-blue-50 dark:bg-blue-950/50 text-blue-700 dark:text-blue-300 text-xs font-medium">
                  {video.program_title}
                </span>
              )}
            </div>

            <h1 className="font-serif text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight text-gray-950 dark:text-white mb-4 leading-tight">
              {video.title}
            </h1>

            <div className="flex flex-wrap items-center gap-3 mb-8">
              <button
                type="button"
                onClick={() => setInList(!inList)}
                className="inline-flex items-center gap-2 px-6 py-3 rounded-full bg-[#5b3ee4] hover:bg-[#4f2bee] text-white font-semibold text-sm shadow-md transition-all active:scale-95 cursor-pointer"
              >
                {inList ? <Check className="w-4 h-4" /> : <Plus className="w-4 h-4" />}
                {inList ? 'Saved in Library' : 'Save to Library'}
              </button>
            </div>

            {video.description && (
              <div className="bg-white dark:bg-gray-900 rounded-3xl p-6 sm:p-8 border border-gray-100 dark:border-gray-800 shadow-sm">
                <h3 className="font-serif font-bold text-lg text-gray-950 dark:text-white mb-2">Description</h3>
                <p className="text-gray-600 dark:text-gray-300 text-sm sm:text-base leading-relaxed whitespace-pre-wrap">
                  {video.description}
                </p>
              </div>
            )}
          </div>
          
          {/* Sidebar Info Card */}
          <div className="lg:col-span-4">
            <div className="bg-white dark:bg-gray-900 rounded-3xl p-6 sm:p-8 border border-gray-100 dark:border-gray-800 shadow-sm">
              <h3 className="font-serif font-bold text-lg text-gray-950 dark:text-white mb-4">Program Details</h3>
              <div className="space-y-3.5 text-sm">
                {video.program_title && (
                  <div className="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-800">
                    <span className="text-gray-400">Program</span>
                    <span className="font-medium text-gray-800 dark:text-gray-200">{video.program_title}</span>
                  </div>
                )}
                <div className="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-800">
                  <span className="text-gray-400">{isRecording ? 'Recorded on' : 'Added on'}</span>
                  <span className="font-medium text-gray-800 dark:text-gray-200">
                    {new Date(video.program_date || video.created_at || Date.now()).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })}
                  </span>
                </div>
                <div className="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-800">
                  <span className="text-gray-400">Category</span>
                  <span className="font-medium text-gray-800 dark:text-gray-200">{categoryName}</span>
                </div>
                {video.duration_seconds > 0 && (
                  <div className="flex justify-between items-center py-2 border-b border-gray-100 dark:border-gray-800">
                    <span className="text-gray-400">Duration</span>
                    <span className="font-medium text-gray-800 dark:text-gray-200">{formatTime(video.duration_seconds)}</span>
                  </div>
                )}
                {isRecording && (
                  <div className="flex justify-between items-center py-2">
                    <span className="text-gray-400">Viewing Progress</span>
                    <span className="font-medium text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                      <Clock className="w-3.5 h-3.5" />
                      {video.saved_progress > 0 ? `${formatTime(video.saved_progress)} saved` : 'Ready to watch'}
                    </span>
                  </div>
                )}
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  );
}
