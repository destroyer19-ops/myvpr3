import { useState, useEffect } from 'react';
import { Loader2, CalendarDays, Users, Circle } from 'lucide-react';
import { adminApi } from '../../services/api';

const TABS = ['all', 'active', 'past'];

export default function AdminCrusades() {
  const [loading, setLoading] = useState(true);
  const [crusades, setCrusades] = useState([]);
  const [activeTab, setActiveTab] = useState('all');
  const [error, setError] = useState('');

  useEffect(() => {
    async function fetchData() {
      try {
        // adminApi doesn't have listCrusades — use raw fetch
        const res = await fetch('/api/admin/crusades', { credentials: 'include' }).then(r => r.json());
        if (res.success) setCrusades(res.data);
        else setError(res.message || 'Failed to load.');
      } catch { setError('Failed to fetch crusades.'); }
      finally { setLoading(false); }
    }
    fetchData();
  }, []);

  const filtered = crusades.filter(c => {
    if (activeTab === 'all') return true;
    if (activeTab === 'active') return c.is_active == 1;
    if (activeTab === 'past') return c.is_active == 0;
    return true;
  });

  return (
    <div className="container max-w-7xl mx-auto px-4 py-8">
      <div className="mb-6">
        <h1 className="text-2xl font-bold mb-1 text-gray-950 dark:text-white">Manage Crusades</h1>
        <p className="text-gray-500 dark:text-gray-400 text-sm">Monitor all crusades created by users across the platform.</p>
      </div>

      {error && <div className="bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900/50 text-red-600 dark:text-red-400 p-4 rounded-lg mb-6">{error}</div>}

      {/* Tabs */}
      <div className="flex overflow-x-auto border-b border-gray-200 dark:border-gray-800 mb-6 hide-scrollbar">
        {TABS.map(tab => (
          <button key={tab} onClick={() => setActiveTab(tab)}
            className={`px-6 py-3 font-medium whitespace-nowrap border-b-2 transition-colors capitalize ${
              activeTab === tab
                ? 'border-blue-600 text-blue-600 dark:text-blue-400'
                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'
            }`}>
            {tab}
            <span className="ml-2 text-xs bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 px-2 py-0.5 rounded-full">
              {tab === 'all' ? crusades.length : crusades.filter(c => tab === 'active' ? c.is_active == 1 : c.is_active == 0).length}
            </span>
          </button>
        ))}
      </div>

      {loading ? (
        <div className="flex justify-center p-16"><Loader2 className="w-8 h-8 animate-spin text-blue-600" /></div>
      ) : (
        <div className="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm text-left">
              <thead className="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                <tr>
                  <th className="p-4 font-medium text-gray-500 dark:text-gray-400">Title</th>
                  <th className="p-4 font-medium text-gray-500 dark:text-gray-400">Owner</th>
                  <th className="p-4 font-medium text-gray-500 dark:text-gray-400">Code</th>
                  <th className="p-4 font-medium text-gray-500 dark:text-gray-400">Status</th>
                  <th className="p-4 font-medium text-gray-500 dark:text-gray-400">Views</th>
                  <th className="p-4 font-medium text-gray-500 dark:text-gray-400">Created</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                {filtered.length === 0 ? (
                  <tr><td colSpan="6" className="p-12 text-center text-gray-400 dark:text-gray-500">
                    <CalendarDays className="w-8 h-8 mx-auto mb-2 opacity-40" />
                    No {activeTab} crusades found.
                  </td></tr>
                ) : filtered.map(c => (
                  <tr key={c.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors">
                    <td className="p-4 font-medium text-gray-900 dark:text-white max-w-[200px]">
                      <span className="line-clamp-1">{c.title}</span>
                    </td>
                    <td className="p-4 text-gray-500 dark:text-gray-400">{c.owner || '—'}</td>
                    <td className="p-4">
                      <span className="font-mono text-xs bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-200 px-2 py-1 rounded">{c.crusade_code}</span>
                    </td>
                    <td className="p-4">
                      {c.is_active == 1 ? (
                        <span className="flex items-center gap-1.5 text-red-600 dark:text-red-400 font-semibold text-xs">
                          <Circle className="w-2 h-2 fill-current animate-pulse" /> LIVE
                        </span>
                      ) : (
                        <span className="text-gray-400 dark:text-gray-500 text-xs font-medium">ENDED</span>
                      )}
                    </td>
                    <td className="p-4">
                      <span className="flex items-center gap-1.5 text-gray-600 dark:text-gray-300">
                        <Users className="w-4 h-4 text-gray-400" /> {Number(c.total_views).toLocaleString()}
                      </span>
                    </td>
                    <td className="p-4 text-gray-500 dark:text-gray-400 whitespace-nowrap">{new Date(c.created_at).toLocaleDateString()}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
}
