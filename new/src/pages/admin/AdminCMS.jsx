import { useState, useEffect } from 'react';
import { Loader2, Megaphone, Plus, Trash2, X, Save, ToggleLeft, ToggleRight } from 'lucide-react';
import { cmsApi } from '../../services/api';

const PLACEMENTS = ['top', 'dashboard', 'videos'];
const EMPTY_FORM = { title: '', message: '', placement: 'dashboard', is_active: true };

export default function AdminCMS() {
  const [loading, setLoading] = useState(true);
  const [banners, setBanners] = useState([]);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState(EMPTY_FORM);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [successMsg, setSuccessMsg] = useState('');

  useEffect(() => { fetchBanners(); }, []);

  async function fetchBanners() {
    setLoading(true);
    try {
      const res = await cmsApi.getBanners('all').catch(() => null);
      if (res?.success) setBanners(res.data);
    } catch { /* empty — stub is fine */ }
    finally { setLoading(false); }
  }

  async function handleCreate(e) {
    e.preventDefault();
    setSaving(true);
    setError('');
    try {
      const res = await fetch('/api/admin/cms/banners', {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...form, is_active: form.is_active ? 1 : 0 })
      }).then(r => r.json()).catch(() => null);

      if (res?.success) {
        setSuccessMsg('Banner created! (Will appear once CMS table is provisioned in the database.)');
        setShowForm(false);
        setForm(EMPTY_FORM);
        // Optimistically add it to the list
        setBanners(prev => [...prev, { id: Date.now(), ...form }]);
      } else {
        setError(res?.message || 'Failed to save banner.');
      }
    } catch { setError('Error saving banner.'); }
    finally { setSaving(false); }
  }

  async function handleDelete(id) {
    if (!window.confirm('Delete this banner?')) return;
    try {
      await fetch(`/api/admin/cms/banners/${id}`, { method: 'DELETE', credentials: 'include' }).then(r => r.json());
      setBanners(prev => prev.filter(b => b.id !== id));
    } catch { alert('Error deleting banner.'); }
  }

  return (
    <div className="container max-w-5xl mx-auto px-4 py-8">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold mb-1 text-gray-950 dark:text-white">CMS & Announcements</h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm">Manage platform-wide banners and announcements shown to users.</p>
        </div>
        <button onClick={() => { setShowForm(true); setForm(EMPTY_FORM); }}
          className="flex items-center justify-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-700">
          <Plus className="w-4 h-4" /> New Banner
        </button>
      </div>

      {error && <div className="bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900/50 text-red-600 dark:text-red-400 p-4 rounded-lg mb-4">{error}</div>}
      {successMsg && (
        <div className="bg-green-50 dark:bg-green-950/40 border border-green-200 dark:border-green-900/50 text-green-700 dark:text-green-300 p-4 rounded-lg mb-4 flex items-center justify-between">
          {successMsg}
          <button onClick={() => setSuccessMsg('')}><X className="w-4 h-4" /></button>
        </div>
      )}

      {/* Stub notice */}
      <div className="bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/50 text-amber-800 dark:text-amber-300 p-4 rounded-xl mb-6 text-sm">
        <strong>Note:</strong> The CMS banners feature is UI-ready. A <code className="bg-amber-100 dark:bg-amber-900/60 px-1 rounded">cms_banners</code> database table will need to be created to persist banners. The API endpoints are stubbed and ready.
      </div>

      {/* Add Form */}
      {showForm && (
        <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 mb-6 shadow-sm">
          <div className="flex justify-between items-center mb-4">
            <h2 className="font-bold text-lg text-gray-950 dark:text-white">New Announcement Banner</h2>
            <button onClick={() => setShowForm(false)} className="p-1 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 rounded"><X className="w-5 h-5" /></button>
          </div>
          <form onSubmit={handleCreate} className="space-y-4">
            <div className="grid md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title *</label>
                <input value={form.title} onChange={e => setForm(f => ({ ...f, title: e.target.value }))} required
                  placeholder="e.g. Platform Maintenance"
                  className="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none" />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Placement</label>
                <select value={form.placement} onChange={e => setForm(f => ({ ...f, placement: e.target.value }))}
                  className="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none capitalize">
                  {PLACEMENTS.map(p => <option key={p} value={p}>{p.charAt(0).toUpperCase() + p.slice(1)}</option>)}
                </select>
              </div>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Message *</label>
              <textarea value={form.message} onChange={e => setForm(f => ({ ...f, message: e.target.value }))} required rows={3}
                placeholder="Write the announcement content..."
                className="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none resize-none" />
            </div>
            <div className="flex items-center justify-between">
              <label className="flex items-center gap-3 cursor-pointer">
                <button type="button" onClick={() => setForm(f => ({ ...f, is_active: !f.is_active }))}>
                  {form.is_active
                    ? <ToggleRight className="w-8 h-8 text-blue-600" />
                    : <ToggleLeft className="w-8 h-8 text-gray-400" />}
                </button>
                <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Active (visible to users)</span>
              </label>
              <div className="flex gap-3">
                <button type="button" onClick={() => setShowForm(false)} className="px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg text-sm font-medium transition-colors">Cancel</button>
                <button type="submit" disabled={saving} className="flex items-center gap-2 px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-60">
                  {saving ? <Loader2 className="w-4 h-4 animate-spin" /> : <Save className="w-4 h-4" />} Save Banner
                </button>
              </div>
            </div>
          </form>
        </div>
      )}

      {/* Banner List */}
      {loading ? (
        <div className="flex justify-center p-16"><Loader2 className="w-8 h-8 animate-spin text-blue-600" /></div>
      ) : banners.length === 0 ? (
        <div className="text-center py-20 bg-gray-50 dark:bg-gray-900 rounded-2xl border border-dashed border-gray-200 dark:border-gray-800">
          <Megaphone className="w-12 h-12 mx-auto mb-3 text-gray-400 opacity-50" />
          <h2 className="text-lg font-medium text-gray-600 dark:text-gray-300 mb-1">No Banners Yet</h2>
          <p className="text-gray-400 dark:text-gray-500 text-sm">Create your first announcement banner to display messages to users.</p>
        </div>
      ) : (
        <div className="flex flex-col gap-4">
          {banners.map(banner => (
            <div key={banner.id} className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-5 flex items-start gap-4 shadow-sm">
              <div className="p-2.5 bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 rounded-lg shrink-0">
                <Megaphone className="w-5 h-5" />
              </div>
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 mb-1">
                  <h3 className="font-semibold text-gray-900 dark:text-white">{banner.title}</h3>
                  <span className="text-xs bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 px-2 py-0.5 rounded capitalize">{banner.placement}</span>
                  {banner.is_active ? (
                    <span className="text-xs bg-green-100 dark:bg-green-950/60 text-green-700 dark:text-green-300 px-2 py-0.5 rounded font-medium">Active</span>
                  ) : (
                    <span className="text-xs bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 px-2 py-0.5 rounded">Inactive</span>
                  )}
                </div>
                <p className="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">{banner.message}</p>
              </div>
              <button onClick={() => handleDelete(banner.id)} className="p-1.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded shrink-0 transition-colors">
                <Trash2 className="w-4 h-4" />
              </button>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
