import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { Loader2, ArrowLeft, Copy, Check, Users, ShieldAlert, ExternalLink } from 'lucide-react';
import { meetingsApi } from '../services/api';

export default function MeetingRoomPage() {
  const { code } = useParams();
  const [loading, setLoading] = useState(true);
  const [meeting, setMeeting] = useState(null);
  const [error, setError] = useState('');
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    async function initMeeting() {
      setLoading(true);
      try {
        const meetingRes = await meetingsApi.getByCode(code);
        if (meetingRes?.success) {
          setMeeting(meetingRes.data);
        } else {
          setError(meetingRes?.message || 'Meeting not found or you lack permission to join.');
        }
      } catch (err) {
        setError('Failed to load meeting room.');
        console.error(err);
      } finally {
        setLoading(false);
      }
    }

    initMeeting();
  }, [code]);

  const handleCopyLink = () => {
    const url = window.location.href;
    navigator.clipboard.writeText(url);
    setCopied(true);
    setTimeout(() => setCopied(false), 3000);
  };

  if (loading) {
    return (
      <div className="flex flex-col justify-center items-center min-h-[70vh]">
        <div className="w-10 h-10 border-4 border-gray-100 dark:border-gray-800 border-t-[#5b3ee4] rounded-full animate-spin mb-4" />
        <p className="text-gray-500 dark:text-gray-400 font-medium text-sm">Connecting to virtual room...</p>
      </div>
    );
  }

  if (error || !meeting) {
    return (
      <div className="max-w-2xl mx-auto px-4 py-16 text-center animate-in fade-in duration-300">
        <div className="bg-white dark:bg-gray-900 p-8 sm:p-12 rounded-3xl border border-gray-100 dark:border-gray-800 shadow-sm">
          <ShieldAlert className="w-12 h-12 text-rose-500 mx-auto mb-4" />
          <h2 className="font-serif text-2xl font-bold text-gray-950 dark:text-white mb-2">Access Denied</h2>
          <p className="text-sm text-gray-500 dark:text-gray-400 mb-6">{error}</p>
          <Link to="/dashboard" className="inline-flex items-center gap-2 px-6 py-2.5 bg-[#5b3ee4] hover:bg-[#4f2bee] text-white rounded-full text-sm font-semibold transition-all">
            ← Return to Dashboard
          </Link>
        </div>
      </div>
    );
  }

  const roomUrl = meeting.daily_room_url || `https://myvpr.daily.co/${code}`;

  return (
    <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6 pb-28 lg:pb-16 flex flex-col h-[calc(100dvh-120px)] animate-in fade-in duration-300">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
        <div className="flex items-center gap-3.5">
          <Link to="/dashboard" className="w-9 h-9 sm:w-10 sm:h-10 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-full flex items-center justify-center transition-colors text-gray-700 dark:text-gray-300">
            <ArrowLeft className="w-4 h-4 sm:w-5 sm:h-5" />
          </Link>
          <div>
            <div className="flex items-center gap-2">
              <h1 className="font-serif text-xl sm:text-2xl font-bold text-gray-950 dark:text-white flex items-center gap-2">
                {meeting.title || 'Virtual Praise Room'}
              </h1>
              <span className="px-2.5 py-0.5 bg-indigo-50 text-[#5b3ee4] dark:bg-indigo-950/60 dark:text-[#a5b4fc] text-[10px] font-bold uppercase rounded-full tracking-wider">
                Live Room
              </span>
            </div>
            <p className="text-[11px] text-gray-400 uppercase tracking-wider font-mono">
              Code: {code}
            </p>
          </div>
        </div>

        <div className="flex items-center gap-2.5">
          <a
            href={roomUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="flex items-center justify-center gap-1.5 px-4 py-2 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-750 rounded-full transition-colors text-xs font-semibold text-gray-700 dark:text-gray-300"
            title="Open in full standalone window"
          >
            <ExternalLink className="w-3.5 h-3.5" />
            <span className="hidden sm:inline">Popout Window</span>
          </a>

          <button 
            onClick={handleCopyLink}
            className="flex items-center justify-center gap-1.5 px-5 py-2 bg-[#5b3ee4] hover:bg-[#4f2bee] text-white rounded-full transition-all text-xs font-semibold shadow-xs active:scale-95 cursor-pointer"
          >
            {copied ? (
              <>
                <Check className="w-3.5 h-3.5 text-white" />
                <span>Copied Link</span>
              </>
            ) : (
              <>
                <Copy className="w-3.5 h-3.5" />
                <span>Share Invite</span>
              </>
            )}
          </button>
        </div>
      </div>

      {/* Room Container */}
      <div className="flex-1 bg-black rounded-3xl overflow-hidden shadow-2xl border border-gray-100 dark:border-gray-800 relative">
        <iframe
          src={roomUrl}
          allow="camera; microphone; fullscreen; display-capture; autoplay"
          className="absolute inset-0 w-full h-full border-0"
          title={meeting.title || 'Meeting Room'}
        />
      </div>
    </div>
  );
}
