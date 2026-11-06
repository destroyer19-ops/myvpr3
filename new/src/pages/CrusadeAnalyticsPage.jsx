import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { Loader2, ArrowLeft, Users, Eye, Globe, Clock, BarChart2, MapPin } from 'lucide-react';
import { crusadesApi } from '../services/api';

export default function CrusadeAnalyticsPage() {
  const { code } = useParams();
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    crusadesApi.getAnalytics(code)
      .then(res => {
        if (res.success) setData(res.data);
        else setError(res.message || 'Failed to load analytics.');
      })
      .catch(() => setError('Failed to load analytics.'))
      .finally(() => setLoading(false));
  }, [code]);

  if (loading) return (
    <div className="flex items-center justify-center min-h-[60vh]">
      <Loader2 className="w-10 h-10 animate-spin text-blue-500" />
    </div>
  );

  if (error) return (
    <div className="max-w-2xl mx-auto mt-16 text-center">
      <p className="text-red-400">{error}</p>
      <Link to="/crusades" className="mt-4 inline-block text-blue-400 hover:underline">← Back to Crusades</Link>
    </div>
  );

  const ov = data?.overview || {};
  const totalViews = parseInt(ov.total_views) || 0;
  const uniqueVisitors = parseInt(ov.unique_visitors) || 0;
  const identified = parseInt(ov.identified_attendees) || 0;
  const anonymous = uniqueVisitors - identified;

  return (
    <div className="max-w-5xl mx-auto px-4 py-8">
      <Link to="/crusades" className="inline-flex items-center gap-2 text-gray-500 hover:text-blue-500 mb-8 transition-colors text-sm">
        <ArrowLeft className="w-4 h-4" /> Back to My Crusades
      </Link>

      <div className="mb-8">
        <h1 className="text-2xl font-bold text-gray-900 mb-1">Event Analytics</h1>
        <p className="text-gray-500 text-sm font-mono">/{code}</p>
      </div>

      {/* Overview Cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10">
        <StatCard icon={Eye} label="Total Views" value={totalViews.toLocaleString()} color="blue" />
        <StatCard icon={Users} label="Unique Visitors" value={uniqueVisitors.toLocaleString()} color="indigo" />
        <StatCard icon={BarChart2} label="Identified" value={identified.toLocaleString()} color="emerald" />
        <StatCard icon={Globe} label="Anonymous" value={Math.max(0, anonymous).toLocaleString()} color="slate" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        {/* Geography */}
        <div className="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
          <div className="flex items-center gap-2 mb-5">
            <MapPin className="w-5 h-5 text-slate-400" />
            <h2 className="text-base font-bold text-slate-700">Viewers by Country</h2>
          </div>
          {data?.countries?.length > 0 ? (
            <div className="space-y-3">
              {data.countries.map((c, i) => {
                const pct = totalViews > 0 ? Math.round((c.count / totalViews) * 100) : 0;
                return (
                  <div key={i}>
                    <div className="flex justify-between text-sm mb-1">
                      <span className="font-medium text-slate-700">{c.country || 'Unknown'}</span>
                      <span className="text-slate-400">{parseInt(c.count).toLocaleString()} ({pct}%)</span>
                    </div>
                    <div className="h-2 bg-slate-100 rounded-full overflow-hidden">
                      <div className="h-full bg-blue-500 rounded-full transition-all" style={{ width: `${pct}%` }} />
                    </div>
                  </div>
                );
              })}
            </div>
          ) : (
            <EmptyState label="No location data yet" />
          )}
        </div>

        {/* Stats summary */}
        <div className="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
          <div className="flex items-center gap-2 mb-5">
            <Clock className="w-5 h-5 text-slate-400" />
            <h2 className="text-base font-bold text-slate-700">Engagement</h2>
          </div>
          <div className="space-y-4">
            <MetricRow label="Watched 1+ minute" value={parseInt(ov.stayed_1m || 0).toLocaleString()} />
            <MetricRow label="Identified attendees" value={identified.toLocaleString()} />
            <MetricRow label="Anonymous viewers" value={Math.max(0, anonymous).toLocaleString()} />
          </div>
        </div>
      </div>

      {/* Attendee Table */}
      <div className="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div className="p-6 border-b border-slate-100">
          <h2 className="text-base font-bold text-slate-700">Identified Attendees</h2>
          <p className="text-xs text-slate-400 mt-0.5">Visitors who provided their information</p>
        </div>
        {data?.attendees?.length > 0 ? (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-slate-50 text-slate-500 text-xs uppercase tracking-wide">
                  <th className="text-left px-6 py-3 font-semibold">Name</th>
                  <th className="text-left px-6 py-3 font-semibold">Email</th>
                  <th className="text-left px-6 py-3 font-semibold">Country</th>
                  <th className="text-left px-6 py-3 font-semibold">City</th>
                  <th className="text-left px-6 py-3 font-semibold">Joined</th>
                  <th className="text-left px-6 py-3 font-semibold">Stayed 1min</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {data.attendees.map((a, i) => (
                  <tr key={i} className="hover:bg-slate-50/70 transition-colors">
                    <td className="px-6 py-3 font-medium text-slate-800">{a.name || '—'}</td>
                    <td className="px-6 py-3 text-slate-500">{a.email || '—'}</td>
                    <td className="px-6 py-3 text-slate-500">{a.country || '—'}</td>
                    <td className="px-6 py-3 text-slate-500">{a.city || '—'}</td>
                    <td className="px-6 py-3 text-slate-400">{new Date(a.first_seen).toLocaleString()}</td>
                    <td className="px-6 py-3">
                      {a.stayed_for_a_minute ? (
                        <span className="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded text-xs font-semibold">Yes</span>
                      ) : (
                        <span className="text-slate-300 text-xs">No</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="p-10 text-center">
            <EmptyState label="No identified attendees yet. They'll appear here after viewers submit their info." />
          </div>
        )}
      </div>
    </div>
  );
}

function StatCard({ icon: Icon, label, value, color }) {
  const colors = {
    blue: 'bg-blue-50 text-blue-600 border-blue-100',
    indigo: 'bg-indigo-50 text-indigo-600 border-indigo-100',
    emerald: 'bg-emerald-50 text-emerald-600 border-emerald-100',
    slate: 'bg-slate-50 text-slate-600 border-slate-200',
  };
  return (
    <div className={`rounded-2xl border p-5 ${colors[color]}`}>
      <Icon className="w-5 h-5 mb-3 opacity-70" />
      <div className="text-2xl font-bold">{value}</div>
      <div className="text-xs font-semibold mt-0.5 opacity-70 uppercase tracking-wide">{label}</div>
    </div>
  );
}

function MetricRow({ label, value }) {
  return (
    <div className="flex justify-between items-center">
      <span className="text-sm text-slate-500">{label}</span>
      <span className="text-sm font-bold text-slate-800">{value}</span>
    </div>
  );
}

function EmptyState({ label }) {
  return <p className="text-slate-400 text-sm">{label}</p>;
}
