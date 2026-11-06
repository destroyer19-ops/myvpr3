import { useState, useEffect } from 'react';
import { Loader2, Plus, Edit2, Trash2, Tv, Circle, X, Save } from 'lucide-react';
import { adminApi } from '../../services/api';

const EMPTY_FORM = { title: '', description: '', stream_url: '', is_live: false };

export default function AdminStreams() {
  const [loading, setLoading] = useState(true);
  const [streams, setStreams] = useState([]);
  const [form, setForm] = useState(EMPTY_FORM);
  const [editingId, setEditingId] = useState(null);
  const [showForm, setShowForm] = useState(false);
  const [saving, setSaving] = useState(false);
  const [togglingId, setTogglingId] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => { fetchStreams(); }, []);

  async function fetchStreams() {
    setLoading(true);
    try {
      const res = await adminApi.listStreams();
      if (res.success) setStreams(res.data);
      else setError(res.message);
    } catch { setError('Failed to load streams.'); }
    finally { setLoading(false); }
  }

  function openAdd() {
    setEditingId(null);
    setForm(EMPTY_FORM);
    setShowForm(true);
  }

  function openEdit(stream) {
    setEditingId(stream.id);
    setForm({ title: stream.title, description: stream.description || '', stream_url: stream.stream_url, is_live: !!stream.is_live });
    setShowForm(true);
  }

  async function handleSave(e) {
    e.preventDefault();
    setSaving(true);
    try {
      const payload = { ...form, is_live: form.is_live ? 1 : 0 };
      const res = editingId
        ? await adminApi.updateStream(editingId, payload)
        : await adminApi.createStream(payload);
      if (res.success) { setShowForm(false); await fetchStreams(); }
      else alert(res.message || 'Failed to save.');
    } catch { alert('Error saving stream.'); }
    finally { setSaving(false); }
  }

  async function handleDelete(id) {
    if (!window.confirm('Delete this stream?')) return;
    try {
      const res = await adminApi.deleteStream(id);
      if (res.success) setStreams(prev => prev.filter(s => s.id !== id));
      else alert(res.message || 'Failed to delete.');
    } catch { alert('Error deleting stream.'); }
  }

  async function handleToggle(id) {
    setTogglingId(id);
    try {
      await fetch(`/api/admin/streams/${id}/toggle`, {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json' }
      });
      await fetchStreams();
    } catch { alert('Error toggling stream.'); }
    finally { setTogglingId(null); }
  }

  return (
    <div className="container max-w-7xl mx-auto px-4 py-8">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold mb-1 text-gray-950 dark:text-white">Manage Live Streams</h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm">Add and control 24/7 TV channels. Toggle a stream to go live.</p>
        </div>
        <button onClick={openAdd} className="flex items-center justify-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-700">
          <Plus className="w-4 h-4" /> Add Stream
        </button>
      </div>

      {error && <div className="bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900/50 text-red-600 dark:text-red-400 p-4 rounded-lg mb-6">{error}</div>}

      {/* Add/Edit Form Panel */}
      {showForm && (
        <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 mb-6 shadow-sm">
          <div className="flex justify-between items-center mb-4">
            <h2 className="font-bold text-lg text-gray-950 dark:text-white">{editingId ? 'Edit Stream' : 'Add New Stream'}</h2>
            <button onClick={() => setShowForm(false)} className="p-1 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 rounded"><X className="w-5 h-5" /></button>
          </div>
          <form onSubmit={handleSave} className="grid md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title *</label>
              <input value={form.title} onChange={e => setForm(f => ({ ...f, title: e.target.value }))} required
                className="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Stream URL *</label>
              <input value={form.stream_url} onChange={e => setForm(f => ({ ...f, stream_url: e.target.value }))} required
                placeholder="YouTube URL or HLS stream URL"
                className="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>
            <div className="md:col-span-2">
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
              <textarea value={form.description} onChange={e => setForm(f => ({ ...f, description: e.target.value }))} rows={2}
                className="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none resize-none" />
            </div>
            <div className="flex items-center gap-3">
              <label className="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" checked={form.is_live} onChange={e => setForm(f => ({ ...f, is_live: e.target.checked }))} className="sr-only peer" />
                <div className="w-10 h-6 bg-gray-200 dark:bg-gray-700 peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
              </label>
              <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Set as Live</span>
            </div>
            <div className="flex justify-end gap-3 md:col-span-2 mt-2">
              <button type="button" onClick={() => setShowForm(false)} className="px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg text-sm font-medium transition-colors">Cancel</button>
              <button type="submit" disabled={saving} className="flex items-center gap-2 px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-60">
                {saving ? <Loader2 className="w-4 h-4 animate-spin" /> : <Save className="w-4 h-4" />}
                {editingId ? 'Save Changes' : 'Create Stream'}
              </button>
            </div>
          </form>
        </div>
      )}

      {loading ? (
        <div className="flex justify-center p-16"><Loader2 className="w-8 h-8 animate-spin text-blue-600" /></div>
      ) : (
        <div className="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm text-left">
              <thead className="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                <tr>
                  <th className="p-4 font-medium text-gray-500 dark:text-gray-400">Title</th>
                  <th className="p-4 font-medium text-gray-500 dark:text-gray-400">Stream URL</th>
                  <th className="p-4 font-medium text-gray-500 dark:text-gray-400">Status</th>
                  <th className="p-4 font-medium text-gray-500 dark:text-gray-400">Created</th>
                  <th className="p-4 font-medium text-gray-500 dark:text-gray-400 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                {streams.length === 0 ? (
                  <tr><td colSpan="5" className="p-12 text-center text-gray-400 dark:text-gray-500">
                    <Tv className="w-8 h-8 mx-auto mb-2 opacity-40" />
                    No streams yet. Add one above.
                  </td></tr>
                ) : streams.map(stream => (
                  <tr key={stream.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors">
                    <td className="p-4 font-medium text-gray-900 dark:text-white">{stream.title}</td>
                    <td className="p-4">
                      <span className="text-gray-500 dark:text-gray-400 text-xs font-mono truncate block max-w-[220px]" title={stream.stream_url}>
                        {stream.stream_url}
                      </span>
                    </td>
                    <td className="p-4">
                      {stream.is_live ? (
                        <span className="flex items-center gap-1.5 text-red-600 dark:text-red-400 font-semibold text-xs">
                          <Circle className="w-2 h-2 fill-current animate-pulse" /> LIVE
                        </span>
                      ) : (
                        <span className="text-gray-400 dark:text-gray-500 text-xs font-medium">OFFLINE</span>
                      )}
                    </td>
                    <td className="p-4 text-gray-500 dark:text-gray-400 whitespace-nowrap">{new Date(stream.created_at).toLocaleDateString()}</td>
                    <td className="p-4 text-right">
                      <div className="flex justify-end gap-2">
                        <button onClick={() => handleToggle(stream.id)} disabled={togglingId === stream.id}
                          className={`text-xs px-3 py-1.5 rounded-lg font-medium transition-colors ${stream.is_live ? 'bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-700' : 'bg-red-100 dark:bg-red-950/60 text-red-700 dark:text-red-300 hover:bg-red-200'}`}>
                          {togglingId === stream.id ? <Loader2 className="w-3 h-3 animate-spin" /> : (stream.is_live ? 'Go Offline' : 'Go Live')}
                        </button>
                        <button onClick={() => openEdit(stream)} className="p-1.5 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 rounded transition-colors"><Edit2 className="w-4 h-4" /></button>
                        <button onClick={() => handleDelete(stream.id)} className="p-1.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded transition-colors"><Trash2 className="w-4 h-4" /></button>
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
