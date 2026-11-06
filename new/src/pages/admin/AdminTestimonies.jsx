import { useState, useEffect } from 'react';
import { Loader2, Check, X, Eye, Trash2, MessageSquare } from 'lucide-react';
import { testimoniesApi } from '../../services/api';

const STATUS_TABS = ['all', 'pending', 'approved', 'published', 'rejected'];
const STATUS_COLORS = {
  pending:  'bg-amber-100 text-amber-700',
  approved: 'bg-blue-100 text-blue-700',
  published:'bg-emerald-100 text-emerald-700',
  rejected: 'bg-red-100 text-red-700',
};

export default function AdminTestimonies() {
  const [testimonies, setTestimonies] = useState([]);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState('pending');
  const [updating, setUpdating] = useState(null);
  const [preview, setPreview] = useState(null);

  const load = () => {
    setLoading(true);
    testimoniesApi.adminList()
      .then(res => { if (res.success) setTestimonies(res.data || []); })
      .catch(() => {})
      .finally(() => setLoading(false));
  };

  useEffect(load, []);

  const updateStatus = async (id, status) => {
    setUpdating(id + status);
    await testimoniesApi.updateStatus(id, status).catch(() => {});
    setUpdating(null);
    load();
  };

  const filtered = activeTab === 'all' ? testimonies : testimonies.filter(t => t.status === activeTab);

  const counts = STATUS_TABS.reduce((acc, s) => {
    acc[s] = s === 'all' ? testimonies.length : testimonies.filter(t => t.status === s).length;
    return acc;
  }, {});

  return (
    <div className="container max-w-7xl mx-auto px-4 py-8">
      <div className="flex items-center gap-3 mb-8">
        <div className="p-2.5 bg-[var(--color-brand-accent)]/10 rounded-xl">
          <MessageSquare className="w-6 h-6 text-[var(--color-brand-accent)]" />
        </div>
        <div>
          <h1 className="text-2xl font-bold text-gray-950 dark:text-white">Testimonies</h1>
          <p className="text-sm text-gray-500 dark:text-gray-400">Review and moderate submitted testimonies</p>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-1.5 mb-6 bg-gray-100/70 dark:bg-gray-800 p-1.5 rounded-xl w-fit flex-wrap">
        {STATUS_TABS.map(tab => (
          <button
            key={tab}
            onClick={() => setActiveTab(tab)}
            className={`px-4 py-1.5 rounded-lg text-sm font-semibold capitalize transition-all ${activeTab === tab ? 'bg-white dark:bg-gray-700 shadow-sm text-gray-950 dark:text-white' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'}`}
          >
            {tab} {counts[tab] > 0 && <span className="ml-1 text-xs opacity-60">({counts[tab]})</span>}
          </button>
        ))}
      </div>

      {/* Preview Modal */}
      {preview && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
          <div className="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl max-w-lg w-full shadow-2xl overflow-hidden">
            <div className="flex items-center justify-between p-5 border-b border-gray-100 dark:border-gray-800">
              <h3 className="font-bold text-gray-950 dark:text-white">Testimony Preview</h3>
              <button onClick={() => setPreview(null)} className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <X className="w-5 h-5" />
              </button>
            </div>
            <div className="p-6">
              <blockquote className="text-gray-700 dark:text-gray-300 text-sm leading-relaxed mb-5 italic">"{preview.testimony_text}"</blockquote>
              <div className="text-xs text-gray-400 dark:text-gray-400 space-y-1">
                <p><strong>Name:</strong> {preview.full_name}</p>
                <p><strong>Email:</strong> {preview.email || '—'}</p>
                <p><strong>Country:</strong> {preview.country || '—'}</p>
                <p><strong>Submitted:</strong> {new Date(preview.created_at).toLocaleString()}</p>
              </div>
              <div className="flex gap-2 mt-6">
                {preview.status !== 'published' && (
                  <button onClick={() => { updateStatus(preview.id, 'published'); setPreview(null); }} className="flex-1 flex items-center justify-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold py-2.5 rounded-xl">
                    <Check className="w-4 h-4" /> Publish
                  </button>
                )}
                {preview.status !== 'approved' && preview.status !== 'published' && (
                  <button onClick={() => { updateStatus(preview.id, 'approved'); setPreview(null); }} className="flex-1 flex items-center justify-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold py-2.5 rounded-xl">
                    Approve
                  </button>
                )}
                {preview.status !== 'rejected' && (
                  <button onClick={() => { updateStatus(preview.id, 'rejected'); setPreview(null); }} className="flex-1 flex items-center justify-center gap-1.5 bg-red-500 hover:bg-red-600 text-white text-sm font-bold py-2.5 rounded-xl">
                    <X className="w-4 h-4" /> Reject
                  </button>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Table */}
      {loading ? (
        <div className="flex justify-center py-20"><Loader2 className="w-10 h-10 animate-spin text-[var(--color-brand-accent)]" /></div>
      ) : filtered.length === 0 ? (
        <div className="text-center py-20 text-gray-400 dark:text-gray-500">
          <MessageSquare className="w-12 h-12 mx-auto mb-3 opacity-30" />
          <p>No testimonies in this category.</p>
        </div>
      ) : (
        <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-gray-50 dark:bg-gray-800 text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide border-b border-gray-200 dark:border-gray-700">
                  <th className="text-left px-5 py-3 font-semibold">Name</th>
                  <th className="text-left px-5 py-3 font-semibold">Country</th>
                  <th className="text-left px-5 py-3 font-semibold">Preview</th>
                  <th className="text-left px-5 py-3 font-semibold">Status</th>
                  <th className="text-left px-5 py-3 font-semibold">Date</th>
                  <th className="text-left px-5 py-3 font-semibold">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                {filtered.map(t => (
                  <tr key={t.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors">
                    <td className="px-5 py-3 font-medium text-gray-900 dark:text-white">{t.full_name}</td>
                    <td className="px-5 py-3 text-gray-500 dark:text-gray-400">{t.country || '—'}</td>
                    <td className="px-5 py-3 text-gray-500 dark:text-gray-400 max-w-xs">
                      <span className="line-clamp-1">{t.testimony_text?.slice(0, 80)}…</span>
                    </td>
                    <td className="px-5 py-3">
                      <span className={`px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize ${STATUS_COLORS[t.status] || 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400'}`}>
                        {t.status}
                      </span>
                    </td>
                    <td className="px-5 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">{new Date(t.created_at).toLocaleDateString()}</td>
                    <td className="px-5 py-3">
                      <div className="flex items-center gap-1">
                        <button onClick={() => setPreview(t)} title="Preview" className="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                          <Eye className="w-4 h-4" />
                        </button>
                        {t.status !== 'published' && (
                          <button onClick={() => updateStatus(t.id, 'published')} disabled={updating === t.id+'published'} title="Publish" className="p-1.5 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-950/40 text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors">
                            {updating === t.id+'published' ? <Loader2 className="w-4 h-4 animate-spin" /> : <Check className="w-4 h-4" />}
                          </button>
                        )}
                        {t.status !== 'rejected' && (
                          <button onClick={() => updateStatus(t.id, 'rejected')} disabled={updating === t.id+'rejected'} title="Reject" className="p-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-950/40 text-gray-400 hover:text-red-500 dark:hover:text-red-400 transition-colors">
                            {updating === t.id+'rejected' ? <Loader2 className="w-4 h-4 animate-spin" /> : <X className="w-4 h-4" />}
                          </button>
                        )}
                      </div>
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
