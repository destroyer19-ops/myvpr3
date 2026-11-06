import React from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import { Button } from '@/components/ui/FormElements';

export default function AdminCompareStreaming() {
  const navigate = useNavigate();
  const { user } = useAuth();

  return (
    <div className="p-8 max-w-6xl mx-auto space-y-8">
      <div>
        <h1 className="text-3xl font-bold mb-2">Streaming Architecture Comparison</h1>
        <p className="text-slate-600 dark:text-slate-400">
          Compare the two streaming modes side-by-side to evaluate latency, quality, and connection behavior.
        </p>
      </div>

      <div className="grid md:grid-cols-2 gap-8">
        {/* Option A: Low-Latency */}
        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm flex flex-col">
          <div className="flex-1">
            <h2 className="text-xl font-bold mb-4 text-blue-600 dark:text-blue-400">Option A: Low-Latency Streaming</h2>
            <ul className="list-disc list-inside space-y-2 text-slate-600 dark:text-slate-400 mb-6 text-sm">
              <li><strong>Architecture:</strong> Amazon IVS Channel → HLS → IVS Player</li>
              <li><strong>Latency:</strong> ~3 to 5 seconds (HLS Low-Latency)</li>
              <li><strong>Pros:</strong> Excellent compatibility, stable, highly scalable (millions of viewers), built-in recording.</li>
              <li><strong>Cons:</strong> Slower interaction for two-way communication.</li>
            </ul>
            <div className="bg-slate-50 dark:bg-slate-800 p-4 rounded-lg text-sm space-y-2 mb-6">
              <p className="font-semibold">Test Procedure:</p>
              <ol className="list-decimal list-inside space-y-1">
                <li>Open the Studio and start your camera.</li>
                <li>Click "Go Live".</li>
                <li>Open the Viewer link in a new incognito window.</li>
                <li>Observe the delay between your action and the video.</li>
              </ol>
            </div>
          </div>
          <div className="flex gap-4 flex-col sm:flex-row mt-auto">
            <Button className="flex-1" onClick={() => navigate('/studio')} variant="outline">
              Open Low-Latency Studio
            </Button>
            <Button className="flex-1" onClick={() => window.open(`/stream/${user?.username}`, '_blank')} variant="outline">
              Open Viewer (New Tab)
            </Button>
          </div>
        </div>

        {/* Option B: Real-Time WebRTC */}
        <div className="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-6 shadow-sm flex flex-col">
          <div className="flex-1">
            <h2 className="text-xl font-bold mb-4 text-green-600 dark:text-green-400">Option B: Real-Time WebRTC</h2>
            <ul className="list-disc list-inside space-y-2 text-slate-600 dark:text-slate-400 mb-6 text-sm">
              <li><strong>Architecture:</strong> Amazon IVS Stage → WebRTC → Native Video Element</li>
              <li><strong>Latency:</strong> ~300 to 500 milliseconds (WebRTC Real-Time)</li>
              <li><strong>Pros:</strong> Near-instant interaction, true real-time synchronization.</li>
              <li><strong>Cons:</strong> Stricter network requirements, may be more costly at extreme scale, limited to ~10,000 viewers currently.</li>
            </ul>
            <div className="bg-slate-50 dark:bg-slate-800 p-4 rounded-lg text-sm space-y-2 mb-6">
              <p className="font-semibold">Test Procedure:</p>
              <ol className="list-decimal list-inside space-y-1">
                <li>Open the Real-Time Studio and allow camera access.</li>
                <li>Click "Go Live".</li>
                <li>Open the Viewer link in a new incognito window.</li>
                <li>Observe the near-instant playback.</li>
              </ol>
            </div>
          </div>
          <div className="flex gap-4 flex-col sm:flex-row mt-auto">
            <Button className="flex-1 bg-green-600 hover:bg-green-700" onClick={() => navigate('/studio/realtime-test')}>
              Open Real-Time Studio
            </Button>
            <Button className="flex-1 bg-green-600 hover:bg-green-700" onClick={() => window.open(`/watch/realtime-test/${user?.username}`, '_blank')}>
              Open Viewer (New Tab)
            </Button>
          </div>
        </div>
      </div>
      
      <div className="mt-8 bg-blue-50 dark:bg-blue-900/20 p-6 rounded-xl border border-blue-100 dark:border-blue-800">
        <h3 className="font-bold text-blue-800 dark:text-blue-300 mb-2">Notice for Reviewers</h3>
        <p className="text-sm text-blue-700 dark:text-blue-400">
          Both streaming pipelines are fully isolated. The Real-Time implementation uses Amazon IVS Stages and WebRTC, while the Low-Latency implementation uses standard IVS Channels and HLS. Starting a broadcast in one will not interfere with the other. Ensure you end your broadcast when testing is complete.
        </p>
      </div>
    </div>
  );
}
