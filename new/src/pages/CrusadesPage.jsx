import { useState, useEffect, useRef } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  Play, Users, Calendar, Video, Plus, X, Loader2,
  Clock, Globe, BarChart2, Flame, CheckCircle, FileEdit, XCircle,
  Share2, Copy, Check, Layers, Upload
} from 'lucide-react';
import { crusadesApi, videosApi, streamsApi } from '../services/api';

const STATUS_COLORS = {
  live:      'bg-[#fdf0f4] text-[#e11d48] border-pink-200 dark:bg-rose-950/40 dark:text-rose-400 dark:border-rose-900',
  scheduled: 'bg-[#eefbf7] text-teal-700 border-teal-200 dark:bg-teal-950/40 dark:text-teal-400 dark:border-teal-900',
  draft:     'bg-gray-100 text-gray-600 border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700',
  ended:     'bg-gray-100 text-gray-500 border-gray-200 dark:bg-gray-800/60 dark:text-gray-400 dark:border-gray-750',
  cancelled: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-900',
};

const STATUS_LABELS = {
  live:      'Live',
  scheduled: 'Scheduled',
  draft:     'Draft',
  ended:     'Ended',
  cancelled: 'Cancelled',
};

const SOURCE_TABS = [
  { id: 'external', label: 'YouTube / URL' },
  { id: 'vod',      label: 'VPR Library' },
  { id: 'live_tv',  label: 'Official Live TV' },
];

const TIMEZONES = [
  'UTC', 'Africa/Lagos', 'Africa/Accra', 'Africa/Nairobi', 'Africa/Johannesburg',
  'Europe/London', 'Europe/Berlin', 'America/New_York', 'America/Chicago',
  'America/Los_Angeles', 'America/Toronto', 'Asia/Dubai', 'Australia/Sydney',
];

