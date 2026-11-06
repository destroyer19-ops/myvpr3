import { useState, useEffect } from 'react';
import { Loader2, Heart, Globe } from 'lucide-react';
import { adminApi } from '../../services/api';

export default function AdminSalvations() {
  const [loading, setLoading] = useState(true);
  const [decisions, setDecisions] = useState([]);
  const [total, setTotal] = useState(0);
  const [error, setError] = useState('');

  useEffect(() => {
    async function fetchData() {
      try {
        const res = await adminApi.listSalvationDecisions();
        if (res.success) {
          setDecisions(res.data);
          setTotal(res.total ?? res.data.length);
        } else setError(res.message || 'Failed to load.');
      } catch { setError('Failed to fetch salvation decisions.'); }
      finally { setLoading(false); }
    }
    fetchData();
  }, []);

  return (
    <div className="container max-w-7xl mx-auto px-4 py-8">
      <div className="mb-8">
        <h1 className="text-2xl font-bold mb-1 text-gray-950 dark:text-white">Salvation Decisions</h1>
        <p className="text-gray-500 dark:text-gray-400 text-sm">Track every soul that chose to give their life to Christ through the platform.</p>
      </div>

      {error && <div className="bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900/50 text-red-600 dark:text-red-400 p-4 rounded-lg mb-6">{error}</div>}

      {/* Hero stat */}
      <div className="bg-gradient-to-br from-pink-500 to-rose-600 rounded-2xl p-8 text-white text-center mb-8 shadow-lg">
        <div className="flex justify-center mb-4">
          <div className="bg-white/20 backdrop-blur-sm p-4 rounded-full">
            <Heart className="w-10 h-10 text-white" fill="currentColor" />
          </div>
        </div>
        <p className="text-pink-100 font-medium mb-1 uppercase tracking-wider text-sm">Total Souls Won</p>
        {loading ? (
          <Loader2 className="w-8 h-8 animate-spin mx-auto mt-2" />
        ) : (
          <p className="text-6xl font-black">{total.toLocaleString()}</p>
        )}
      </div>

      {loading ? (
        <div className="flex justify-center p-16"><Loader2 className="w-8 h-8 animate-spin text-pink-600" /></div>
      ) : (
        <div className="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm text-left">
              <thead className="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                <tr>
                  <th className="p-4 font-medium text-gray-500 dark:text-gray-400">#</th>
                  <th className="p-4 font-medium text-gray-500 dark:text-gray-400">Crusade</th>
                  <th className="p-4 font-medium text-gray-500 dark:text-gray-400">IP Address</th>
                  <th className="p-4 font-medium text-gray-500 dark:text-gray-400">Date</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                {decisions.length === 0 ? (
                  <tr><td colSpan="4" className="p-12 text-center text-gray-400 dark:text-gray-500">
                    <Heart className="w-8 h-8 mx-auto mb-2 opacity-40" />
                    No salvation decisions recorded yet.
                  </td></tr>
                ) : decisions.map((d, i) => (
                  <tr key={d.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors">
                    <td className="p-4 text-gray-400 dark:text-gray-500 text-xs font-mono">{i + 1}</td>
                    <td className="p-4 font-medium text-gray-900 dark:text-white">
                      {d.crusade_title || <span className="text-gray-400 dark:text-gray-500 italic">Unknown Crusade</span>}
                    </td>
                    <td className="p-4 font-mono text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                      <Globe className="w-3.5 h-3.5" />{d.ip_address || '—'}
                    </td>
                    <td className="p-4 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                      {new Date(d.created_at).toLocaleString()}
                    </td>
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
