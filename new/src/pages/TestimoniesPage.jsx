import { useState, useEffect } from 'react';
import { Loader2, MessageSquare, Globe, Send, Check, X } from 'lucide-react';
import { testimoniesApi } from '../services/api';
import { COUNTRIES } from '../constants/countries';

export default function TestimoniesPage() {
  const [testimonies, setTestimonies] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState('');
  
  const [form, setForm] = useState({ name: '', email: '', country: '', text: '' });

  useEffect(() => {
    testimoniesApi.list()
      .then(res => { if (res.success) setTestimonies(res.data || []); })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSubmitting(true);
    setSubmitError('');
    try {
      const res = await testimoniesApi.submit({ full_name: form.name, email: form.email, country: form.country, testimony_text: form.text });
      if (res.success) {
        setSubmitted(true);
        setShowForm(false);
        setForm({ name: '', email: '', country: '', text: '' });
      } else {
        setSubmitError(res.message || 'Submission failed. Please try again.');
      }
    } catch {
      setSubmitError('An unexpected error occurred. Please try again.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-8 pb-28 lg:pb-16 animate-in fade-in duration-300">
      
      {/* Header */}
      <div className="text-center max-w-3xl mx-auto mb-10 sm:mb-14">
        <div className="inline-flex items-center gap-1.5 bg-indigo-50 dark:bg-indigo-950/60 text-[#5b3ee4] dark:text-[#a5b4fc] px-3.5 py-1 rounded-full text-xs font-semibold mb-3">
          <MessageSquare className="w-3.5 h-3.5" />
          <span>Community Testimonies</span>
        </div>
        <h1 className="font-serif text-3xl sm:text-4xl lg:text-5xl font-bold text-gray-950 dark:text-white tracking-tight mb-3">
          What God Has Done
        </h1>
        <p className="text-sm sm:text-base text-gray-600 dark:text-gray-300 leading-relaxed max-w-xl mx-auto">
          Stories of healing, miracles, and transformations shared by believers during our services and crusades.
        </p>
      </div>

      {/* Success banner */}
      {submitted && (
        <div className="max-w-xl mx-auto mb-8 flex items-start gap-3 bg-[#eefbf7] dark:bg-[#102622] border border-teal-200 dark:border-teal-800 text-teal-800 dark:text-teal-200 rounded-3xl p-5">
          <Check className="w-5 h-5 mt-0.5 text-teal-600 shrink-0" />
          <div>
            <p className="font-bold text-sm">Thank you for sharing your testimony!</p>
            <p className="text-xs mt-0.5 opacity-90">Your testimony has been received and will be reviewed before being published.</p>
          </div>
        </div>
      )}

      {/* Share CTA */}
      {!showForm && (
        <div className="flex justify-center mb-10">
          <button
            onClick={() => setShowForm(true)}
            className="inline-flex items-center gap-2 bg-[#5b3ee4] hover:bg-[#4f2bee] text-white font-semibold px-7 py-3 rounded-full text-sm shadow-md shadow-indigo-500/20 transition-all active:scale-95 cursor-pointer"
          >
            <Send className="w-4 h-4" />
            Share Your Testimony
          </button>
        </div>
      )}

      {/* Submission Form */}
      {showForm && (
        <div className="max-w-2xl mx-auto mb-12 bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-3xl shadow-sm overflow-hidden animate-in slide-in-from-top-4 duration-300">
          <div className="flex items-center justify-between p-6 border-b border-gray-100 dark:border-gray-800">
            <h2 className="font-serif text-xl font-bold text-gray-950 dark:text-white">Share Your Testimony</h2>
            <button onClick={() => setShowForm(false)} className="w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-400 hover:text-gray-700 dark:hover:text-white">
              <X className="w-4 h-4" />
            </button>
          </div>
          <form onSubmit={handleSubmit} className="p-6 space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-1.5">Full Name <span className="text-red-500">*</span></label>
                <input
                  type="text" required
                  value={form.name} onChange={e => setForm({ ...form, name: e.target.value })}
                  placeholder="Your name"
                  className="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl px-4 py-3 text-sm text-gray-950 dark:text-white outline-none focus:ring-2 focus:ring-[#5b3ee4]/20 transition-all"
                />
              </div>
              <div>
                <label className="block text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-1.5">Email <span className="text-gray-400 font-normal">(optional)</span></label>
                <input
                  type="email"
                  value={form.email} onChange={e => setForm({ ...form, email: e.target.value })}
                  placeholder="your@email.com"
                  className="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl px-4 py-3 text-sm text-gray-950 dark:text-white outline-none focus:ring-2 focus:ring-[#5b3ee4]/20 transition-all"
                />
              </div>
            </div>
            <div>
              <label className="block text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-1.5">Country</label>
              <select
                value={form.country} onChange={e => setForm({ ...form, country: e.target.value })}
                className="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl px-4 py-3 text-sm text-gray-950 dark:text-white outline-none focus:ring-2 focus:ring-[#5b3ee4]/20 transition-all"
              >
                <option value="">Select your country...</option>
                {COUNTRIES.map(c => (
                  <option key={c.code} value={c.code}>{c.name}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-1.5">Your Testimony <span className="text-red-500">*</span></label>
              <textarea
                required rows={4}
                value={form.text} onChange={e => setForm({ ...form, text: e.target.value })}
                placeholder="Share what God has done in your life..."
                className="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl px-4 py-3 text-sm text-gray-950 dark:text-white outline-none focus:ring-2 focus:ring-[#5b3ee4]/20 transition-all resize-none"
              />
            </div>
            {submitError && (
              <p className="text-red-500 text-xs bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 rounded-2xl px-4 py-2.5">{submitError}</p>
            )}
            <div className="flex gap-3 justify-end pt-2">
              <button type="button" onClick={() => setShowForm(false)} className="px-5 py-2.5 text-xs font-semibold text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-full transition-colors cursor-pointer">
                Cancel
              </button>
              <button type="submit" disabled={submitting} className="flex items-center gap-2 px-6 py-2.5 text-xs font-semibold bg-[#5b3ee4] hover:bg-[#4f2bee] text-white rounded-full transition-all shadow-md active:scale-95 disabled:opacity-50 cursor-pointer">
                {submitting && <Loader2 className="w-3.5 h-3.5 animate-spin" />}
                {submitting ? 'Submitting...' : 'Submit Testimony'}
              </button>
            </div>
          </form>
        </div>
      )}

      {/* Testimonies Grid */}
      {loading ? (
        <div className="flex justify-center py-16">
          <div className="w-10 h-10 border-4 border-gray-100 dark:border-gray-800 border-t-[#5b3ee4] rounded-full animate-spin" />
        </div>
      ) : testimonies.length === 0 ? (
        <div className="text-center py-16 bg-white dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 max-w-lg mx-auto">
          <MessageSquare className="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-3" />
          <h3 className="font-serif font-bold text-lg text-gray-900 dark:text-white mb-1">No testimonies yet</h3>
          <p className="text-xs text-gray-400">Be the first to share your praise report and testify.</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {testimonies.map((t, i) => (
            <TestimonyCard key={t.id || i} testimony={t} />
          ))}
        </div>
      )}
    </div>
  );
}

function TestimonyCard({ testimony }) {
  const [expanded, setExpanded] = useState(false);
  const isLong = testimony.text?.length > 200;
  const displayText = !expanded && isLong ? testimony.text.slice(0, 200) + '…' : testimony.text;

  return (
    <div className="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-3xl p-6 shadow-2xs hover:shadow-md transition-all flex flex-col justify-between">
      <blockquote className="text-gray-700 dark:text-gray-300 text-sm leading-relaxed mb-4">
        "{displayText}"
        {isLong && (
          <button onClick={() => setExpanded(!expanded)} className="ml-1 text-[#5b3ee4] text-xs font-semibold hover:underline cursor-pointer">
            {expanded ? 'Show less' : 'Read more'}
          </button>
        )}
      </blockquote>
      <div className="flex items-center justify-between pt-3 border-t border-gray-100 dark:border-gray-800">
        <div>
          <p className="font-serif font-bold text-sm text-gray-900 dark:text-white">{testimony.name || 'Anonymous Believer'}</p>
          {testimony.country && (
            <div className="flex items-center gap-1 mt-0.5">
              <Globe className="w-3 h-3 text-gray-400" />
              <span className="text-[11px] text-gray-400">{testimony.country}</span>
            </div>
          )}
        </div>
        <span className="text-[11px] text-gray-400 font-medium">
          {new Date(testimony.created_at || Date.now()).toLocaleDateString()}
        </span>
      </div>
    </div>
  );
}