export default function CrusadesPage() {
  const [crusades, setCrusades] = useState({ scheduled: [], active: [], past: [] });
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();

  // Create Crusade Modal state
  const [showCreate, setShowCreate] = useState(false);
  const [creating, setCreating] = useState(false);
  const [createError, setCreateError] = useState('');
  const [ecardFile, setEcardFile] = useState(null);
  const [ecardPreview, setEcardPreview] = useState(null);
  const [sourceType, setSourceType] = useState('external');
  const [vodList, setVodList] = useState([]);
  const [liveTvList, setLiveTvList] = useState([]);
  const [step, setStep] = useState(1); // 1=details, 2=source, 3=ecard, 4=schedule

  const [form, setForm] = useState({
    title: '',
    description: '',
    video_url: '',
    schedule_crusade: false,
    start_time: '',
    end_time: '',
    timezone: 'Africa/Lagos',
  });

  // Copy-link state per crusade
  const [copiedCode, setCopiedCode] = useState('');

  const resetModal = () => {
    setForm({ title: '', description: '', video_url: '', schedule_crusade: false, start_time: '', end_time: '', timezone: 'Africa/Lagos' });
    setSourceType('external');
    setStep(1);
    setCreateError('');
    setEcardFile(null);
    setEcardPreview(null);
  };

  useEffect(() => {
    if (showCreate && sourceType === 'vod' && vodList.length === 0) {
      videosApi.list().then(res => {
        if (res.success) setVodList(res.data || []);
      }).catch(() => {});
    }
    if (showCreate && sourceType === 'live_tv' && liveTvList.length === 0) {
      streamsApi.getLive().then(res => {
        if (res.success) setLiveTvList(res.streams || []);
      }).catch(() => {});
    }
  }, [showCreate, sourceType]);

  const handleEcardChange = (e) => {
    const file = e.target.files[0];
    if (file) {
      setEcardFile(file);
      setEcardPreview(URL.createObjectURL(file));
    }
  };

  const handleFormChange = (e) => {
    const { name, value, type, checked } = e.target;
    setForm(prev => ({ ...prev, [name]: type === 'checkbox' ? checked : value }));
  };

  const handleCreate = async (isDraft = false) => {
    if (!form.title.trim() || !form.description.trim() || !form.video_url) {
      setCreateError('Title, description, and a video source are required.');
      return;
    }
    if (form.schedule_crusade && !form.start_time) {
      setCreateError('Please set a start date/time for the scheduled event.');
      return;
    }
    setCreating(true);
    setCreateError('');
    try {
      const payload = { ...form, draft: isDraft };
      const res = await crusadesApi.create(payload);
      if (res.success) {
        setShowCreate(false);
        resetModal();
        fetchCrusades();
        navigate(`/crusade/${res.crusade_code}`);
      } else {
        setCreateError(res.message || 'Failed to create event.');
      }
    } catch (err) {
      setCreateError(err.message || 'An error occurred. Check your subscription.');
    } finally {
      setCreating(false);
    }
  };

  const fetchCrusades = async () => {
    setLoading(true);
    try {
      const res = await crusadesApi.list().catch(() => null);
      if (res?.success) {
        if (res.data && Array.isArray(res.data.scheduled)) {
          setCrusades(res.data);
        } else {
          const flat = res.crusades || res.data || [];
          if (Array.isArray(flat)) {
            setCrusades({
              active: flat.filter(c => c.is_active || c.status === 'live'),
              scheduled: flat.filter(c => !c.is_active && (c.status === 'scheduled' || c.status === 'draft')),
              past: flat.filter(c => !c.is_active && (c.status === 'ended' || c.status === 'cancelled')),
            });
          }
        }
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchCrusades(); }, []);

  const copyLink = (code) => {
    navigator.clipboard.writeText(`${window.location.origin}/crusade/${code}`);
    setCopiedCode(code);
    setTimeout(() => setCopiedCode(''), 2000);
  };

  const allEmpty = crusades.active.length === 0 && crusades.scheduled.length === 0 && crusades.past.length === 0;

  return (
    <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-8 pb-28 lg:pb-16 animate-in fade-in duration-300">
      
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8 sm:mb-12">
        <div>
          <div className="flex items-center gap-2 text-[#5b3ee4] dark:text-[#818cf8] mb-1 font-semibold text-xs tracking-wider uppercase">
            <Flame className="w-4 h-4" />
            <span>Digital Crusades</span>
          </div>
          <h1 className="font-serif text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight text-gray-950 dark:text-white mb-1.5">
            My Hosted Events
          </h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm sm:text-base">
            Manage, schedule, and analyse your digital crusades.
          </p>
        </div>
        
        <button
          onClick={() => { resetModal(); setShowCreate(true); }}
          className="self-start sm:self-auto shrink-0 inline-flex items-center gap-2 bg-[#5b3ee4] hover:bg-[#4f2bee] text-white font-semibold py-3 px-6 rounded-full transition-all text-sm shadow-md shadow-indigo-500/20 active:scale-95 cursor-pointer"
        >
          <Plus className="w-4 h-4" /> Create New Event
        </button>
      </div>

      {loading ? (
        <div className="flex justify-center py-24">
          <div className="w-10 h-10 border-4 border-gray-100 dark:border-gray-800 border-t-[#5b3ee4] rounded-full animate-spin" />
        </div>
      ) : allEmpty ? (
        <EmptyState onCreateClick={() => { resetModal(); setShowCreate(true); }} />
      ) : (
        <div className="space-y-10 sm:space-y-12">
          {crusades.active.length > 0 && (
            <Section
              title="Live Now"
              icon={<span className="w-2.5 h-2.5 bg-[#e11d48] rounded-full animate-pulse" />}
              crusades={crusades.active}
              navigate={navigate}
              onCopy={copyLink}
              copiedCode={copiedCode}
            />
          )}
          {crusades.scheduled.length > 0 && (
            <Section
              title="Scheduled & Drafts"
              icon={<Clock className="w-5 h-5 text-teal-600 dark:text-teal-400" />}
              crusades={crusades.scheduled}
              navigate={navigate}
              onCopy={copyLink}
              copiedCode={copiedCode}
            />
          )}
          {crusades.past.length > 0 && (
            <Section
              title="Past Events"
              icon={<Layers className="w-5 h-5 text-gray-400" />}
              crusades={crusades.past}
              navigate={navigate}
              onCopy={copyLink}
              copiedCode={copiedCode}
            />
          )}
        </div>
      )}

      {/* ── Create Crusade Modal ────────────────────────────────── */}
      {showCreate && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in duration-200">
          <div className="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-3xl w-full max-w-xl shadow-2xl max-h-[92vh] flex flex-col overflow-hidden">
            {/* Modal header */}
            <div className="flex items-center justify-between p-6 border-b border-gray-100 dark:border-gray-800 shrink-0">
              <div>
                <h3 className="font-serif text-2xl font-bold text-gray-950 dark:text-white flex items-center gap-2">
                  <Flame className="w-5 h-5 text-[#5b3ee4]" /> Create New Event
                </h3>
                <div className="flex flex-wrap gap-1.5 mt-3">
                  {['Details', 'Video Source', 'E-Card', 'Schedule'].map((label, idx) => (
                    <div key={label} className="flex items-center gap-1">
                      <button
                        type="button"
                        onClick={() => setStep(idx + 1)}
                        className={`text-xs font-semibold px-3 py-1 rounded-full transition-all ${
                          step === idx + 1 
                            ? 'bg-[#5b3ee4] text-white shadow-xs' 
                            : step > idx + 1 
                            ? 'bg-purple-100 dark:bg-purple-950/60 text-[#5b3ee4] dark:text-[#a5b4fc]' 
                            : 'bg-gray-100 dark:bg-gray-800 text-gray-400'
                        }`}
                      >
                        {idx + 1}. {label}
                      </button>
                    </div>
                  ))}
                </div>
              </div>
              <button onClick={() => setShowCreate(false)} className="w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-500 hover:text-gray-950 dark:hover:text-white transition-colors">
                <X className="w-4 h-4" />
              </button>
            </div>

            {/* Modal body */}
            <div className="overflow-y-auto flex-1 p-6 space-y-5">
              {createError && (
                <div className="bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 text-sm px-4 py-3 rounded-2xl flex items-start gap-2">
                  <XCircle className="w-4 h-4 mt-0.5 shrink-0" />
                  {createError}
                </div>
              )}

              {/* Step 1: Details */}
              {step === 1 && (
                <div className="space-y-4">
                  <div>
                    <label className="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1.5">
                      Event Title <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="text" name="title" value={form.title} onChange={handleFormChange} required
                      placeholder="e.g. Healing & Deliverance Crusade"
                      className="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-950 dark:text-white px-4 py-3 rounded-2xl outline-none focus:ring-2 focus:ring-[#5b3ee4]/20 transition-all placeholder-gray-400 text-sm"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1.5">
                      Description <span className="text-red-500">*</span>
                    </label>
                    <textarea
                      name="description" value={form.description} onChange={handleFormChange} required rows={3}
                      placeholder="What is this event about? Who should attend?"
                      className="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-950 dark:text-white px-4 py-3 rounded-2xl outline-none focus:ring-2 focus:ring-[#5b3ee4]/20 transition-all resize-none placeholder-gray-400 text-sm"
                    />
                  </div>
                </div>
              )}

              {/* Step 2: Source */}
              {step === 2 && (
                <div className="space-y-4">
                  <div>
                    <label className="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">Video Source Type</label>
                    <div className="grid grid-cols-3 gap-2">
                      {SOURCE_TABS.map(tab => (
                        <button
                          key={tab.id} type="button"
                          onClick={() => { setSourceType(tab.id); setForm(f => ({ ...f, video_url: '' })); }}
                          className={`py-2 px-3 rounded-full text-xs font-semibold transition-all border ${
                            sourceType === tab.id 
                              ? 'bg-[#5b3ee4] border-[#5b3ee4] text-white' 
                              : 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300'
                          }`}
                        >
                          {tab.label}
                        </button>
                      ))}
                    </div>
                  </div>

                  {sourceType === 'external' && (
                    <div>
                      <label className="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1.5">YouTube URL or Direct Video URL <span className="text-red-500">*</span></label>
                      <input
                        type="url" name="video_url" value={form.video_url} onChange={handleFormChange}
                        placeholder="https://youtube.com/watch?v=... or https://..."
                        className="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-950 dark:text-white px-4 py-3 rounded-2xl outline-none focus:ring-2 focus:ring-[#5b3ee4]/20 transition-all placeholder-gray-400 text-sm"
                      />
                    </div>
                  )}
                  {sourceType === 'vod' && (
                    <div>
                      <label className="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1.5">Select from VPR Library <span className="text-red-500">*</span></label>
                      {vodList.length === 0 ? (
                        <div className="text-gray-400 text-sm py-4 text-center">Loading videos...</div>
                      ) : (
                        <select
                          name="video_url" value={form.video_url} onChange={handleFormChange}
                          className="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-950 dark:text-white px-4 py-3 rounded-2xl outline-none focus:ring-2 focus:ring-[#5b3ee4]/20 text-sm"
                        >
                          <option value="">Select a video...</option>
                          {vodList.map(v => (
                            <option key={v.id} value={`VPR_VOD_${v.id}`}>{v.title}</option>
                          ))}
                        </select>
                      )}
                    </div>
                  )}
                  {sourceType === 'live_tv' && (
                    <div>
                      <label className="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1.5">Select Official Live TV Channel <span className="text-red-500">*</span></label>
                      {liveTvList.length === 0 ? (
                        <div className="text-gray-400 text-sm py-4 text-center">Loading channels...</div>
                      ) : (
                        <select
                          name="video_url" value={form.video_url} onChange={handleFormChange}
                          className="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-950 dark:text-white px-4 py-3 rounded-2xl outline-none focus:ring-2 focus:ring-[#5b3ee4]/20 text-sm"
                        >
                          <option value="">Select a channel...</option>
                          {liveTvList.map(ch => (
                            <option key={ch.id} value={`LIVE_STREAM_OFFICIAL_${ch.id}`}>{ch.title || ch.name}</option>
                          ))}
                        </select>
                      )}
                    </div>
                  )}
                </div>
              )}

              {/* Step 3: E-Card */}
              {step === 3 && (
                <div className="space-y-4">
                  <label className="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1">Event E-Card / Flyer (Optional)</label>
                  <p className="text-xs text-gray-400 mb-3">Upload an image to represent your crusade on the platform and social cards.</p>
                  
                  <label className="flex flex-col items-center justify-center w-full h-44 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-2xl bg-gray-50 dark:bg-gray-800/50 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer transition-colors relative overflow-hidden group">
                    {ecardPreview ? (
                      <>
                        <img src={ecardPreview} alt="E-card preview" className="w-full h-full object-cover" />
                        <div className="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                          <span className="bg-white text-gray-950 px-4 py-2 rounded-full text-xs font-semibold flex items-center gap-2">
                            <Upload className="w-3.5 h-3.5"/> Replace Image
                          </span>
                        </div>
                      </>
                    ) : (
                      <div className="flex flex-col items-center justify-center p-5 text-center">
                        <div className="w-10 h-10 bg-indigo-50 dark:bg-indigo-950/60 text-[#5b3ee4] rounded-full flex items-center justify-center mb-2">
                          <Upload className="w-5 h-5" />
                        </div>
                        <p className="text-sm font-semibold text-gray-700 dark:text-gray-300">Click to upload flyer</p>
                        <p className="text-xs text-gray-400 mt-0.5">JPEG, PNG up to 5MB</p>
                      </div>
                    )}
                    <input type="file" className="hidden" accept="image/*" onChange={handleEcardChange} />
                  </label>
                  
                  {ecardFile && (
                    <div className="flex justify-end">
                      <button type="button" onClick={() => { setEcardFile(null); setEcardPreview(null); }} className="text-xs text-red-500 font-semibold cursor-pointer">
                        Remove Image
                      </button>
                    </div>
                  )}
                </div>
              )}

              {/* Step 4: Schedule */}
              {step === 4 && (
                <div className="space-y-4">
                  <div className="flex items-start gap-3 bg-[#eefbf7] dark:bg-[#102622] border border-teal-100 dark:border-teal-900 rounded-2xl p-4">
                    <input
                      id="schedule_toggle" type="checkbox" name="schedule_crusade"
                      checked={form.schedule_crusade} onChange={handleFormChange}
                      className="mt-0.5 w-4 h-4 accent-[#5b3ee4] cursor-pointer"
                    />
                    <label htmlFor="schedule_toggle" className="cursor-pointer">
                      <span className="block text-sm font-bold text-teal-950 dark:text-teal-100">Schedule for a future date</span>
                      <span className="text-xs text-teal-800/80 dark:text-teal-300 mt-0.5 block">
                        Visitors will see a countdown until the event starts.
                      </span>
                    </label>
                  </div>

                  {form.schedule_crusade && (
                    <>
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                          <label className="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1.5">Start Date & Time <span className="text-red-500">*</span></label>
                          <input
                            type="datetime-local" name="start_time" value={form.start_time} onChange={handleFormChange}
                            className="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-950 dark:text-white px-4 py-2.5 rounded-2xl outline-none focus:ring-2 focus:ring-[#5b3ee4]/20 text-sm"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1.5">End Date & Time <span className="text-gray-400 text-xs">(optional)</span></label>
                          <input
                            type="datetime-local" name="end_time" value={form.end_time} onChange={handleFormChange}
                            className="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-950 dark:text-white px-4 py-2.5 rounded-2xl outline-none focus:ring-2 focus:ring-[#5b3ee4]/20 text-sm"
                          />
                        </div>
                      </div>
                      <div>
                        <label className="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1.5">Timezone</label>
                        <select
                          name="timezone" value={form.timezone} onChange={handleFormChange}
                          className="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-950 dark:text-white px-4 py-2.5 rounded-2xl outline-none focus:ring-2 focus:ring-[#5b3ee4]/20 text-sm"
                        >
                          {TIMEZONES.map(tz => (
                            <option key={tz} value={tz}>{tz}</option>
                          ))}
                        </select>
                      </div>
                    </>
                  )}

                  {!form.schedule_crusade && (
                    <div className="flex items-center gap-2 text-teal-700 dark:text-teal-300 bg-[#eefbf7] dark:bg-[#102622] rounded-2xl p-4 text-sm font-medium">
                      <CheckCircle className="w-4 h-4 text-teal-600 shrink-0" />
                      Event will go <strong className="ml-1">Live immediately</strong> after publishing.
                    </div>
                  )}
                </div>
              )}
            </div>

            {/* Modal footer */}
            <div className="p-4 sm:p-6 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between gap-3 bg-gray-50/50 dark:bg-gray-850 shrink-0">
              {step > 1 ? (
                <button
                  type="button" onClick={() => setStep(s => s - 1)}
                  className="px-5 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-full hover:bg-gray-50 transition-colors"
                >
                  ← Back
                </button>
              ) : <div />}

              {step < 4 ? (
                <button
                  type="button" onClick={() => setStep(s => s + 1)}
                  className="px-6 py-2.5 text-sm font-semibold bg-[#5b3ee4] hover:bg-[#4f2bee] text-white rounded-full transition-all shadow-sm active:scale-95"
                >
                  Next →
                </button>
              ) : (
                <div className="flex gap-2">
                  <button
                    type="button" onClick={() => handleCreate(true)} disabled={creating}
                    className="px-4 py-2.5 text-sm font-semibold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-full hover:bg-gray-200 transition-colors flex items-center gap-1.5"
                  >
                    <FileEdit className="w-3.5 h-3.5" /> Save Draft
                  </button>
                  <button
                    type="button" onClick={() => handleCreate(false)} disabled={creating}
                    className="px-6 py-2.5 text-sm font-semibold bg-[#5b3ee4] hover:bg-[#4f2bee] text-white rounded-full transition-all shadow-md flex items-center gap-2 justify-center"
                  >
                    {creating ? <Loader2 className="w-4 h-4 animate-spin" /> : <><CheckCircle className="w-4 h-4" /> Publish Event</>}
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>
      )}

    </div>
  );
}

function Section({ title, icon, crusades, navigate, onCopy, copiedCode }) {
  return (
    <div>
      <h2 className="font-serif text-2xl sm:text-3xl font-bold text-gray-950 dark:text-white mb-5 flex items-center gap-2">
        {icon} {title}
      </h2>
      <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-5 sm:gap-6">
        {crusades.map((c, i) => (
          <CrusadeCard key={c.code || i} crusade={c} navigate={navigate} onCopy={onCopy} copiedCode={copiedCode} />
        ))}
      </div>
    </div>
  );
}

function CrusadeCard({ crusade, navigate, onCopy, copiedCode }) {
  const status = crusade.status || (crusade.is_active ? 'live' : 'ended');
  const code = crusade.code || crusade.crusade_code;

  return (
    <div className="bg-white dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 overflow-hidden flex flex-col hover:shadow-md transition-all group">
      {/* Thumbnail area */}
      <div className="h-40 bg-gradient-to-br from-[#5b3ee4] to-[#3b1fb8] relative flex items-center justify-center overflow-hidden">
        {crusade.thumbnail_url ? (
          <img src={crusade.thumbnail_url} className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="Crusade E-Card" />
        ) : (
          <div className="flex flex-col items-center justify-center text-white/50">
            <Video className="w-10 h-10 mb-1" />
            <span className="text-xs font-serif font-bold text-white/80">Digital Crusade</span>
          </div>
        )}
        <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent" />
        <div className={`absolute top-3 left-3 text-[11px] font-bold px-3 py-1 rounded-full border shadow-2xs ${STATUS_COLORS[status] || STATUS_COLORS.draft}`}>
          {status === 'live' && <span className="inline-block w-1.5 h-1.5 rounded-full bg-[#e11d48] animate-pulse mr-1.5" />}
          {STATUS_LABELS[status] || status}
        </div>
      </div>

      <div className="p-5 flex-1 flex flex-col justify-between">
        <div>
          <h3 className="font-bold text-base text-gray-950 dark:text-white mb-1.5 line-clamp-2 leading-snug group-hover:text-[#5b3ee4] transition-colors">
            {crusade.title || 'Untitled Event'}
          </h3>
          <p className="text-gray-400 dark:text-gray-500 text-xs flex items-center gap-1.5 mb-4 font-medium">
            <Calendar className="w-3.5 h-3.5" />
            {crusade.startTime
              ? new Date(crusade.startTime).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
              : new Date(crusade.created_at || Date.now()).toLocaleDateString()}
          </p>
        </div>

        <div className="flex items-center gap-2 pt-2 border-t border-gray-100 dark:border-gray-800">
          <button
            onClick={() => navigate(`/crusade/${code}`)}
            className="flex-1 py-2.5 rounded-full bg-[#5b3ee4] hover:bg-[#4f2bee] text-white text-xs font-semibold transition-all flex items-center justify-center gap-1.5 shadow-2xs active:scale-95 cursor-pointer"
          >
            <Play className="w-3.5 h-3.5 fill-white" /> Open
          </button>
          <Link
            to={`/crusades/${code}/analytics`}
            className="py-2.5 px-3.5 rounded-full bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-750 text-gray-700 dark:text-gray-300 text-xs font-semibold transition-colors flex items-center gap-1"
            title="Analytics"
          >
            <BarChart2 className="w-3.5 h-3.5" />
          </Link>
          <button
            onClick={() => onCopy(code)}
            title="Copy viewer link"
            className="py-2.5 px-3.5 rounded-full bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-750 text-gray-700 dark:text-gray-300 text-xs font-semibold transition-colors flex items-center gap-1 cursor-pointer"
          >
            {copiedCode === code ? <Check className="w-3.5 h-3.5 text-teal-600" /> : <Share2 className="w-3.5 h-3.5" />}
          </button>
        </div>
      </div>
    </div>
  );
}

function EmptyState({ onCreateClick }) {
  return (
    <div className="flex flex-col items-center justify-center py-20 sm:py-24 text-center">
      <div className="w-20 h-20 rounded-3xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-100 dark:border-indigo-900/60 flex items-center justify-center mb-5 text-[#5b3ee4]">
        <Flame className="w-10 h-10" />
      </div>
      <h2 className="font-serif text-2xl sm:text-3xl font-bold text-gray-950 dark:text-white mb-2">No hosted events yet</h2>
      <p className="text-gray-500 dark:text-gray-400 max-w-md mb-6 text-sm leading-relaxed">
        Create your first Digital Crusade to broadcast a live service, prayer session, or special event to your audience — no technical setup required.
      </p>
      <button
        onClick={onCreateClick}
        className="inline-flex items-center gap-2 bg-[#5b3ee4] hover:bg-[#4f2bee] text-white font-semibold py-3 px-8 rounded-full transition-all text-sm shadow-md shadow-indigo-500/20 active:scale-95 cursor-pointer"
      >
        <Plus className="w-4 h-4" /> Create Your First Event
      </button>
    </div>
  );
}
