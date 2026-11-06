import React, { useState, useEffect } from 'react';
import { request } from '../../services/api';
import { Plus, Edit2, Trash2, Loader2, X, Save, Tv, Circle } from 'lucide-react';

export default function AdminStudioPlans() {
  const [plans, setPlans] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [saving, setSaving] = useState(false);
  
  const [togglingId, setTogglingId] = useState(null);

  const [form, setForm] = useState({
    name: '',
    description: '',
    price: '0.00',
    billing_period: 'month',
    resolution: '1080p',
    max_duration_hours: '',
    max_viewers: '',
    is_active: true
  });

  async function fetchPlans() {
    try {
      const json = await request('/admin/streaming-plans');
      if (json.success) setPlans(json.data || []);
      else setError(json.message || 'Failed to load plans');
    } catch (e) {
      setError(e.message || 'Error fetching plans');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    fetchPlans();
  }, []);

  function openAdd() {
    setEditingId(null);
    setForm({
      name: '',
      description: '',
      price: '0.00',
      billing_period: 'month',
      resolution: '1080p',
      max_duration_hours: '',
      max_viewers: '',
      is_active: true
    });
    setShowForm(true);
  }

  function openEdit(plan) {
    setEditingId(plan.id);
    setForm({
      name: plan.name,
      description: plan.description || '',
      price: plan.price || '0.00',
      billing_period: plan.billing_period || 'month',
      resolution: plan.resolution || '1080p',
      max_duration_hours: plan.max_duration_hours || '',
      max_viewers: plan.max_viewers || '',
      is_active: !!plan.is_active
    });
    setShowForm(true);
  }

  async function handleSave(e) {
    e.preventDefault();
    setSaving(true);
    try {
      const url = editingId ? `/admin/streaming-plans/${editingId}` : '/admin/streaming-plans';
      const method = editingId ? 'PUT' : 'POST';
      
      const res = await request(url, {
        method,
        body: JSON.stringify(form)
      });

      if (res.success) {
        setShowForm(false);
        await fetchPlans();
      } else {
        alert(res.message || 'Failed to save plan.');
      }
    } catch {
      alert('Error saving plan.');
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(id) {
    if (!window.confirm('Delete this plan completely?')) return;
    try {
      const res = await request(`/admin/streaming-plans/${id}`, { method: 'DELETE' });
      if (res.success) await fetchPlans();
      else alert(res.message || 'Failed to delete.');
    } catch { alert('Error deleting plan.'); }
  }

  async function handleToggle(id) {
    setTogglingId(id);
    try {
      const res = await request(`/admin/streaming-plans/${id}/toggle`, { method: 'POST' });
      if (res.success) await fetchPlans();
      else alert(res.message || 'Failed to toggle status.');
    } catch { alert('Error toggling status.'); }
    finally { setTogglingId(null); }
  }

  return (
    <div className="container max-w-7xl mx-auto px-4 py-8">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold mb-1 text-gray-950 dark:text-white">Manage Studio Plans</h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm">Create and configure subscription tiers for the broadcast studio.</p>
        </div>
        <button onClick={openAdd} className="flex items-center justify-center gap-2 bg-[var(--color-brand-primary)] text-white px-4 py-2 rounded-lg font-medium hover:opacity-90 transition-opacity">
          <Plus className="w-4 h-4" /> Add Plan
        </button>
      </div>

      {error && <div className="bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900/50 text-red-600 dark:text-red-400 p-4 rounded-lg mb-6">{error}</div>}

      {showForm && (
        <div className="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 mb-6 shadow-sm">
          <div className="flex justify-between items-center mb-4">
            <h2 className="font-bold text-lg text-gray-950 dark:text-white">{editingId ? 'Edit Plan' : 'Add New Plan'}</h2>
            <button onClick={() => setShowForm(false)} className="p-1 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 rounded"><X className="w-5 h-5" /></button>
          </div>
          <form onSubmit={handleSave} className="grid md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Plan Name *</label>
              <input value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} required
                placeholder="e.g. Pro, Premium"
                className="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Price (USD) *</label>
              <input type="number" step="0.01" value={form.price} onChange={e => setForm(f => ({ ...f, price: e.target.value }))} required
                className="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>
            <div className="md:col-span-2">
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
              <textarea value={form.description} onChange={e => setForm(f => ({ ...f, description: e.target.value }))} rows={2}
                className="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none resize-none" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Billing Period</label>
              <select value={form.billing_period} onChange={e => setForm(f => ({ ...f, billing_period: e.target.value }))}
                className="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="month">Monthly</option>
                <option value="year">Yearly</option>
                <option value="one-time">One-time</option>
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Resolution</label>
              <input value={form.resolution} onChange={e => setForm(f => ({ ...f, resolution: e.target.value }))}
                placeholder="e.g. 1080p, 4K"
                className="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Max Duration (Hours)</label>
              <input type="number" value={form.max_duration_hours} onChange={e => setForm(f => ({ ...f, max_duration_hours: e.target.value }))}
                placeholder="Leave blank for unlimited"
                className="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Max Viewers</label>
              <input type="number" value={form.max_viewers} onChange={e => setForm(f => ({ ...f, max_viewers: e.target.value }))}
                placeholder="Leave blank for unlimited"
                className="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none" />
            </div>
            
            <div className="flex items-center gap-3 md:col-span-2">
              <label className="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" checked={form.is_active} onChange={e => setForm(f => ({ ...f, is_active: e.target.checked }))} className="sr-only peer" />
                <div className="w-10 h-6 bg-gray-200 dark:bg-gray-700 peer-focus:ring-2 peer-focus:ring-[var(--color-brand-primary-soft)] rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[var(--color-brand-primary)]"></div>
              </label>
              <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Active (Visible to users)</span>
            </div>
            <div className="flex justify-end gap-3 md:col-span-2 mt-2">
              <button type="button" onClick={() => setShowForm(false)} className="px-4 py-2 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg text-sm font-medium transition-colors">Cancel</button>
              <button type="submit" disabled={saving} className="flex items-center gap-2 px-5 py-2 bg-[var(--color-brand-primary)] text-white rounded-lg text-sm font-medium hover:opacity-90 disabled:opacity-60 transition-opacity">
                {saving ? <Loader2 className="w-4 h-4 animate-spin" /> : <Save className="w-4 h-4" />}
                {editingId ? 'Save Changes' : 'Create Plan'}
              </button>
            </div>
          </form>
        </div>
      )}

      {loading ? (
        <div className="flex justify-center p-16"><Loader2 className="w-8 h-8 animate-spin text-[var(--color-brand-primary)]" /></div>
      ) : (
        <div className="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm text-left">
              <thead className="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                <tr>
                  <th className="p-4 font-medium text-gray-500 dark:text-gray-400">Plan Name</th>
                  <th className="p-4 font-medium text-gray-500 dark:text-gray-400">Price</th>
                  <th className="p-4 font-medium text-gray-500 dark:text-gray-400">Limits</th>
                  <th className="p-4 font-medium text-gray-500 dark:text-gray-400">Status</th>
                  <th className="p-4 font-medium text-gray-500 dark:text-gray-400 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                {plans.length === 0 ? (
                  <tr><td colSpan="5" className="p-12 text-center text-gray-400 dark:text-gray-500">
                    <Tv className="w-8 h-8 mx-auto mb-2 opacity-40" />
                    No plans configured. Create one above.
                  </td></tr>
                ) : plans.map(plan => (
                  <tr key={plan.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors">
                    <td className="p-4 font-medium text-gray-900 dark:text-white">{plan.name}</td>
                    <td className="p-4 font-semibold text-gray-700 dark:text-gray-300">${plan.price} / {plan.billing_period}</td>
                    <td className="p-4">
                      <div className="text-xs text-gray-500 dark:text-gray-400">
                        {plan.resolution} &bull; {plan.max_viewers ? `${plan.max_viewers} viewers` : 'Unl. viewers'} &bull; {plan.max_duration_hours ? `${plan.max_duration_hours}h max` : 'Unl. time'}
                      </div>
                    </td>
                    <td className="p-4">
                      {plan.is_active ? (
                        <span className="flex items-center gap-1.5 text-green-600 dark:text-green-400 font-semibold text-xs">
                          <Circle className="w-2 h-2 fill-current" /> ACTIVE
                        </span>
                      ) : (
                        <span className="text-gray-400 dark:text-gray-500 text-xs font-medium">INACTIVE</span>
                      )}
                    </td>
                    <td className="p-4 text-right">
                      <div className="flex justify-end gap-2">
                        <button onClick={() => handleToggle(plan.id)} disabled={togglingId === plan.id}
                          className={`text-xs px-3 py-1.5 rounded-lg font-medium transition-colors ${plan.is_active ? 'bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-700' : 'bg-green-100 dark:bg-green-950/60 text-green-700 dark:text-green-300 hover:bg-green-200'}`}>
                          {togglingId === plan.id ? <Loader2 className="w-3 h-3 animate-spin" /> : (plan.is_active ? 'Deactivate' : 'Activate')}
                        </button>
                        <button onClick={() => openEdit(plan)} className="p-1.5 text-gray-400 hover:text-[var(--color-brand-primary)] dark:hover:text-blue-400 rounded transition-colors"><Edit2 className="w-4 h-4" /></button>
                        <button onClick={() => handleDelete(plan.id)} className="p-1.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400 rounded transition-colors"><Trash2 className="w-4 h-4" /></button>
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
