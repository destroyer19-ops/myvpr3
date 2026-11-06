import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { meetingsApi } from '../services/api';
import { Loader2, CalendarDays } from 'lucide-react';

export default function CreateMeetingPage() {
  const navigate = useNavigate();
  const [title, setTitle] = useState('');
  const [creating, setCreating] = useState(false);
  const [error, setError] = useState('');

  const handleCreate = async (e) => {
    e.preventDefault();
    setCreating(true);
    setError('');
    try {
      const res = await meetingsApi.create({ title });
      if (res.success) {
        navigate(`/meeting/${res.meeting_code || res.data?.code}`);
      } else {
        setError(res.message || 'Failed to create meeting.');
      }
    } catch (err) {
      setError('An error occurred. Make sure you are logged in.');
    } finally {
      setCreating(false);
    }
  };

  return (
    <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-16 pb-28 lg:pb-16 flex justify-center animate-in fade-in duration-300">
      <div className="w-full max-w-md p-6 sm:p-8 bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-3xl shadow-sm">
        <div className="flex items-center gap-3.5 mb-6">
          <div className="p-3 bg-purple-100 dark:bg-purple-950/60 rounded-2xl text-[#5b3ee4] dark:text-[#a5b4fc]">
            <CalendarDays className="w-6 h-6" />
          </div>
          <div>
            <h1 className="font-serif text-2xl font-bold text-gray-950 dark:text-white">Create Meeting</h1>
            <p className="text-xs text-gray-400">Launch a live prayer or study session</p>
          </div>
        </div>
        
        {error && (
          <div className="mb-5 p-3.5 bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 rounded-2xl text-xs">
            {error}
          </div>
        )}

        <form onSubmit={handleCreate} className="space-y-4">
          <div>
            <label className="block text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-1.5">Meeting Title</label>
            <input
              type="text"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              required
              placeholder="e.g. Wednesday Bible Study"
              className="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-950 dark:text-white px-4 py-3 rounded-2xl outline-none focus:ring-2 focus:ring-[#5b3ee4]/20 transition-all text-sm"
            />
          </div>
          
          <button
            type="submit"
            disabled={creating || !title.trim()}
            className="w-full bg-[#5b3ee4] hover:bg-[#4f2bee] disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold py-3 px-6 rounded-full transition-all flex items-center justify-center gap-2 shadow-md active:scale-95 text-sm cursor-pointer mt-2"
          >
            {creating && <Loader2 className="w-4 h-4 animate-spin" />}
            {creating ? 'Creating...' : 'Create & Join Meeting'}
          </button>
        </form>
      </div>
    </div>
  );
}
