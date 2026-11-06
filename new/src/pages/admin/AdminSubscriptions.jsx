import { useState, useEffect } from 'react';
import { 
  Loader2, CheckCircle, XCircle, CreditCard, ExternalLink, 
  Search, Filter, ShieldCheck, DollarSign, Users, AlertTriangle, 
  Eye, X, ArrowUpRight, Ban, RefreshCw, Calendar, CheckCircle2,
  Sliders, Plus, Settings
} from 'lucide-react';
import { adminApi } from '../../services/api';

const TABS = [
  { id: 'transactions', label: 'Payment Transactions' },
  { id: 'pending', label: 'Pending Approvals' },
  { id: 'active', label: 'Active Subscribers' },
  { id: 'plans', label: 'Subscription Plans' },
  { id: 'gateway', label: 'Squad Gateway Status' },
];

export default function AdminSubscriptions() {
  const [activeTab, setActiveTab] = useState('transactions');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  // Overview metrics state
  const [overview, setOverview] = useState({
    totalSubscribers: 0,
    activeSubscribers: 0,
    pendingTransactions: 0,
    expiredSubscribers: 0,
    totalRevenue: 0,
    successCount: 0,
    failedCount: 0,
    planDistribution: []
  });

  // Transactions state & filters
  const [transactions, setTransactions] = useState([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [filterStatus, setFilterStatus] = useState('all');
  const [filterGateway, setFilterGateway] = useState('all');
  const [filterFromDate, setFilterFromDate] = useState('');
  const [filterToDate, setFilterToDate] = useState('');

  // Subscriptions lists (pending manual, active, expired)
  const [subData, setSubData] = useState({ pending: [], active: [], expired: [] });

  // Subscription plans management
  const [plans, setPlans] = useState([]);

  // Gateway config
  const [gatewayConfig, setGatewayConfig] = useState(null);

  // Modals & Action Loaders
  const [actionLoading, setActionLoading] = useState(null);
  const [selectedTxn, setSelectedTxn] = useState(null);
  const [auditLogs, setAuditLogs] = useState([]);
  const [loadingAudit, setLoadingAudit] = useState(false);

  // Cancellation Modal
  const [cancelModalSub, setCancelModalSub] = useState(null);
  const [cancelReason, setCancelReason] = useState('');

  useEffect(() => {
    fetchInitialData();
  }, []);

  async function fetchInitialData() {
    setLoading(true);
    try {
      const [overviewRes, subRes, plansRes, configRes] = await Promise.all([
        adminApi.getPaymentOverview().catch(() => null),
        adminApi.listSubscriptions().catch(() => null),
        adminApi.listSubscriptionPlans().catch(() => null),
        adminApi.getPaymentConfig().catch(() => null),
      ]);

      if (overviewRes?.success) setOverview(overviewRes.data);
      if (subRes?.success) setSubData(subRes.data);
      if (plansRes?.success) setPlans(plansRes.data);
      if (configRes?.success) setGatewayConfig(configRes.data);

      await fetchTransactions();
    } catch (e) {
      console.error(e);
      setError('Failed to load subscription administration data.');
    } finally {
      setLoading(false);
    }
  }

  async function fetchTransactions() {
    try {
      const params = {};
      if (searchQuery) params.q = searchQuery;
      if (filterStatus !== 'all') params.status = filterStatus;
      if (filterGateway !== 'all') params.gateway = filterGateway;
      if (filterFromDate) params.from_date = filterFromDate;
      if (filterToDate) params.to_date = filterToDate;

      const res = await adminApi.listTransactions(params);
      if (res?.success) {
        setTransactions(res.data);
      }
    } catch (e) {
      console.error('Error fetching transactions:', e);
    }
  }

  // Handle Search submit
  function handleFilterSubmit(e) {
    e.preventDefault();
    fetchTransactions();
  }

  // Inspect Transaction & load audit logs
  async function handleInspectTransaction(txn) {
    setSelectedTxn(txn);
    setLoadingAudit(true);
    setAuditLogs([]);
    try {
      const res = await adminApi.getTransactionAudit(txn.id);
      if (res?.success) setAuditLogs(res.data);
    } catch (err) {
      console.error('Failed to load transaction audit:', err);
    } finally {
      setLoadingAudit(false);
    }
  }

  // Approve manual payment
  async function handleApprove(id) {
    if (!window.confirm('Approve this payment and activate the user subscription?')) return;
    setActionLoading(id + '_approve');
    try {
      const res = await adminApi.approveSubscription(id);
      if (res?.success) {
        await fetchInitialData();
      } else alert(res?.message || 'Failed to approve.');
    } catch (e) {
      alert('Error approving subscription.');
    } finally {
      setActionLoading(null);
    }
  }

  // Reject manual payment
  async function handleReject(id) {
    if (!window.confirm('Reject this subscription payment?')) return;
    setActionLoading(id + '_reject');
    try {
      const res = await adminApi.rejectSubscription(id);
      if (res?.success) {
        await fetchInitialData();
      } else alert(res?.message || 'Failed to reject.');
    } catch (e) {
      alert('Error rejecting subscription.');
    } finally {
      setActionLoading(null);
    }
  }

  // Cancel an active subscription
  async function handleCancelSubscriptionSubmit(e) {
    e.preventDefault();
    if (!cancelModalSub) return;
    setActionLoading('cancel_' + cancelModalSub.id);
    try {
      const res = await adminApi.cancelSubscription(cancelModalSub.id, { reason: cancelReason });
      if (res?.success) {
        setCancelModalSub(null);
        setCancelReason('');
        await fetchInitialData();
      } else {
        alert(res?.message || 'Failed to cancel subscription.');
      }
    } catch (err) {
      alert('Error processing subscription cancellation.');
    } finally {
      setActionLoading(null);
    }
  }

  // Toggle plan visibility
  async function handleTogglePlan(id) {
    try {
      const res = await adminApi.toggleSubscriptionPlan(id);
      if (res?.success) {
        const plansRes = await adminApi.listSubscriptionPlans();
        if (plansRes?.success) setPlans(plansRes.data);
      } else alert(res?.message || 'Failed to update plan.');
    } catch (e) {
      alert('Error toggling plan.');
    }
  }

  return (
    <div className="container max-w-7xl mx-auto px-4 py-8 pb-28 md:pb-12 text-gray-950 dark:text-white transition-colors duration-200">
      
      {/* ── Top Header ────────────────────────────────────────────────────── */}
      <div className="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-gray-200 dark:border-gray-800 pb-6">
        <div>
          <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-[#5b3ee4] dark:text-purple-400 mb-1">
            <CreditCard className="w-4 h-4" /> Billing & Revenue Management
          </div>
          <h1 className="text-3xl font-serif font-bold text-gray-950 dark:text-white">
            Subscriptions & Squad Payments
          </h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm mt-1">
            Track transactions, verify Squad gateway payments, manage active memberships, and audit revenue.
          </p>
        </div>
        <div className="flex items-center gap-3">
          <button
            onClick={fetchInitialData}
            className="flex items-center gap-2 px-4 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-xs font-semibold cursor-pointer transition-colors"
          >
            <RefreshCw className="w-3.5 h-3.5" /> Refresh Data
          </button>
        </div>
      </div>

      {error && (
        <div className="bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900/50 text-red-600 dark:text-red-400 p-4 rounded-2xl mb-6 text-sm">
          {error}
        </div>
      )}

      {/* ── Summary Metrics Bar ────────────────────────────────────────────── */}
      <div className="grid grid-cols-2 lg:grid-cols-6 gap-4 mb-8">
        <div className="bg-white dark:bg-gray-900 p-4 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm">
          <span className="text-[11px] font-semibold text-gray-500 uppercase tracking-wider block mb-1">Total Members</span>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-gray-950 dark:text-white">{overview.totalSubscribers}</span>
          </div>
        </div>

        <div className="bg-white dark:bg-gray-900 p-4 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm">
          <span className="text-[11px] font-semibold text-gray-500 uppercase tracking-wider block mb-1">Active Subscriptions</span>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-emerald-600 dark:text-emerald-400">{overview.activeSubscribers}</span>
          </div>
        </div>

        <div className="bg-white dark:bg-gray-900 p-4 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm">
          <span className="text-[11px] font-semibold text-gray-500 uppercase tracking-wider block mb-1">Pending Review</span>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-amber-500">{overview.pendingTransactions}</span>
          </div>
        </div>

        <div className="bg-white dark:bg-gray-900 p-4 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm">
          <span className="text-[11px] font-semibold text-gray-500 uppercase tracking-wider block mb-1">Verified Revenue</span>
          <div className="flex items-baseline gap-1">
            <span className="text-xs font-bold text-gray-400">₦</span>
            <span className="text-2xl font-black text-gray-950 dark:text-white">
              {Number(overview.totalRevenue).toLocaleString()}
            </span>
          </div>
        </div>

        <div className="bg-white dark:bg-gray-900 p-4 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm">
          <span className="text-[11px] font-semibold text-gray-500 uppercase tracking-wider block mb-1">Approved Payments</span>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-green-600 dark:text-green-400">{overview.successCount}</span>
          </div>
        </div>

        <div className="bg-white dark:bg-gray-900 p-4 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm">
          <span className="text-[11px] font-semibold text-gray-500 uppercase tracking-wider block mb-1">Failed / Rejected</span>
          <div className="flex items-baseline gap-2">
            <span className="text-2xl font-black text-red-500">{overview.failedCount}</span>
          </div>
        </div>
      </div>

      {/* ── Navigation Tabs ────────────────────────────────────────────────── */}
      <div className="flex overflow-x-auto border-b border-gray-200 dark:border-gray-800 mb-6 hide-scrollbar gap-2">
        {TABS.map((tab) => {
          let countBadge = null;
          if (tab.id === 'pending') countBadge = subData.pending.length;
          if (tab.id === 'active') countBadge = subData.active.length;
          if (tab.id === 'transactions') countBadge = transactions.length;

          return (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id)}
              className={`px-5 py-3 font-semibold text-xs sm:text-sm whitespace-nowrap border-b-2 transition-all flex items-center gap-2 cursor-pointer ${
                activeTab === tab.id
                  ? 'border-[#5b3ee4] text-[#5b3ee4] dark:text-purple-400'
                  : 'border-transparent text-gray-500 hover:text-gray-900 dark:hover:text-white'
              }`}
            >
              <span>{tab.label}</span>
              {countBadge !== null && (
                <span className={`text-[10px] px-2 py-0.5 rounded-full font-bold ${
                  activeTab === tab.id
                    ? 'bg-[#5b3ee4]/10 text-[#5b3ee4] dark:bg-purple-900/50 dark:text-purple-300'
                    : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400'
                }`}>
                  {countBadge}
                </span>
              )}
            </button>
          );
        })}
      </div>

      {/* ── TAB 1: TRANSACTIONS TABLE & SEARCH ─────────────────────────────── */}
      {activeTab === 'transactions' && (
        <div className="space-y-4">
          {/* Filter Bar */}
          <form onSubmit={handleFilterSubmit} className="bg-white dark:bg-gray-900 p-4 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm flex flex-col md:flex-row gap-3 items-center justify-between">
            <div className="relative w-full md:w-80">
              <Search className="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
              <input
                type="text"
                placeholder="Search reference, username, email..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="w-full pl-9 pr-4 py-2 rounded-xl text-xs bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 outline-none focus:ring-2 focus:ring-[#5b3ee4]/20"
              />
            </div>

            <div className="flex flex-wrap items-center gap-2 w-full md:w-auto">
              {/* Status Filter */}
              <select
                value={filterStatus}
                onChange={(e) => setFilterStatus(e.target.value)}
                className="px-3 py-2 rounded-xl text-xs bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 outline-none cursor-pointer"
              >
                <option value="all">All Statuses</option>
                <option value="approved">Approved</option>
                <option value="pending">Pending</option>
                <option value="failed">Failed</option>
                <option value="rejected">Rejected</option>
              </select>

              {/* Gateway Filter */}
              <select
                value={filterGateway}
                onChange={(e) => setFilterGateway(e.target.value)}
                className="px-3 py-2 rounded-xl text-xs bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 outline-none cursor-pointer"
              >
                <option value="all">All Gateways</option>
                <option value="squad">Squad Online</option>
                <option value="manual">Manual Transfer</option>
                <option value="espees">Espees</option>
              </select>

              <button
                type="submit"
                className="px-4 py-2 rounded-xl bg-[#5b3ee4] hover:bg-[#4f2bee] text-white text-xs font-semibold cursor-pointer transition-colors shadow-sm"
              >
                Filter
              </button>
            </div>
          </form>

          {/* Table */}
          <div className="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-xs text-left">
                <thead className="bg-gray-50/70 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-800">
                  <tr>
                    <th className="p-3.5 font-bold uppercase tracking-wider text-gray-500">Reference</th>
                    <th className="p-3.5 font-bold uppercase tracking-wider text-gray-500">Customer</th>
                    <th className="p-3.5 font-bold uppercase tracking-wider text-gray-500">Plan / Type</th>
                    <th className="p-3.5 font-bold uppercase tracking-wider text-gray-500">Amount</th>
                    <th className="p-3.5 font-bold uppercase tracking-wider text-gray-500">Gateway</th>
                    <th className="p-3.5 font-bold uppercase tracking-wider text-gray-500">Status</th>
                    <th className="p-3.5 font-bold uppercase tracking-wider text-gray-500">Date</th>
                    <th className="p-3.5 font-bold uppercase tracking-wider text-gray-500 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                  {transactions.length === 0 ? (
                    <tr>
                      <td colSpan="8" className="p-12 text-center text-gray-400">
                        <CreditCard className="w-8 h-8 mx-auto mb-2 opacity-30" />
                        No transactions found matching your criteria.
                      </td>
                    </tr>
                  ) : (
                    transactions.map((t) => {
                      const isApproved = t.status === 'approved';
                      const isPending = t.status === 'pending';
                      const isFailed = t.status === 'failed' || t.status === 'rejected';

                      return (
                        <tr key={t.id} className="hover:bg-gray-50/60 dark:hover:bg-gray-800/30 transition-colors">
                          <td className="p-3.5 font-mono text-gray-900 dark:text-white font-medium">
                            {t.transaction_ref || `TXN-#${t.id}`}
                          </td>
                          <td className="p-3.5">
                            <div className="font-semibold text-gray-900 dark:text-white">{t.user_username || t.full_name}</div>
                            <div className="text-[10px] text-gray-400">{t.user_email}</div>
                          </td>
                          <td className="p-3.5 font-medium capitalize text-gray-700 dark:text-gray-300">
                            {t.subscription_type}
                          </td>
                          <td className="p-3.5 font-bold text-gray-900 dark:text-white">
                            {t.currency === 'NGN' ? '₦' : ''}{Number(t.amount).toLocaleString()}
                          </td>
                          <td className="p-3.5">
                            <span className="inline-flex items-center gap-1.5 font-medium capitalize">
                              <span className={`w-2 h-2 rounded-full ${t.gateway === 'squad' ? 'bg-blue-500' : 'bg-amber-500'}`} />
                              {t.gateway} {t.channel ? `(${t.channel})` : ''}
                            </span>
                          </td>
                          <td className="p-3.5">
                            <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${
                              isApproved
                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                : isPending
                                ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'
                                : 'bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-300'
                            }`}>
                              {t.status}
                            </span>
                          </td>
                          <td className="p-3.5 text-gray-500 whitespace-nowrap">
                            {new Date(t.created_at).toLocaleDateString()}
                          </td>
                          <td className="p-3.5 text-right">
                            <button
                              onClick={() => handleInspectTransaction(t)}
                              className="px-2.5 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 font-semibold cursor-pointer inline-flex items-center gap-1"
                            >
                              <Eye className="w-3 h-3" /> Inspect
                            </button>
                          </td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* ── TAB 2: PENDING APPROVALS (MANUAL PROOFS) ───────────────────────── */}
      {activeTab === 'pending' && (
        <div className="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-xs text-left">
              <thead className="bg-gray-50/70 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-800">
                <tr>
                  <th className="p-3.5 font-bold uppercase tracking-wider text-gray-500">User</th>
                  <th className="p-3.5 font-bold uppercase tracking-wider text-gray-500">Plan</th>
                  <th className="p-3.5 font-bold uppercase tracking-wider text-gray-500">Amount</th>
                  <th className="p-3.5 font-bold uppercase tracking-wider text-gray-500">Submitted Date</th>
                  <th className="p-3.5 font-bold uppercase tracking-wider text-gray-500">Proof Receipt</th>
                  <th className="p-3.5 font-bold uppercase tracking-wider text-gray-500 text-right">Review Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                {subData.pending.length === 0 ? (
                  <tr>
                    <td colSpan="6" className="p-12 text-center text-gray-400">
                      <CheckCircle className="w-8 h-8 mx-auto mb-2 text-emerald-500 opacity-60" />
                      All manual requests have been reviewed. No pending approvals.
                    </td>
                  </tr>
                ) : (
                  subData.pending.map((t) => (
                    <tr key={t.id} className="hover:bg-gray-50/60 dark:hover:bg-gray-800/30 transition-colors">
                      <td className="p-3.5">
                        <div className="font-semibold text-gray-900 dark:text-white">{t.user_username}</div>
                        <div className="text-[10px] text-gray-400">{t.user_email}</div>
                      </td>
                      <td className="p-3.5 font-medium capitalize text-gray-700 dark:text-gray-300">
                        {t.subscription_type}
                      </td>
                      <td className="p-3.5 font-bold text-gray-900 dark:text-white">
                        ₦{Number(t.amount).toLocaleString()}
                      </td>
                      <td className="p-3.5 text-gray-500 whitespace-nowrap">
                        {new Date(t.created_at).toLocaleDateString()}
                      </td>
                      <td className="p-3.5">
                        {t.proof_of_transaction ? (
                          <a
                            href={`/${t.proof_of_transaction}`}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-1 text-blue-600 dark:text-blue-400 hover:underline font-semibold"
                          >
                            View Receipt <ExternalLink className="w-3 h-3" />
                          </a>
                        ) : (
                          '—'
                        )}
                      </td>
                      <td className="p-3.5 text-right">
                        <div className="inline-flex items-center gap-2">
                          <button
                            onClick={() => handleApprove(t.id)}
                            disabled={!!actionLoading}
                            className="inline-flex items-center gap-1 bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg font-semibold cursor-pointer disabled:opacity-50"
                          >
                            {actionLoading === t.id + '_approve' ? (
                              <Loader2 className="w-3 h-3 animate-spin" />
                            ) : (
                              <CheckCircle className="w-3 h-3" />
                            )}
                            Approve
                          </button>
                          <button
                            onClick={() => handleReject(t.id)}
                            disabled={!!actionLoading}
                            className="inline-flex items-center gap-1 bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg font-semibold cursor-pointer disabled:opacity-50"
                          >
                            {actionLoading === t.id + '_reject' ? (
                              <Loader2 className="w-3 h-3 animate-spin" />
                            ) : (
                              <XCircle className="w-3 h-3" />
                            )}
                            Reject
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* ── TAB 3: ACTIVE SUBSCRIBERS ──────────────────────────────────────── */}
      {activeTab === 'active' && (
        <div className="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-xs text-left">
              <thead className="bg-gray-50/70 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-800">
                <tr>
                  <th className="p-3.5 font-bold uppercase tracking-wider text-gray-500">Member</th>
                  <th className="p-3.5 font-bold uppercase tracking-wider text-gray-500">Active Plan</th>
                  <th className="p-3.5 font-bold uppercase tracking-wider text-gray-500">Started</th>
                  <th className="p-3.5 font-bold uppercase tracking-wider text-gray-500">Valid Until</th>
                  <th className="p-3.5 font-bold uppercase tracking-wider text-gray-500 text-right">Admin Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                {subData.active.length === 0 ? (
                  <tr>
                    <td colSpan="5" className="p-12 text-center text-gray-400">
                      No active subscribers currently found.
                    </td>
                  </tr>
                ) : (
                  subData.active.map((s) => (
                    <tr key={s.id} className="hover:bg-gray-50/60 dark:hover:bg-gray-800/30 transition-colors">
                      <td className="p-3.5">
                        <div className="font-semibold text-gray-900 dark:text-white">{s.user_username}</div>
                        <div className="text-[10px] text-gray-400">{s.user_email}</div>
                      </td>
                      <td className="p-3.5 font-medium capitalize text-gray-700 dark:text-gray-300">
                        {s.plan_code || s.subscription_type}
                      </td>
                      <td className="p-3.5 text-gray-500 whitespace-nowrap">
                        {new Date(s.start_date).toLocaleDateString()}
                      </td>
                      <td className="p-3.5 whitespace-nowrap">
                        <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                          {new Date(s.end_date).toLocaleDateString()}
                        </span>
                      </td>
                      <td className="p-3.5 text-right">
                        <button
                          onClick={() => {
                            setCancelModalSub(s);
                            setCancelReason('');
                          }}
                          className="px-2.5 py-1 rounded-lg bg-red-50 hover:bg-red-100 dark:bg-red-950/40 dark:hover:bg-red-900/60 text-red-600 dark:text-red-300 font-semibold cursor-pointer inline-flex items-center gap-1"
                        >
                          <Ban className="w-3 h-3" /> Cancel
                        </button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* ── TAB 4: SUBSCRIPTION PLANS MANAGEMENT ───────────────────────────── */}
      {activeTab === 'plans' && (
        <div className="space-y-4">
          <div className="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
            <div className="p-5 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
              <div>
                <h3 className="font-serif text-lg font-bold">Authoritative Plans Registry</h3>
                <p className="text-xs text-gray-500">
                  These plans are enforced dynamically across the application and during Squad checkout.
                </p>
              </div>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full text-xs text-left">
                <thead className="bg-gray-50/70 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-800">
                  <tr>
                    <th className="p-3.5 font-bold uppercase tracking-wider text-gray-500">Plan Code</th>
                    <th className="p-3.5 font-bold uppercase tracking-wider text-gray-500">Plan Name</th>
                    <th className="p-3.5 font-bold uppercase tracking-wider text-gray-500">Category</th>
                    <th className="p-3.5 font-bold uppercase tracking-wider text-gray-500">Price (NGN)</th>
                    <th className="p-3.5 font-bold uppercase tracking-wider text-gray-500">Interval</th>
                    <th className="p-3.5 font-bold uppercase tracking-wider text-gray-500">Badge</th>
                    <th className="p-3.5 font-bold uppercase tracking-wider text-gray-500">Status</th>
                    <th className="p-3.5 font-bold uppercase tracking-wider text-gray-500 text-right">Visibility</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                  {plans.map((p) => (
                    <tr key={p.id} className="hover:bg-gray-50/60 dark:hover:bg-gray-800/30 transition-colors">
                      <td className="p-3.5 font-mono font-medium text-gray-900 dark:text-white">{p.plan_code}</td>
                      <td className="p-3.5 font-semibold text-gray-900 dark:text-white">{p.name}</td>
                      <td className="p-3.5 capitalize text-gray-600 dark:text-gray-400">{p.category}</td>
                      <td className="p-3.5 font-bold text-gray-900 dark:text-white">₦{Number(p.price).toLocaleString()}</td>
                      <td className="p-3.5 capitalize text-gray-600 dark:text-gray-400">{p.interval_unit}</td>
                      <td className="p-3.5 text-gray-500">{p.badge_text || '—'}</td>
                      <td className="p-3.5">
                        <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                          p.is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
                        }`}>
                          {p.is_active ? 'Active' : 'Hidden'}
                        </span>
                      </td>
                      <td className="p-3.5 text-right">
                        <button
                          onClick={() => handleTogglePlan(p.id)}
                          className="px-3 py-1 rounded-lg text-xs font-semibold bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 cursor-pointer"
                        >
                          {p.is_active ? 'Hide' : 'Activate'}
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* ── TAB 5: SQUAD GATEWAY STATUS & SECURITY ─────────────────────────── */}
      {activeTab === 'gateway' && gatewayConfig && (
        <div className="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6 sm:p-8 space-y-6 max-w-3xl">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center">
              <ShieldCheck className="w-6 h-6" />
            </div>
            <div>
              <h3 className="font-serif text-xl font-bold text-gray-950 dark:text-white">Squad Gateway Integration</h3>
              <p className="text-xs text-gray-500">Secure server-side payment processing status.</p>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-gray-100 dark:border-gray-800 text-xs">
            <div className="p-4 rounded-xl bg-gray-50 dark:bg-gray-800/40 border border-gray-100 dark:border-gray-800">
              <span className="text-gray-400 block mb-1">Provider</span>
              <strong className="text-sm font-semibold text-gray-900 dark:text-white">{gatewayConfig.gateway}</strong>
            </div>

            <div className="p-4 rounded-xl bg-gray-50 dark:bg-gray-800/40 border border-gray-100 dark:border-gray-800">
              <span className="text-gray-400 block mb-1">Environment</span>
              <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-bold uppercase ${
                gatewayConfig.is_sandbox ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
              }`}>
                {gatewayConfig.environment}
              </span>
            </div>

            <div className="p-4 rounded-xl bg-gray-50 dark:bg-gray-800/40 border border-gray-100 dark:border-gray-800">
              <span className="text-gray-400 block mb-1">Public Key</span>
              <span className="font-mono text-xs">{gatewayConfig.public_key_masked}</span>
            </div>

            <div className="p-4 rounded-xl bg-gray-50 dark:bg-gray-800/40 border border-gray-100 dark:border-gray-800">
              <span className="text-gray-400 block mb-1">Secret Key Status</span>
              <span className="text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
                <Check className="w-3.5 h-3.5" /> Secured on server (.env)
              </span>
            </div>

            <div className="p-4 rounded-xl bg-gray-50 dark:bg-gray-800/40 border border-gray-100 dark:border-gray-800 sm:col-span-2">
              <span className="text-gray-400 block mb-1">Webhook Endpoint URL</span>
              <code className="text-xs font-mono text-[#5b3ee4] dark:text-purple-400 break-all">
                https://yourdomain.com/api/subscriptions/squad/webhook
              </code>
              <p className="text-[11px] text-gray-400 mt-1">
                Whitelist Squad outgoing IP address: <span className="font-mono font-bold text-gray-700 dark:text-gray-300">{gatewayConfig.ip_whitelist}</span>
              </p>
            </div>
          </div>
        </div>
      )}

      {/* ── TRANSACTION INSPECT MODAL & AUDIT TRAIL ────────────────────────── */}
      {selectedTxn && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
          <div className="bg-white dark:bg-gray-900 rounded-3xl max-w-2xl w-full shadow-2xl border border-gray-100 dark:border-gray-800 overflow-hidden my-8 animate-in zoom-in-95 duration-200">
            <div className="p-6 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
              <div>
                <span className="text-[10px] font-bold uppercase tracking-wider text-gray-400">Transaction Details</span>
                <h3 className="font-serif text-xl font-bold font-mono text-gray-950 dark:text-white">
                  {selectedTxn.transaction_ref || `TXN-#${selectedTxn.id}`}
                </h3>
              </div>
              <button
                onClick={() => setSelectedTxn(null)}
                className="w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-500 cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="p-6 space-y-6">
              {/* Meta Grid */}
              <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                <div className="p-3 bg-gray-50 dark:bg-gray-800/40 rounded-xl">
                  <span className="text-gray-400 block">Customer</span>
                  <strong className="text-gray-900 dark:text-white">{selectedTxn.user_username || selectedTxn.full_name}</strong>
                </div>
                <div className="p-3 bg-gray-50 dark:bg-gray-800/40 rounded-xl">
                  <span className="text-gray-400 block">Amount</span>
                  <strong className="text-gray-900 dark:text-white">₦{Number(selectedTxn.amount).toLocaleString()}</strong>
                </div>
                <div className="p-3 bg-gray-50 dark:bg-gray-800/40 rounded-xl">
                  <span className="text-gray-400 block">Status</span>
                  <strong className="capitalize text-gray-900 dark:text-white">{selectedTxn.status}</strong>
                </div>
                <div className="p-3 bg-gray-50 dark:bg-gray-800/40 rounded-xl">
                  <span className="text-gray-400 block">Gateway</span>
                  <strong className="capitalize text-gray-900 dark:text-white">{selectedTxn.gateway}</strong>
                </div>
                <div className="p-3 bg-gray-50 dark:bg-gray-800/40 rounded-xl">
                  <span className="text-gray-400 block">Plan Code</span>
                  <strong className="text-gray-900 dark:text-white">{selectedTxn.subscription_type}</strong>
                </div>
                <div className="p-3 bg-gray-50 dark:bg-gray-800/40 rounded-xl">
                  <span className="text-gray-400 block">Created At</span>
                  <span className="text-gray-600 dark:text-gray-300">{new Date(selectedTxn.created_at).toLocaleString()}</span>
                </div>
              </div>

              {/* Audit Logs Trail */}
              <div>
                <h4 className="font-bold text-xs uppercase tracking-wider text-gray-500 mb-3 flex items-center gap-1.5">
                  <Sliders className="w-3.5 h-3.5" /> Immutable Audit Trail
                </h4>
                {loadingAudit ? (
                  <div className="p-6 text-center text-gray-400 text-xs">
                    <Loader2 className="w-4 h-4 animate-spin mx-auto mb-1" /> Loading trail...
                  </div>
                ) : auditLogs.length === 0 ? (
                  <div className="p-4 bg-gray-50 dark:bg-gray-800/20 rounded-xl text-xs text-gray-400 text-center">
                    No individual audit events recorded for this legacy transaction.
                  </div>
                ) : (
                  <div className="space-y-2">
                    {auditLogs.map((log) => (
                      <div key={log.id} className="p-3 rounded-xl bg-gray-50 dark:bg-gray-800/40 border border-gray-100 dark:border-gray-800 flex items-start justify-between text-xs">
                        <div>
                          <div className="flex items-center gap-2">
                            <span className="font-bold text-[#5b3ee4] dark:text-purple-400 font-mono">{log.action}</span>
                            <span className="text-[10px] text-gray-400">• Actor: {log.actor_type}</span>
                          </div>
                          {log.reason && <p className="text-gray-600 dark:text-gray-300 mt-0.5">{log.reason}</p>}
                        </div>
                        <span className="text-[10px] text-gray-400 whitespace-nowrap">
                          {new Date(log.created_at).toLocaleTimeString()}
                        </span>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ── CANCELLATION MODAL ────────────────────────────────────────────── */}
      {cancelModalSub && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white dark:bg-gray-900 rounded-3xl max-w-md w-full p-6 shadow-2xl border border-gray-100 dark:border-gray-800">
            <h3 className="font-serif text-xl font-bold mb-2">Cancel User Subscription</h3>
            <p className="text-xs text-gray-500 mb-4">
              Are you sure you want to cancel the subscription for <strong>{cancelModalSub.user_username}</strong>? This action will be recorded in the audit trail.
            </p>
            <form onSubmit={handleCancelSubscriptionSubmit} className="space-y-4">
              <div>
                <label className="block text-[11px] font-bold text-gray-500 uppercase mb-1">
                  Mandatory Cancellation Reason
                </label>
                <textarea
                  required
                  rows="3"
                  placeholder="e.g. User requested refund, fraudulent activity, administrative correction..."
                  value={cancelReason}
                  onChange={(e) => setCancelReason(e.target.value)}
                  className="w-full p-3 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs outline-none"
                />
              </div>
              <div className="flex justify-end gap-2">
                <button
                  type="button"
                  onClick={() => setCancelModalSub(null)}
                  className="px-4 py-2 rounded-xl text-xs font-semibold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={actionLoading}
                  className="px-4 py-2 rounded-xl text-xs font-semibold bg-red-600 hover:bg-red-700 text-white cursor-pointer disabled:opacity-50"
                >
                  Confirm Cancellation
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

    </div>
  );
}
