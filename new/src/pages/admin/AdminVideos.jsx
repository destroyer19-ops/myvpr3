import { useState, useEffect } from 'react';
import { Loader2, Plus, Edit2, Trash2, Video, Upload, X } from 'lucide-react';
import { adminApi } from '../../services/api';

export default function AdminVideos() {
  const [loading, setLoading] = useState(true);
  const [videos, setVideos] = useState([]);
  const [error, setError] = useState('');

  // Add modal state
  const [showAdd, setShowAdd] = useState(false);
  const [adding, setAdding] = useState(false);
  const [addForm, setAddForm] = useState({
    title: '', description: '', video_url: '', language: '',
    category_children_outreach: false,
    category_movie_outreach: false,
    category_ministrations: false
  });
  const [ecardFile, setEcardFile] = useState(null);
  const [ecardPreview, setEcardPreview] = useState(null);

  const fetchVideos = async () => {
    setLoading(true);
    try {
      const res = await adminApi.listVideos();
      if (res.success) setVideos(res.data);
      else setError(res.message);
    } catch (err) {
      setError('Failed to fetch videos.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchVideos(); }, []);

  const handleDelete = async (id) => {
    if (window.confirm('Are you sure you want to delete this video?')) {
      try {
        const res = await adminApi.deleteVideo(id).catch(() => null);
        if (res?.success) setVideos(videos.filter(v => v.id !== id));
        else alert('Failed to delete video.');
      } catch (e) { console.error(e); }
    }
  };

  const handleAddSubmit = async (e) => {
    e.preventDefault();
    if (!addForm.title || !addForm.video_url) return;
    setAdding(true);
    try {
      const data = new FormData();
      Object.entries(addForm).forEach(([k, v]) => data.append(k, v));
      if (ecardFile) data.append('ecard', ecardFile);

      const res = await adminApi.createVideo(data);
      if (res.success) {
        setShowAdd(false);
        setAddForm({
          title: '', description: '', video_url: '', language: '',
          category_children_outreach: false, category_movie_outreach: false, category_ministrations: false
        });
        setEcardFile(null);
        setEcardPreview(null);
        fetchVideos();
      } else {
        alert(res.message || 'Failed to add video');
      }
    } catch (err) {
      alert('Error adding video');
    } finally {
      setAdding(false);
    }
  };

  const handleEcardChange = (e) => {
    const file = e.target.files[0];
    if (file) {
      setEcardFile(file);
      setEcardPreview(URL.createObjectURL(file));
    }
  };

  return (
    <div className="container max-w-7xl mx-auto px-4 py-8 animate-in fade-in duration-300">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold mb-1 text-gray-950 dark:text-white">Manage Videos</h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm">Upload and organize the Video Library.</p>
        </div>
        <button onClick={() => setShowAdd(true)} className="bg-[var(--color-action-primary)] text-white px-5 py-2.5 rounded-full font-bold hover:opacity-90 transition-opacity flex items-center justify-center gap-2 shadow-md">
          <Plus className="w-5 h-5" /> Add Video
        </button>
      </div>

      {error && <div className="bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900/50 text-red-600 dark:text-red-400 p-4 rounded-xl mb-6 font-medium">{error}</div>}

      {loading ? (
        <div className="flex justify-center p-12"><Loader2 className="w-8 h-8 animate-spin text-[var(--color-action-primary)]" /></div>
      ) : (
        <div className="bg-white dark:bg-gray-900 rounded-3xl shadow-sm border border-gray-200 dark:border-gray-800 overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                <tr>
                  <th className="p-4 font-bold text-gray-500 dark:text-gray-400">Title</th>
                  <th className="p-4 font-bold text-gray-500 dark:text-gray-400">Language</th>
                  <th className="p-4 font-bold text-gray-500 dark:text-gray-400">Categories</th>
                  <th className="p-4 font-bold text-gray-500 dark:text-gray-400">Created</th>
                  <th className="p-4 font-bold text-gray-500 dark:text-gray-400 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                {videos.length > 0 ? (
                  videos.map((video) => (
                    <tr key={video.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                      <td className="p-4">
                        <div className="flex items-center gap-3">
                          {video.thumbnail_url ? (
                            <img src={video.thumbnail_url} alt="" className="w-12 h-8 rounded object-cover" />
                          ) : (
                            <div className="w-12 h-8 rounded bg-gray-200 dark:bg-gray-700 flex items-center justify-center"><Video className="w-4 h-4 text-gray-400"/></div>
                          )}
                          <div className="font-semibold text-gray-900 dark:text-white line-clamp-1">{video.title}</div>
                        </div>
                      </td>
                      <td className="p-4 text-gray-500 dark:text-gray-400">{video.language || '-'}</td>
                      <td className="p-4">
                        <div className="flex flex-wrap gap-1">
                          {video.category_children_outreach == 1 && <span className="bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 px-2 py-0.5 rounded text-[10px] uppercase font-bold tracking-wider">Children</span>}
                          {video.category_movie_outreach == 1 && <span className="bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 px-2 py-0.5 rounded text-[10px] uppercase font-bold tracking-wider">Movie</span>}
                          {video.category_ministrations == 1 && <span className="bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 px-2 py-0.5 rounded text-[10px] uppercase font-bold tracking-wider">Ministrations</span>}
                        </div>
                      </td>
                      <td className="p-4 text-gray-500 dark:text-gray-400 whitespace-nowrap">{new Date(video.created_at).toLocaleDateString()}</td>
                      <td className="p-4 text-right">
                        <div className="flex justify-end gap-2">
                          <button onClick={() => handleDelete(video.id)} className="p-2 text-gray-400 hover:text-red-600 dark:hover:text-red-400 bg-gray-100 dark:bg-gray-800 hover:bg-red-50 dark:hover:bg-red-950/40 rounded-lg transition-colors" title="Delete">
                            <Trash2 className="w-4 h-4" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan="5" className="p-12 text-center text-gray-500 dark:text-gray-400">
                      <Video className="w-8 h-8 mx-auto mb-2 opacity-50" />
                      <p>No videos found.</p>
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Add Modal */}
      {showAdd && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm overflow-y-auto">
          <div className="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 w-full max-w-3xl rounded-3xl shadow-2xl relative my-8 animate-in zoom-in-95 duration-300">
            
            <div className="sticky top-0 z-10 bg-white/80 dark:bg-gray-900/80 backdrop-blur-md px-8 py-5 border-b border-gray-100 dark:border-gray-800 rounded-t-3xl flex items-center justify-between">
              <h2 className="text-xl font-bold text-gray-900 dark:text-white">Upload New Video</h2>
              <button onClick={() => setShowAdd(false)} className="p-2 text-gray-400 hover:text-gray-900 dark:hover:text-white bg-gray-100 dark:bg-gray-800 rounded-full transition-colors"><X className="w-5 h-5"/></button>
            </div>

            <form onSubmit={handleAddSubmit} className="p-8">
              <div className="grid md:grid-cols-2 gap-8">
                <div className="space-y-5">
                  <h3 className="font-bold text-lg border-b border-gray-100 dark:border-gray-800 pb-2 text-gray-950 dark:text-white">Video Details</h3>
                  <div>
                    <label className="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">Title *</label>
                    <input type="text" required value={addForm.title} onChange={e=>setAddForm({...addForm, title: e.target.value})} className="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white outline-none focus:border-[var(--color-action-primary)] transition-colors" />
                  </div>
                  <div>
                    <label className="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">Video URL *</label>
                    <input type="text" required placeholder="YouTube or MP4 link" value={addForm.video_url} onChange={e=>setAddForm({...addForm, video_url: e.target.value})} className="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white outline-none focus:border-[var(--color-action-primary)] transition-colors" />
                  </div>
                  <div>
                    <label className="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">Description</label>
                    <textarea rows="3" value={addForm.description} onChange={e=>setAddForm({...addForm, description: e.target.value})} className="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white outline-none focus:border-[var(--color-action-primary)] transition-colors"></textarea>
                  </div>
                  <div>
                    <label className="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">Language</label>
                    <input type="text" placeholder="e.g. English, French" value={addForm.language} onChange={e=>setAddForm({...addForm, language: e.target.value})} className="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white outline-none focus:border-[var(--color-action-primary)] transition-colors" />
                  </div>
                </div>

                <div className="space-y-5">
                  <h3 className="font-bold text-lg border-b border-gray-100 dark:border-gray-800 pb-2 text-gray-950 dark:text-white">Artwork & Categories</h3>
                  
                  <div>
                    <label className="block text-sm font-semibold mb-1 text-gray-700 dark:text-gray-300">E-Card / Thumbnail</label>
                    <label className="flex flex-col items-center justify-center w-full h-40 border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-xl bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer transition-colors relative overflow-hidden group">
                      {ecardPreview ? (
                        <>
                          <img src={ecardPreview} alt="Preview" className="w-full h-full object-cover" />
                          <div className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                            <span className="text-white text-sm font-bold bg-black/50 px-3 py-1.5 rounded-full">Replace Image</span>
                          </div>
                        </>
                      ) : (
                        <div className="flex flex-col items-center justify-center pt-5 pb-6">
                          <Upload className="w-8 h-8 text-gray-400 mb-2 group-hover:scale-110 transition-transform" />
                          <p className="text-sm font-medium text-gray-500 dark:text-gray-400">Upload thumbnail</p>
                        </div>
                      )}
                      <input type="file" className="hidden" accept="image/*" onChange={handleEcardChange} />
                    </label>
                    {ecardFile && (
                      <div className="flex justify-end mt-2">
                        <button type="button" onClick={(e) => { e.preventDefault(); setEcardFile(null); setEcardPreview(null); }} className="text-xs text-red-500 hover:text-red-600 font-bold">Remove Image</button>
                      </div>
                    )}
                  </div>

                  <div className="space-y-3 pt-2">
                    <label className="flex items-center gap-3 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors">
                      <input type="checkbox" checked={addForm.category_children_outreach} onChange={e=>setAddForm({...addForm, category_children_outreach: e.target.checked})} className="w-4 h-4 rounded text-[var(--color-action-primary)] focus:ring-[var(--color-action-primary)]" />
                      <span className="text-sm font-semibold text-gray-900 dark:text-white">Children Outreach</span>
                    </label>
                    <label className="flex items-center gap-3 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors">
                      <input type="checkbox" checked={addForm.category_movie_outreach} onChange={e=>setAddForm({...addForm, category_movie_outreach: e.target.checked})} className="w-4 h-4 rounded text-[var(--color-action-primary)] focus:ring-[var(--color-action-primary)]" />
                      <span className="text-sm font-semibold text-gray-900 dark:text-white">Movie Outreach</span>
                    </label>
                    <label className="flex items-center gap-3 p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors">
                      <input type="checkbox" checked={addForm.category_ministrations} onChange={e=>setAddForm({...addForm, category_ministrations: e.target.checked})} className="w-4 h-4 rounded text-[var(--color-action-primary)] focus:ring-[var(--color-action-primary)]" />
                      <span className="text-sm font-semibold text-gray-900 dark:text-white">Ministrations</span>
                    </label>
                  </div>
                </div>
              </div>

              <div className="mt-8 pt-6 border-t border-gray-100 dark:border-gray-800 flex justify-end gap-3">
                <button type="button" onClick={() => setShowAdd(false)} className="px-6 py-3 rounded-full font-bold text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">Cancel</button>
                <button type="submit" disabled={adding} className="px-8 py-3 rounded-full bg-[var(--color-action-primary)] text-white font-bold hover:opacity-90 transition-opacity flex items-center justify-center gap-2">
                  {adding ? <Loader2 className="w-5 h-5 animate-spin"/> : 'Publish Video'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
