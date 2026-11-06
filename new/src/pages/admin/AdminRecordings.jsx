import { useState, useEffect } from 'react';
import { 
  Loader2, Plus, Edit2, Trash2, Video, Upload, X, Check,
  Radio, Film, Calendar, Clock, Eye, AlertCircle, Play, Tag, Globe, Link as LinkIcon,
  Cloud, CheckCircle2, ShieldCheck
} from 'lucide-react';
import { adminApi } from '@/services/api';

const CATEGORIES = ['Ministration', 'Worship', 'Teaching', 'Children', 'Music', 'Movie', 'Special Event'];

export default function AdminRecordings() {
  const [loading, setLoading] = useState(true);
  const [recordings, setRecordings] = useState([]);
  const [programs, setPrograms] = useState([]);
  const [activeTab, setActiveTab] = useState('all'); // all, published, draft, processing
  const [error, setError] = useState('');
  const [successMsg, setSuccessMsg] = useState('');

  // Modal states
  const [modalOpen, setModalOpen] = useState(false);
  const [isEditing, setIsEditing] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [submitting, setSubmitting] = useState(false);

  // Form states
  const [sourceType, setSourceType] = useState('upload'); // default to 'upload' for convenience
  const [uploadProgress, setUploadProgress] = useState(0);
  const [uploadStatusText, setUploadStatusText] = useState('');
  const [s3Config, setS3Config] = useState({ configured: false, bucket: null, region: 'us-east-1' });
  const [videoFile, setVideoFile] = useState(null);
  const [thumbnailFile, setThumbnailFile] = useState(null);
  const [thumbnailPreview, setThumbnailPreview] = useState(null);

  const [form, setForm] = useState({
    title: '',
    description: '',
    program_id: '',
    program_date: new Date().toISOString().split('T')[0],
    category: 'Ministration',
    status: 'published',
    duration_minutes: '60',
    video_url: '',
    thumbnail_url: ''
  });

  const fetchData = async () => {
    setLoading(true);
    try {
      const [recRes, progRes, s3Res] = await Promise.all([
        adminApi.listRecordings().catch(() => null),
        adminApi.listPrograms().catch(() => null),
        adminApi.getS3Config().catch(() => null)
      ]);

      if (s3Res?.success && s3Res.configured) {
        setS3Config(s3Res);
      }

      if (recRes?.success) {
        setRecordings(recRes.data || []);
      } else {
        // Fallback demo data if backend not reachable
        setRecordings([
          {
            id: 1,
            title: 'A Day of Bliss with Pastor Chris (Samoa)',
            program_title: 'Healing Streams Live Healing Services',
            category: 'Ministration',
            program_date: '2026-11-04',
            duration_seconds: 3600,
            status: 'published',
            thumbnail_url: 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=600&q=80',
            video_url: 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4'
          },
          {
            id: 2,
            title: 'Teaching Series on Prayer — Part 2',
            program_title: 'Global Prayer & Intercession Service',
            category: 'Teaching',
            program_date: '2026-11-02',
            duration_seconds: 2880,
            status: 'published',
            thumbnail_url: 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?auto=format&fit=crop&w=600&q=80',
            video_url: 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4'
          },
          {
            id: 3,
            title: 'Children\'s Bible Stories — Faith Over Fear',
            program_title: 'Children\'s Praise & Faith Stories',
            category: 'Children',
            program_date: '2026-10-30',
            duration_seconds: 1500,
            status: 'draft',
            thumbnail_url: 'https://images.unsplash.com/photo-1485546246426-74dc88dec4d9?auto=format&fit=crop&w=600&q=80',
            video_url: 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerEscapes.mp4'
          }
        ]);
      }

      if (progRes?.success) {
        setPrograms(progRes.data || []);
      } else {
        setPrograms([
          { id: 1, title: 'Healing Streams Live Healing Services' },
          { id: 2, title: 'Praise Room 24/7' },
          { id: 3, title: 'Children\'s Praise & Faith Stories' },
          { id: 4, title: 'Global Prayer & Intercession Service' }
        ]);
      }
    } catch {
      setError('Could not connect to recordings API.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const openCreateModal = () => {
    setIsEditing(false);
    setEditingId(null);
    setSourceType('url');
    setUploadProgress(0);
    setVideoFile(null);
    setThumbnailFile(null);
    setThumbnailPreview(null);
    setForm({
      title: '',
      description: '',
      program_id: programs[0]?.id || '',
      program_date: new Date().toISOString().split('T')[0],
      category: 'Ministration',
      status: 'published',
      duration_minutes: '45',
      video_url: '',
      thumbnail_url: ''
    });
    setModalOpen(true);
  };

  const openEditModal = (rec) => {
    setIsEditing(true);
    setEditingId(rec.id);
    setSourceType('url');
    setUploadProgress(0);
    setVideoFile(null);
    setThumbnailFile(null);
    setThumbnailPreview(rec.thumbnail_url || null);
    setForm({
      title: rec.title || '',
      description: rec.description || '',
      program_id: rec.program_id || '',
      program_date: rec.program_date || new Date().toISOString().split('T')[0],
      category: rec.category || 'Ministration',
      status: rec.status || 'draft',
      duration_minutes: Math.round((rec.duration_seconds || 0) / 60).toString(),
      video_url: rec.video_url || '',
      thumbnail_url: rec.thumbnail_url || ''
    });
    setModalOpen(true);
  };

  const handleToggleStatus = async (rec) => {
    const nextStatus = rec.status === 'published' ? 'draft' : 'published';
    try {
      const res = await adminApi.updateRecording(rec.id, {
        ...rec,
        status: nextStatus
      });
      if (res.success) {
        setRecordings(prev => prev.map(r => r.id === rec.id ? { ...r, status: nextStatus } : r));
        setSuccessMsg(`Recording marked as ${nextStatus}.`);
        setTimeout(() => setSuccessMsg(''), 3000);
      } else {
        alert(res.message || 'Failed to update status.');
      }
    } catch {
      alert('Error updating recording status.');
    }
  };

  const handleDelete = async (id) => {
    if (!window.confirm('Are you sure you want to delete this recorded program?')) return;
    try {
      const res = await adminApi.deleteRecording(id);
      if (res.success) {
        setRecordings(prev => prev.filter(r => r.id !== id));
        setSuccessMsg('Recording deleted successfully.');
        setTimeout(() => setSuccessMsg(''), 3000);
      } else {
        alert(res.message || 'Failed to delete recording.');
      }
    } catch {
      alert('Error deleting recording.');
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!form.title) {
      alert('Please enter a title.');
      return;
    }

    setSubmitting(true);
    setUploadStatusText('');
    try {
      let finalVideoUrl = form.video_url;

      // Handle video file upload if selected
      if (sourceType === 'upload' && videoFile) {
        setUploadProgress(5);
        setUploadStatusText('Requesting AWS S3 upload token...');

        let uploadedToS3 = false;
        try {
          const presignRes = await adminApi.getS3PresignedUrl({
            filename: videoFile.name,
            file_type: videoFile.type || 'video/mp4',
            folder: 'recordings'
          });

          if (presignRes?.success && presignRes.data?.presigned_url) {
            setUploadStatusText('Uploading video directly to AWS S3...');
            await adminApi.uploadDirectToS3(presignRes.data.presigned_url, videoFile, (pct) => {
              setUploadProgress(pct);
              setUploadStatusText(`Uploading to AWS S3 (${pct}%)...`);
            });
            finalVideoUrl = presignRes.data.file_url;
            uploadedToS3 = true;
          }
        } catch (s3Err) {
          console.warn('S3 direct upload failed or not configured, falling back to server upload:', s3Err);
        }

        // If S3 upload wasn't used or failed, fall back to server upload endpoint
        if (!uploadedToS3) {
          setUploadStatusText('Uploading to server storage...');
          const uploadRes = await adminApi.uploadVideoFile(videoFile, (pct) => {
            setUploadProgress(pct);
            setUploadStatusText(`Uploading to server (${pct}%)...`);
          });
          if (uploadRes?.success) {
            finalVideoUrl = uploadRes.video_url;
          } else {
            throw new Error(uploadRes?.message || 'Video upload failed.');
          }
        }
      }

      if (!finalVideoUrl) {
        alert('Please provide a valid video URL or upload a video file.');
        setSubmitting(false);
        return;
      }

      // Handle thumbnail file upload to AWS S3 if selected
      let finalThumbnailUrl = form.thumbnail_url;
      if (thumbnailFile) {
        try {
          const thumbPresign = await adminApi.getS3PresignedUrl({
            filename: thumbnailFile.name,
            file_type: thumbnailFile.type || 'image/jpeg',
            folder: 'thumbnails'
          });
          if (thumbPresign?.success && thumbPresign.data?.presigned_url) {
            await adminApi.uploadDirectToS3(thumbPresign.data.presigned_url, thumbnailFile);
            finalThumbnailUrl = thumbPresign.data.file_url;
          }
        } catch (thumbErr) {
          console.warn('Thumbnail S3 upload skipped, falling back to form upload:', thumbErr);
        }
      }

      const durationSec = (parseInt(form.duration_minutes, 10) || 0) * 60;

      const formData = new FormData();
      formData.append('title', form.title);
      formData.append('description', form.description);
      formData.append('program_id', form.program_id);
      formData.append('program_date', form.program_date);
      formData.append('category', form.category);
      formData.append('status', form.status);
      formData.append('duration_seconds', durationSec);
      formData.append('video_url', finalVideoUrl);
      if (finalThumbnailUrl) formData.append('thumbnail_url', finalThumbnailUrl);
      if (thumbnailFile && !finalThumbnailUrl) formData.append('thumbnail', thumbnailFile);

      let res;
      if (isEditing) {
        res = await adminApi.updateRecording(editingId, formData);
      } else {
        res = await adminApi.createRecording(formData);
      }

      if (res.success) {
        setModalOpen(false);
        setSuccessMsg(isEditing ? 'Recording updated!' : 'Recording published to library!');
        setTimeout(() => setSuccessMsg(''), 4000);
        await fetchData();
      } else {
        alert(res.message || 'Failed to save recording.');
      }
    } catch (err) {
      console.error(err);
      alert(err.message || 'Error submitting recording.');
    } finally {
      setSubmitting(false);
    }
  };

  const filtered = recordings.filter(r => {
    if (activeTab === 'all') return true;
    return r.status === activeTab;
  });

  const totalHours = (recordings.reduce((sum, r) => sum + (r.duration_seconds || 0), 0) / 3600).toFixed(1);
  const publishedCount = recordings.filter(r => r.status === 'published').length;
  const draftCount = recordings.filter(r => r.status === 'draft').length;

  return (
    <div className="container max-w-7xl mx-auto px-4 py-8 animate-in fade-in duration-300">
      
      {/* ── Header ──────────────────────────────────────────────── */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
          <div className="flex items-center gap-2.5 mb-1">
            <span className="p-2 rounded-xl bg-purple-100 dark:bg-purple-950/60 text-[#5b3ee4] dark:text-[#a5b4fc]">
              <Film className="w-5 h-5" />
            </span>
            <h1 className="text-2xl sm:text-3xl font-bold text-gray-950 dark:text-white font-serif">
              Recorded Programs CMS
            </h1>
          </div>
          <p className="text-gray-500 dark:text-gray-400 text-sm">
            Publish, edit, and organize on-demand recordings of past live broadcasts.
          </p>
        </div>

        <button
          onClick={openCreateModal}
          className="inline-flex items-center justify-center gap-2 px-6 py-3 bg-[#5b3ee4] hover:bg-[#4f2bee] text-white font-bold rounded-full shadow-md shadow-indigo-500/20 transition-all active:scale-95 cursor-pointer"
        >
          <Plus className="w-5 h-5" /> Add Program Recording
        </button>
      </div>

      {/* ── Status Alerts ────────────────────────────────────────── */}
      {successMsg && (
        <div className="mb-6 p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 font-semibold flex items-center gap-2">
          <Check className="w-5 h-5" /> {successMsg}
        </div>
      )}
      {error && (
        <div className="mb-6 p-4 rounded-2xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 font-semibold flex items-center gap-2">
          <AlertCircle className="w-5 h-5" /> {error}
        </div>
      )}

      {/* ── Metrics Cards ────────────────────────────────────────── */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div className="bg-white dark:bg-gray-900 p-5 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-2xs">
          <span className="text-xs font-semibold text-gray-400 uppercase tracking-wider block mb-1">Total Recordings</span>
          <span className="text-2xl sm:text-3xl font-bold text-gray-950 dark:text-white">{recordings.length}</span>
        </div>
        <div className="bg-white dark:bg-gray-900 p-5 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-2xs">
          <span className="text-xs font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider block mb-1">Published (Live for Replay)</span>
          <span className="text-2xl sm:text-3xl font-bold text-emerald-600 dark:text-emerald-400">{publishedCount}</span>
        </div>
        <div className="bg-white dark:bg-gray-900 p-5 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-2xs">
          <span className="text-xs font-semibold text-amber-600 dark:text-amber-400 uppercase tracking-wider block mb-1">Drafts (Unpublished)</span>
          <span className="text-2xl sm:text-3xl font-bold text-amber-600 dark:text-amber-400">{draftCount}</span>
        </div>
        <div className="bg-white dark:bg-gray-900 p-5 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-2xs">
          <span className="text-xs font-semibold text-purple-600 dark:text-purple-400 uppercase tracking-wider block mb-1">Recorded Hours</span>
          <span className="text-2xl sm:text-3xl font-bold text-purple-600 dark:text-purple-400">{totalHours} hrs</span>
        </div>
      </div>

      {/* ── Filter Tabs ──────────────────────────────────────────── */}
      <div className="flex items-center gap-2 border-b border-gray-200 dark:border-gray-800 pb-3 mb-6">
        {[
          { key: 'all', label: 'All Content' },
          { key: 'published', label: 'Published' },
          { key: 'draft', label: 'Drafts' },
          { key: 'processing', label: 'Processing' },
        ].map(tab => (
          <button
            key={tab.key}
            onClick={() => setActiveTab(tab.key)}
            className={`px-4 py-2 rounded-xl text-sm font-semibold transition-all cursor-pointer ${
              activeTab === tab.key
                ? 'bg-gray-900 dark:bg-white text-white dark:text-gray-950 shadow-xs'
                : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* ── Recordings Table ─────────────────────────────────────── */}
      {loading ? (
        <div className="flex justify-center p-16">
          <Loader2 className="w-8 h-8 animate-spin text-[#5b3ee4]" />
        </div>
      ) : filtered.length === 0 ? (
        <div className="bg-white dark:bg-gray-900 rounded-3xl p-12 text-center border border-gray-100 dark:border-gray-800">
          <Film className="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-3" />
          <h3 className="font-serif font-bold text-lg text-gray-950 dark:text-white mb-1">No recordings found</h3>
          <p className="text-sm text-gray-500 dark:text-gray-400 mb-6">Create a recorded program entry after broadcasts to make it available for replay.</p>
          <button onClick={openCreateModal} className="px-5 py-2.5 bg-[#5b3ee4] text-white font-semibold rounded-full text-sm">
            Add First Recording
          </button>
        </div>
      ) : (
        <div className="bg-white dark:bg-gray-900 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-gray-50 dark:bg-gray-800/60 border-b border-gray-100 dark:border-gray-800">
                <tr>
                  <th className="p-4 font-bold text-gray-500 dark:text-gray-400">Recording</th>
                  <th className="p-4 font-bold text-gray-500 dark:text-gray-400">Associated Program</th>
                  <th className="p-4 font-bold text-gray-500 dark:text-gray-400">Broadcast Date</th>
                  <th className="p-4 font-bold text-gray-500 dark:text-gray-400">Duration</th>
                  <th className="p-4 font-bold text-gray-500 dark:text-gray-400">Status</th>
                  <th className="p-4 font-bold text-gray-500 dark:text-gray-400 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                {filtered.map(rec => {
                  const mins = Math.round((rec.duration_seconds || 0) / 60);
                  return (
                    <tr key={rec.id} className="hover:bg-gray-50/70 dark:hover:bg-gray-800/40 transition-colors">
                      <td className="p-4">
                        <div className="flex items-center gap-3 max-w-md">
                          <div className="w-16 h-10 rounded-xl overflow-hidden bg-gray-900 shrink-0 relative">
                            {rec.thumbnail_url ? (
                              <img src={rec.thumbnail_url} alt="" className="w-full h-full object-cover" />
                            ) : (
                              <div className="w-full h-full flex items-center justify-center text-gray-500">
                                <Play className="w-4 h-4" />
                              </div>
                            )}
                          </div>
                          <div>
                            <span className="font-bold text-gray-950 dark:text-white line-clamp-1 block">
                              {rec.title}
                            </span>
                            <span className="text-xs text-gray-400 dark:text-gray-500 line-clamp-1">
                              {rec.category || 'General'}
                            </span>
                          </div>
                        </div>
                      </td>
                      <td className="p-4 text-gray-600 dark:text-gray-300">
                        {rec.program_title || 'Standalone Broadcast'}
                      </td>
                      <td className="p-4 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                        {rec.program_date || '—'}
                      </td>
                      <td className="p-4 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                        {mins > 0 ? `${mins} mins` : '—'}
                      </td>
                      <td className="p-4 whitespace-nowrap">
                        {rec.status === 'published' ? (
                          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                            <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" /> Published
                          </span>
                        ) : rec.status === 'processing' ? (
                          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                            <span className="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse" /> Processing
                          </span>
                        ) : (
                          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                            <span className="w-1.5 h-1.5 rounded-full bg-amber-500" /> Draft
                          </span>
                        )}
                      </td>
                      <td className="p-4 text-right whitespace-nowrap">
                        <div className="flex items-center justify-end gap-2">
                          <button
                            onClick={() => handleToggleStatus(rec)}
                            className="px-3 py-1.5 text-xs font-semibold rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-750 text-gray-800 dark:text-gray-200 transition-colors"
                            title={rec.status === 'published' ? 'Unpublish to draft' : 'Publish for replay'}
                          >
                            {rec.status === 'published' ? 'Unpublish' : 'Publish'}
                          </button>
                          <button
                            onClick={() => openEditModal(rec)}
                            className="p-2 text-gray-500 hover:text-[#5b3ee4] bg-gray-100 dark:bg-gray-800 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 rounded-lg transition-colors"
                            title="Edit recording"
                          >
                            <Edit2 className="w-4 h-4" />
                          </button>
                          <button
                            onClick={() => handleDelete(rec.id)}
                            className="p-2 text-gray-500 hover:text-red-600 bg-gray-100 dark:bg-gray-800 hover:bg-red-50 dark:hover:bg-red-950/40 rounded-lg transition-colors"
                            title="Delete"
                          >
                            <Trash2 className="w-4 h-4" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* ── Add/Edit Modal ───────────────────────────────────────── */}
      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm overflow-y-auto">
          <div className="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 w-full max-w-3xl rounded-3xl shadow-2xl relative my-8 animate-in zoom-in-95 duration-200">
            
            {/* Modal Header */}
            <div className="sticky top-0 z-10 bg-white/95 dark:bg-gray-900/95 backdrop-blur-md px-6 sm:px-8 py-5 border-b border-gray-100 dark:border-gray-800 rounded-t-3xl flex items-center justify-between">
              <div>
                <h2 className="text-xl font-bold text-gray-950 dark:text-white font-serif">
                  {isEditing ? 'Edit Recorded Program' : 'Publish New Recorded Program'}
                </h2>
                <p className="text-xs text-gray-500 dark:text-gray-400">
                  Provide recording metadata and video source for on-demand playback.
                </p>
              </div>
              <button
                onClick={() => setModalOpen(false)}
                className="p-2 text-gray-400 hover:text-gray-900 dark:hover:text-white bg-gray-100 dark:bg-gray-800 rounded-full transition-colors cursor-pointer"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSubmit} className="p-6 sm:p-8 space-y-6">
              
              <div className="grid md:grid-cols-2 gap-6">
                
                {/* Left Column: Metadata */}
                <div className="space-y-4">
                  <div>
                    <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                      Program Title *
                    </label>
                    <input
                      type="text"
                      required
                      placeholder="e.g. A Day of Bliss with Pastor Chris"
                      value={form.title}
                      onChange={e => setForm({ ...form, title: e.target.value })}
                      className="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-[#5b3ee4]/20"
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                      Associate with Program / Event
                    </label>
                    <select
                      value={form.program_id}
                      onChange={e => setForm({ ...form, program_id: e.target.value })}
                      className="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white outline-none focus:ring-2 focus:ring-[#5b3ee4]/20"
                    >
                      <option value="">-- Standalone Recording --</option>
                      {programs.map(p => (
                        <option key={p.id} value={p.id}>{p.title}</option>
                      ))}
                    </select>
                  </div>

                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                        Broadcast Date
                      </label>
                      <input
                        type="date"
                        value={form.program_date}
                        onChange={e => setForm({ ...form, program_date: e.target.value })}
                        className="w-full px-3 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white text-sm outline-none"
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                        Duration (Minutes)
                      </label>
                      <input
                        type="number"
                        min="1"
                        value={form.duration_minutes}
                        onChange={e => setForm({ ...form, duration_minutes: e.target.value })}
                        className="w-full px-3 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white text-sm outline-none"
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                        Category
                      </label>
                      <select
                        value={form.category}
                        onChange={e => setForm({ ...form, category: e.target.value })}
                        className="w-full px-3 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white text-sm outline-none"
                      >
                        {CATEGORIES.map(c => (
                          <option key={c} value={c}>{c}</option>
                        ))}
                      </select>
                    </div>
                    <div>
                      <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                        Status
                      </label>
                      <select
                        value={form.status}
                        onChange={e => setForm({ ...form, status: e.target.value })}
                        className="w-full px-3 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white text-sm font-semibold outline-none"
                      >
                        <option value="published">Published (Live for Replay)</option>
                        <option value="draft">Draft (Private)</option>
                        <option value="processing">Processing</option>
                      </select>
                    </div>
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1.5">
                      Description
                    </label>
                    <textarea
                      rows="3"
                      placeholder="Add summary of preaching, hymns, testimony highlights..."
                      value={form.description}
                      onChange={e => setForm({ ...form, description: e.target.value })}
                      className="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white text-sm outline-none focus:ring-2 focus:ring-[#5b3ee4]/20"
                    />
                  </div>
                </div>

                {/* Right Column: Video Source & Poster */}
                <div className="space-y-4">
                  <div>
                    <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">
                      Video Storage & Source *
                    </label>

                    {/* Source Switcher */}
                    <div className="flex rounded-xl bg-gray-100 dark:bg-gray-800 p-1 mb-3">
                      <button
                        type="button"
                        onClick={() => setSourceType('upload')}
                        className={`flex-1 py-1.5 text-xs font-bold rounded-lg transition-all ${
                          sourceType === 'upload'
                            ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-2xs'
                            : 'text-gray-500 hover:text-gray-900 dark:hover:text-white'
                        }`}
                      >
                        <Cloud className="w-3.5 h-3.5 inline mr-1 text-amber-500" /> AWS S3 Upload
                      </button>
                      <button
                        type="button"
                        onClick={() => setSourceType('url')}
                        className={`flex-1 py-1.5 text-xs font-bold rounded-lg transition-all ${
                          sourceType === 'url'
                            ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-2xs'
                            : 'text-gray-500 hover:text-gray-900 dark:hover:text-white'
                        }`}
                      >
                        <LinkIcon className="w-3.5 h-3.5 inline mr-1" /> Direct URL / CDN
                      </button>
                    </div>

                    {sourceType === 'url' ? (
                      <div>
                        <input
                          type="url"
                          placeholder="https://... (AWS S3, CloudFront, Cloudflare R2, Bunny, MP4)"
                          value={form.video_url}
                          onChange={e => setForm({ ...form, video_url: e.target.value })}
                          className="w-full px-4 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white text-sm outline-none focus:ring-2 focus:ring-[#5b3ee4]/20"
                        />
                        <p className="text-[11px] text-gray-400 mt-1">Supports direct AWS S3 URLs, CloudFront CDN, MP4, and HLS (.m3u8).</p>
                      </div>
                    ) : (
                      <div className="space-y-3">
                        {/* AWS S3 Storage Badge */}
                        <div className="p-3 rounded-2xl bg-gradient-to-r from-amber-500/10 via-orange-500/10 to-transparent border border-amber-500/20 text-xs flex items-center justify-between">
                          <div className="flex items-center gap-2">
                            <Cloud className="w-4 h-4 text-amber-500 shrink-0" />
                            <div>
                              <p className="font-semibold text-gray-900 dark:text-white">Direct-to-AWS S3 Storage</p>
                              <p className="text-[11px] text-gray-500 dark:text-gray-400">
                                Video uploads directly to Amazon S3 (bypassing web server disk)
                              </p>
                            </div>
                          </div>
                          <span className="text-[10px] font-mono px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-700 dark:text-amber-300 font-bold shrink-0">
                            {s3Config.bucket ? `s3://${s3Config.bucket}` : 'AWS S3'}
                          </span>
                        </div>

                        <div className="border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-2xl p-4 text-center bg-gray-50/50 dark:bg-gray-800/30">
                          <Upload className="w-7 h-7 text-gray-400 mx-auto mb-2" />
                          <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300 cursor-pointer">
                            <span className="text-[#5b3ee4] hover:underline">Choose a video file</span> (MP4, WebM, MOV)
                            <input
                              type="file"
                              accept="video/mp4,video/webm,video/quicktime"
                              onChange={e => setVideoFile(e.target.files[0] || null)}
                              className="hidden"
                            />
                          </label>
                          {videoFile && (
                            <div className="mt-2 text-xs font-medium text-emerald-600 dark:text-emerald-400 truncate flex items-center justify-center gap-1.5">
                              <CheckCircle2 className="w-3.5 h-3.5" />
                              Selected: {videoFile.name} ({(videoFile.size / 1024 / 1024).toFixed(1)} MB)
                            </div>
                          )}
                          {uploadProgress > 0 && (
                            <div className="mt-3">
                              <div className="h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                <div className="h-full bg-gradient-to-r from-[#5b3ee4] to-amber-500 transition-all duration-300" style={{ width: `${uploadProgress}%` }} />
                              </div>
                              <div className="flex items-center justify-between text-[11px] text-gray-500 dark:text-gray-400 mt-1">
                                <span>{uploadStatusText || `Uploading ${uploadProgress}%`}</span>
                                <span className="font-bold">{uploadProgress}%</span>
                              </div>
                            </div>
                          )}
                        </div>
                      </div>
                    )}
                  </div>

                  {/* Thumbnail / Poster */}
                  <div>
                    <label className="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">
                      Cover Thumbnail / Poster
                    </label>
                    <div className="flex gap-4 items-start">
                      <div className="w-28 h-18 rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden shrink-0 relative flex items-center justify-center">
                        {thumbnailPreview ? (
                          <img src={thumbnailPreview} alt="" className="w-full h-full object-cover" />
                        ) : (
                          <span className="text-[10px] text-gray-400">No poster</span>
                        )}
                      </div>
                      <div className="flex-1 space-y-2">
                        <input
                          type="text"
                          placeholder="Poster Image URL"
                          value={form.thumbnail_url}
                          onChange={e => {
                            setForm({ ...form, thumbnail_url: e.target.value });
                            setThumbnailPreview(e.target.value);
                          }}
                          className="w-full px-3 py-1.5 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs text-gray-900 dark:text-white outline-none"
                        />
                        <label className="inline-block px-3 py-1 text-xs font-semibold rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 cursor-pointer">
                          Upload file
                          <input
                            type="file"
                            accept="image/*"
                            onChange={e => {
                              const f = e.target.files[0];
                              if (f) {
                                setThumbnailFile(f);
                                setThumbnailPreview(URL.createObjectURL(f));
                              }
                            }}
                            className="hidden"
                          />
                        </label>
                      </div>
                    </div>
                  </div>

                </div>

              </div>

              {/* Modal Footer */}
              <div className="pt-4 border-t border-gray-100 dark:border-gray-800 flex items-center justify-end gap-3">
                <button
                  type="button"
                  onClick={() => setModalOpen(false)}
                  className="px-5 py-2.5 rounded-full text-sm font-semibold text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={submitting}
                  className="px-7 py-2.5 rounded-full bg-[#5b3ee4] hover:bg-[#4f2bee] text-white text-sm font-bold shadow-md transition-all flex items-center gap-2 cursor-pointer disabled:opacity-50"
                >
                  {submitting ? <Loader2 className="w-4 h-4 animate-spin" /> : null}
                  {isEditing ? 'Save Changes' : 'Publish Recording'}
                </button>
              </div>

            </form>
          </div>
        </div>
      )}

    </div>
  );
}
