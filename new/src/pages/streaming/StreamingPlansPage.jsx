import { useEffect, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { 
  Loader2, Radio, Check, ChevronRight, Upload, CreditCard, 
  Lock, ShieldCheck, Zap, Sparkles, AlertCircle, CheckCircle2, 
  RefreshCw, ArrowRight, Tv, Copy
} from 'lucide-react';
import { request, subscriptionsApi } from '../../services/api';
import { useAuth } from '@/context/AuthContext';

const DEFAULT_STREAMING_PLANS = [
  {
    id: 1,
    plan_code: 'studio_basic',
    name: 'Basic Streamer',
    description: 'Perfect for small meetings, home fellowships, and personal devotional broadcasts.',
    price: '15000.00',
    billing_period: 'monthly',
    max_duration_hours: 4,
    max_viewers: 100,
    resolution: '720p'
  },
  {
    id: 2,
    plan_code: 'studio_pro',
    name: 'Pro Broadcaster',
    description: 'Professional high-capacity streaming suite with ultra HD 1080p resolution and priority encoding for ministries.',
    price: '45000.00',
    billing_period: 'monthly',
    max_duration_hours: 12,
    max_viewers: 1000,
    resolution: '1080p'
  }
];

export default function StreamingPlansPage() {
  const { user, refreshUser } = useAuth();
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();

  const [plans, setPlans] = useState(DEFAULT_STREAMING_PLANS);
  const [loading, setLoading] = useState(false);
  const [step, setStep] = useState(() => {
    const qStep = searchParams.get('step');
    return qStep ? parseInt(qStep, 10) : 1;
  });
  const [selectedPlan, setSelectedPlan] = useState(() => {
    const planId = searchParams.get('plan_id');
    if (planId) {
      return DEFAULT_STREAMING_PLANS.find(p => String(p.id) === String(planId)) || DEFAULT_STREAMING_PLANS[0];
    }
    return searchParams.get('step') === '2' ? DEFAULT_STREAMING_PLANS[0] : null;
  });
  const [currentStatus, setCurrentStatus] = useState(null);
  const [showPlansOverride, setShowPlansOverride] = useState(false);

  // Payment states
  const [paymentMethod, setPaymentMethod] = useState('squad'); // 'squad' | 'bank_transfer'
  const [submittingSquad, setSubmittingSquad] = useState(false);
  const [submittingManual, setSubmittingManual] = useState(false);
  const [proofFile, setProofFile] = useState(null);

  // Verification states for Squad return redirect
  const [verifyingPayment, setVerifyingPayment] = useState(false);
  const [verificationResult, setVerificationResult] = useState(null);
  const [verificationError, setVerificationError] = useState('');
  const [copiedKey, setCopiedKey] = useState(null);

  // 1. Initial data fetch
  useEffect(() => {
    async function fetchData() {
      try {
        const plansRes = await request('/streaming/plans');
        if (plansRes?.success && Array.isArray(plansRes.data) && plansRes.data.length > 0) {
          setPlans(plansRes.data);
          
          if (user) {
            const statusRes = await request('/streaming/status').catch(() => null);
            if (statusRes?.success && statusRes.data?.has_active_plan) {
              setCurrentStatus(statusRes.data);
            }
            
            const pendingPlanId = sessionStorage.getItem('pendingStudioPlanId');
            if (pendingPlanId) {
              const plan = plansRes.data.find(p => String(p.id) === String(pendingPlanId)) ||
                           DEFAULT_STREAMING_PLANS.find(p => String(p.id) === String(pendingPlanId));
              if (plan) {
                setSelectedPlan(plan);
                setStep(2);
              }
              sessionStorage.removeItem('pendingStudioPlanId');
            }
          }
        } else {
          // If offline or DB unseeded, fallback to DEFAULT_STREAMING_PLANS
          setPlans(DEFAULT_STREAMING_PLANS);
        }
      } catch (e) {
        console.error('Failed to fetch streaming data', e);
        setPlans(DEFAULT_STREAMING_PLANS);
      } finally {
        setLoading(false);
      }
    }
    fetchData();
  }, [user]);

  // 2. Handle Squad Return Callback (?squad_ref=vpr_sq_... or ?reference=... or ?transaction_ref=...)
  useEffect(() => {
    const squadRef = searchParams.get('squad_ref') || searchParams.get('reference') || searchParams.get('transaction_ref');
    if (!squadRef) return;

    let isMounted = true;
    async function verify() {
      setVerifyingPayment(true);
      setVerificationError('');
      try {
        const res = await subscriptionsApi.verifySquadPayment(squadRef);
        if (res?.success && isMounted) {
          setVerificationResult(res);
          setStep(4);
          await refreshUser?.();
          const statusRes = await request('/streaming/status').catch(() => null);
          if (statusRes?.success && statusRes.data?.has_active_plan) {
            setCurrentStatus(statusRes.data);
          }
        } else if (isMounted) {
          setVerificationError(res?.message || 'Payment verification could not be confirmed.');
        }
      } catch (err) {
        if (isMounted) {
          console.error('Squad verification error:', err);
          setVerificationError(err?.message || 'Unable to confirm payment with gateway. If you were debited, your studio channel will activate automatically.');
        }
      } finally {
        if (isMounted) {
          setVerifyingPayment(false);
          // Remove query params from address bar without reloading
          const newParams = new URLSearchParams(searchParams);
          newParams.delete('squad_ref');
          newParams.delete('reference');
          newParams.delete('transaction_ref');
          newParams.delete('squad_simulated');
          setSearchParams(newParams, { replace: true });
        }
      }
    }
    verify();
    return () => { isMounted = false; };
  }, [searchParams]);

  // Helper to format authoritative pricing
  const getPlanPricing = (plan) => {
    if (!plan) return { primary: '₦0', secondary: '$0', rawNgn: 0, isFree: true };
    const num = parseFloat(plan.price) || 0;
    if (num === 0) return { primary: 'Free', secondary: '', rawNgn: 0, isFree: true };
    if (num <= 100) {
      // Legacy USD pricing in DB (10.00 = Basic = ₦15,000, 30.00 = Pro = ₦45,000)
      const ngn = num <= 15 ? 15000 : 45000;
      return {
        primary: `₦${ngn.toLocaleString()}`,
        secondary: `$${num.toFixed(0)}`,
        rawNgn: ngn,
        isFree: false
      };
    }
    return {
      primary: `₦${num.toLocaleString()}`,
      secondary: `$${Math.round(num / 1500)}`,
      rawNgn: num,
      isFree: false
    };
  };

  const handleSelectPlan = (plan) => {
    if (!user) {
      sessionStorage.setItem('pendingStudioPlanId', plan.id);
      navigate('/login?redirect=/dashboard/streaming/plans');
      return;
    }
    setSelectedPlan(plan);
    setPaymentMethod('squad');
    setStep(2);
  };

  // Instant Squad Checkout Initiation
  const handleInitiateSquad = async () => {
    if (!selectedPlan) return;
    setSubmittingSquad(true);
    try {
      const planCode = selectedPlan.plan_code || `streaming_plan_${selectedPlan.id}`;
      const res = await subscriptionsApi.initiateSquadPayment({
        plan_code: planCode,
        redirect_path: '/dashboard/streaming/plans'
      });

      if (res?.success && res.checkout_url) {
        window.location.href = res.checkout_url;
      } else {
        alert(res?.message || 'Failed to initiate payment with Squad gateway. Please try again.');
      }
    } catch (err) {
      console.error('Squad initiation error:', err);
      alert(err?.message || 'Unable to connect to Squad payment gateway.');
    } finally {
      setSubmittingSquad(false);
    }
  };

  // Manual Bank Transfer Receipt Upload
  const handleManualUpload = async (e) => {
    e.preventDefault();
    if (!proofFile) {
      alert('Please select a payment receipt (Image or PDF)');
      return;
    }
    setSubmittingManual(true);
    const formData = new FormData();
    formData.append('proof', proofFile);
    formData.append('plan_id', selectedPlan.id);

    try {
      const json = await request('/streaming/payment/upload', { 
        method: 'POST', 
        body: formData 
      });
      if (json.success) {
        setStep(3);
      } else {
        alert(json.message || 'Upload failed. Please check the file and try again.');
      }
    } catch (err) {
      console.error(err);
      alert('Error uploading receipt file.');
    } finally {
      setSubmittingManual(false);
    }
  };

  const copyToClipboard = (text, key) => {
    navigator.clipboard.writeText(text);
    setCopiedKey(key);
    setTimeout(() => setCopiedKey(null), 2000);
  };

  // Loading state (only show full blocker if no default plans are available)
  if (loading && (!plans || plans.length === 0)) {
    return (
      <div className="min-h-[70vh] flex flex-col items-center justify-center p-12">
        <div className="w-12 h-12 border-4 border-gray-200 dark:border-gray-700 border-t-[#5b3ee4] rounded-full animate-spin mb-4"></div>
        <p className="text-gray-500 dark:text-gray-400 font-medium text-sm">Loading studio plans & channel info...</p>
      </div>
    );
  }

  // Verifying Squad payment screen
  if (verifyingPayment) {
    return (
      <div className="min-h-[70vh] flex flex-col items-center justify-center p-8 text-center max-w-md mx-auto">
        <div className="w-16 h-16 rounded-full bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center mb-6 text-[#5b3ee4] dark:text-[#a5b4fc] animate-pulse">
          <RefreshCw className="w-8 h-8 animate-spin" />
        </div>
        <h2 className="font-serif text-2xl font-bold text-gray-950 dark:text-white mb-2">Verifying Broadcaster Payment</h2>
        <p className="text-gray-600 dark:text-gray-300 text-sm leading-relaxed mb-6">
          Communicating securely with Squad and preparing your AWS IVS streaming channel. This usually takes just a few seconds...
        </p>
        <div className="w-full bg-gray-100 dark:bg-gray-800 h-2 rounded-full overflow-hidden">
          <div className="bg-[#5b3ee4] h-full w-2/3 animate-pulse rounded-full"></div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-[80vh] flex flex-col items-center justify-center p-4 py-8 md:py-16 animate-in fade-in duration-300 text-gray-950 dark:text-white pb-28 lg:pb-16 max-w-6xl mx-auto">
      
      {/* Verification Error Notice */}
      {verificationError && (
        <div className="w-full max-w-2xl mb-8 p-4 bg-amber-50 dark:bg-amber-950/50 border border-amber-200 dark:border-amber-800 rounded-2xl flex items-start gap-3 text-amber-900 dark:text-amber-200 text-sm shadow-sm">
          <AlertCircle className="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
          <div className="flex-1">
            <h4 className="font-bold">Payment Verification Pending</h4>
            <p className="text-xs sm:text-sm mt-0.5 text-amber-800 dark:text-amber-300">{verificationError}</p>
          </div>
          <button 
            onClick={() => setVerificationError('')} 
            className="text-amber-600 dark:text-amber-400 hover:text-amber-800 text-xs font-semibold"
          >
            Dismiss
          </button>
        </div>
      )}

      {/* Step 4: Celebratory Squad Payment Confirmed */}
      {step === 4 && (
        <div className="w-full max-w-2xl bg-white dark:bg-gray-900 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden p-8 sm:p-12 text-center mx-auto animate-in zoom-in-95 duration-300">
          <div className="w-20 h-20 bg-green-50 dark:bg-green-950/60 rounded-full flex items-center justify-center mx-auto mb-6">
            <CheckCircle2 className="w-10 h-10 text-green-600 dark:text-green-400" />
          </div>
          <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300 text-xs font-semibold mb-3 border border-emerald-200 dark:border-emerald-800">
            <Sparkles className="w-3.5 h-3.5" /> Instant Squad Activation
          </div>
          <h2 className="font-serif text-2xl sm:text-4xl font-bold text-gray-950 dark:text-white mb-3 tracking-tight">
            Studio Broadcaster Activated!
          </h2>
          <p className="text-gray-600 dark:text-gray-300 mx-auto mb-8 text-sm sm:text-base max-w-lg leading-relaxed">
            Your payment was confirmed. Your dedicated AWS IVS channel has been provisioned and is ready for live broadcasting.
          </p>

          <div className="bg-gray-50 dark:bg-gray-800/70 rounded-2xl p-6 mb-8 text-left max-w-md mx-auto border border-gray-200/80 dark:border-gray-700 space-y-3">
            <div className="flex justify-between items-center text-sm">
              <span className="text-gray-500 dark:text-gray-400 font-medium">Plan</span>
              <span className="font-bold text-gray-950 dark:text-white">
                {verificationResult?.data?.plan_code || selectedPlan?.name || 'Studio Broadcaster'}
              </span>
            </div>
            <div className="flex justify-between items-center text-sm">
              <span className="text-gray-500 dark:text-gray-400 font-medium">Gateway</span>
              <span className="font-bold text-[#5b3ee4] dark:text-[#a5b4fc] flex items-center gap-1">
                <ShieldCheck className="w-4 h-4" /> Squad Verified
              </span>
            </div>
            <div className="flex justify-between items-center text-sm">
              <span className="text-gray-500 dark:text-gray-400 font-medium">Reference</span>
              <span className="font-mono text-xs font-semibold text-gray-700 dark:text-gray-300 truncate max-w-[180px]">
                {verificationResult?.reference || 'Confirmed'}
              </span>
            </div>
            {verificationResult?.data?.end_date && (
              <div className="flex justify-between items-center text-sm pt-2 border-t border-gray-200 dark:border-gray-700">
                <span className="text-gray-500 dark:text-gray-400 font-medium">Access Valid Until</span>
                <span className="font-bold text-emerald-700 dark:text-emerald-400">
                  {new Date(verificationResult.data.end_date).toLocaleDateString(undefined, { dateStyle: 'medium' })}
                </span>
              </div>
            )}
          </div>

          <div className="flex flex-col sm:flex-row items-center justify-center gap-3">
            <button 
              onClick={() => navigate('/dashboard/streaming')}
              className="bg-[#5b3ee4] hover:bg-[#4f2bee] text-white px-8 py-3.5 rounded-full font-bold text-sm transition-all shadow-md active:scale-95 w-full sm:w-auto flex items-center justify-center gap-2 cursor-pointer"
            >
              <Tv className="w-4 h-4" /> Launch Broadcast Studio
            </button>
            <button 
              onClick={() => { setStep(1); setShowPlansOverride(true); }}
              className="text-gray-600 dark:text-gray-300 hover:text-gray-950 dark:hover:text-white px-6 py-3.5 rounded-full font-medium text-sm transition-colors w-full sm:w-auto cursor-pointer"
            >
              View All Plans
            </button>
          </div>
        </div>
      )}

      {/* Existing Active Plan View */}
      {step === 1 && currentStatus?.has_active_plan && !showPlansOverride && (
        <div className="w-full max-w-2xl bg-white dark:bg-gray-900 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden p-8 sm:p-12 text-center mx-auto animate-in fade-in duration-300">
          <div className="w-20 h-20 bg-green-50 dark:bg-green-950/60 rounded-full flex items-center justify-center mx-auto mb-6">
            <Check className="w-10 h-10 text-green-600 dark:text-green-400" />
          </div>
          <h3 className="font-serif text-2xl sm:text-3xl font-bold text-gray-950 dark:text-white mb-2 tracking-tight">Active Studio Subscription</h3>
          <p className="text-gray-500 dark:text-gray-400 mx-auto mb-6 text-sm sm:text-base">
            Your studio broadcaster channel is provisioned and ready.
          </p>
          
          <div className="bg-gray-50 dark:bg-gray-800/80 rounded-2xl p-6 mb-8 text-left max-w-md mx-auto border border-gray-200 dark:border-gray-700 space-y-3">
             <div className="flex justify-between items-center">
                <span className="text-gray-500 dark:text-gray-400 font-medium text-sm">Active Plan</span>
                <span className="font-bold text-gray-900 dark:text-white text-sm">{currentStatus.plan?.name}</span>
             </div>
             <div className="flex justify-between items-center">
                <span className="text-gray-500 dark:text-gray-400 font-medium text-sm">Resolution</span>
                <span className="font-bold text-gray-900 dark:text-white text-sm uppercase">{currentStatus.plan?.resolution || '1080p'}</span>
             </div>
             <div className="flex justify-between items-center">
                <span className="text-gray-500 dark:text-gray-400 font-medium text-sm">Status</span>
                <span className="font-bold text-green-700 dark:text-green-400 bg-green-100 dark:bg-green-950/80 px-2.5 py-0.5 rounded-full text-xs">Active</span>
             </div>
             <div className="flex justify-between items-center pt-2 border-t border-gray-200 dark:border-gray-700">
                <span className="text-gray-500 dark:text-gray-400 font-medium text-sm">Expires</span>
                <span className="font-bold text-gray-900 dark:text-white text-sm">
                  {currentStatus.plan?.end_date ? new Date(currentStatus.plan.end_date).toLocaleDateString(undefined, { dateStyle: 'medium' }) : 'Auto-renews'}
                </span>
             </div>
          </div>

          <div className="flex flex-col sm:flex-row items-center justify-center gap-3">
            <button 
              onClick={() => navigate('/dashboard/streaming')}
              className="bg-[#5b3ee4] hover:bg-[#4f2bee] text-white px-8 py-3.5 rounded-full font-bold text-sm transition-all shadow-md active:scale-95 w-full sm:w-auto cursor-pointer"
            >
              Open Broadcast Studio
            </button>
            <button 
              onClick={() => setShowPlansOverride(true)}
              className="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-6 py-3.5 rounded-full font-medium text-sm transition-colors w-full sm:w-auto border border-gray-200 dark:border-gray-700 cursor-pointer"
            >
              Upgrade or Change Tier
            </button>
          </div>
        </div>
      )}

      {/* Step 1: Plan Discovery & Selection */}
      {step === 1 && (!currentStatus?.has_active_plan || showPlansOverride) && (
        <div className="w-full">
          {showPlansOverride && (
            <div className="mb-6">
              <button 
                onClick={() => setShowPlansOverride(false)} 
                className="text-sm font-semibold text-[#5b3ee4] dark:text-[#a5b4fc] hover:underline inline-flex items-center gap-1 cursor-pointer"
              >
                ← Back to my active subscription
              </button>
            </div>
          )}

          <div className="text-center mb-10 sm:mb-12">
             <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-[#5b3ee4] dark:text-[#a5b4fc] mb-4">
                <Radio className="w-7 h-7" />
             </div>
             <h1 className="font-serif text-3xl sm:text-4xl lg:text-5xl font-bold text-gray-950 dark:text-white tracking-tight mb-3">
               Choose Your Studio Broadcaster Plan
             </h1>
             <p className="text-sm sm:text-base text-gray-600 dark:text-gray-300 max-w-2xl mx-auto leading-relaxed">
                Stream your praise services and ministry crusades with ultra low-latency AWS IVS infrastructure. Pay securely via Squad with instant channel activation.
             </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {plans.length === 0 && (
              <div className="col-span-full text-center py-16 bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-3xl shadow-sm">
                <h3 className="font-serif text-xl font-bold text-gray-950 dark:text-white mb-2">No Studio Plans Available</h3>
                <p className="text-gray-500 dark:text-gray-400 text-sm">There are currently no active studio tiers configured. Please contact administration.</p>
              </div>
            )}
            
            {plans.map((plan) => {
              const pricing = getPlanPricing(plan);
              const isPro = plan.name?.toLowerCase().includes('pro');

              return (
                <div 
                  key={plan.id} 
                  className={`bg-white dark:bg-gray-900 rounded-3xl p-6 sm:p-8 shadow-sm flex flex-col transition-all relative ${
                    isPro 
                      ? 'border-2 border-[#5b3ee4] dark:border-[#818cf8] shadow-indigo-100/40 dark:shadow-none' 
                      : 'border border-gray-100 dark:border-gray-800 hover:shadow-md'
                  }`}
                >
                  {isPro && (
                    <div className="absolute -top-3.5 left-1/2 -translate-x-1/2 bg-[#5b3ee4] text-white text-[11px] font-bold uppercase tracking-wider px-3.5 py-1 rounded-full shadow-sm flex items-center gap-1">
                      <Sparkles className="w-3 h-3" /> Ministry Recommended
                    </div>
                  )}

                  <h3 className="font-serif text-2xl font-bold text-gray-950 dark:text-white mb-1.5">{plan.name}</h3>
                  <p className="text-gray-500 dark:text-gray-400 text-xs sm:text-sm mb-5 min-h-[36px]">{plan.description}</p>

                  <div className="mb-6 pb-6 border-b border-gray-100 dark:border-gray-800">
                    <div className="flex items-baseline gap-2">
                      <span className="text-3xl sm:text-4xl font-bold text-[#5b3ee4] dark:text-[#818cf8] tracking-tight">
                        {pricing.primary}
                      </span>
                      <span className="text-xs sm:text-sm text-gray-500 dark:text-gray-400 font-medium">
                        / {plan.billing_period || 'month'}
                      </span>
                    </div>
                    {pricing.secondary && (
                      <span className="text-xs text-gray-400 dark:text-gray-500 font-medium mt-0.5 block">
                        Approx. {pricing.secondary} USD
                      </span>
                    )}
                  </div>
                  
                  <ul className="space-y-3 mb-8 text-sm text-gray-700 dark:text-gray-300 flex-1">
                    <li className="flex items-start">
                      <Check className="w-4 h-4 text-emerald-500 mr-2.5 mt-0.5 shrink-0" /> 
                      <span className="font-semibold text-gray-900 dark:text-white">{plan.resolution}</span>&nbsp;Ultra HD Resolution
                    </li>
                    <li className="flex items-start">
                      <Check className="w-4 h-4 text-emerald-500 mr-2.5 mt-0.5 shrink-0" /> 
                      {plan.max_viewers ? `Up to ${plan.max_viewers.toLocaleString()} concurrent viewers` : 'Unlimited concurrent viewers'}
                    </li>
                    <li className="flex items-start">
                      <Check className="w-4 h-4 text-emerald-500 mr-2.5 mt-0.5 shrink-0" /> 
                      {plan.max_duration_hours ? `Up to ${plan.max_duration_hours} hours per broadcast` : 'Unlimited broadcast duration'}
                    </li>
                    <li className="flex items-start">
                      <Check className="w-4 h-4 text-emerald-500 mr-2.5 mt-0.5 shrink-0" /> 
                      Dedicated AWS IVS Channel & Playback URL
                    </li>
                    <li className="flex items-start">
                      <Check className="w-4 h-4 text-emerald-500 mr-2.5 mt-0.5 shrink-0" /> 
                      Browser Studio & OBS Studio compatible
                    </li>
                    <li className="flex items-start">
                      <Check className="w-4 h-4 text-emerald-500 mr-2.5 mt-0.5 shrink-0" /> 
                      Real-time audience ministration & live chat
                    </li>
                  </ul>

                  <button 
                    onClick={() => handleSelectPlan(plan)}
                    className={`w-full py-3.5 rounded-full font-bold text-sm transition-all shadow-md active:scale-95 cursor-pointer flex items-center justify-center gap-2 ${
                      isPro 
                        ? 'bg-[#5b3ee4] hover:bg-[#4f2bee] text-white shadow-indigo-200 dark:shadow-none' 
                        : 'bg-gray-900 hover:bg-black dark:bg-white dark:hover:bg-gray-100 text-white dark:text-gray-900'
                    }`}
                  >
                    Select {plan.name} <ArrowRight className="w-4 h-4" />
                  </button>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Step 2: Multi-Option Checkout (Squad Online + Bank Transfer Fallback) */}
      {step === 2 && selectedPlan && (
        <div className="w-full max-w-4xl bg-white dark:bg-gray-900 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden animate-in slide-in-from-right-8 duration-300">
          <div className="p-6 sm:p-10">
            {/* Breadcrumb */}
            <div className="flex items-center gap-2 text-sm font-medium text-gray-400 mb-6 sm:mb-8">
              <button 
                onClick={() => setStep(1)} 
                className="hover:text-gray-900 dark:hover:text-white transition-colors cursor-pointer"
              >
                Plans
              </button>
              <ChevronRight className="w-4 h-4" />
              <span className="text-gray-900 dark:text-white font-semibold">Checkout</span>
            </div>

            <div className="flex flex-col lg:flex-row gap-8 sm:gap-12">
              
              {/* Payment Method Column */}
              <div className="flex-1">
                <h3 className="font-serif text-2xl font-bold text-gray-950 dark:text-white mb-2">
                  Select Payment Method
                </h3>
                <p className="text-gray-500 dark:text-gray-400 text-sm mb-6">
                  Choose how you would like to pay for your studio broadcaster license.
                </p>

                {/* Method Tabs */}
                <div className="grid grid-cols-2 gap-3 mb-6 p-1 bg-gray-100 dark:bg-gray-800 rounded-2xl">
                  <button
                    type="button"
                    onClick={() => setPaymentMethod('squad')}
                    className={`py-3 px-4 rounded-xl text-xs sm:text-sm font-bold flex items-center justify-center gap-2 transition-all cursor-pointer ${
                      paymentMethod === 'squad'
                        ? 'bg-white dark:bg-gray-700 text-[#5b3ee4] dark:text-[#a5b4fc] shadow-sm'
                        : 'text-gray-600 dark:text-gray-400 hover:text-gray-950 dark:hover:text-white'
                    }`}
                  >
                    <Zap className="w-4 h-4 text-emerald-500" />
                    <span>Squad Online (Instant)</span>
                  </button>

                  <button
                    type="button"
                    onClick={() => setPaymentMethod('bank_transfer')}
                    className={`py-3 px-4 rounded-xl text-xs sm:text-sm font-bold flex items-center justify-center gap-2 transition-all cursor-pointer ${
                      paymentMethod === 'bank_transfer'
                        ? 'bg-white dark:bg-gray-700 text-[#5b3ee4] dark:text-[#a5b4fc] shadow-sm'
                        : 'text-gray-600 dark:text-gray-400 hover:text-gray-950 dark:hover:text-white'
                    }`}
                  >
                    <CreditCard className="w-4 h-4" />
                    <span>Bank Transfer</span>
                  </button>
                </div>

                {/* Squad Online Payment Content */}
                {paymentMethod === 'squad' && (
                  <div className="space-y-4 animate-in fade-in duration-200">
                    <div className="p-5 rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900/60">
                      <div className="flex items-center justify-between mb-3">
                        <div className="flex items-center gap-2 font-bold text-gray-950 dark:text-white text-sm">
                          <ShieldCheck className="w-5 h-5 text-emerald-500" /> Instant Broadcaster Activation
                        </div>
                        <span className="text-[11px] font-bold bg-[#5b3ee4] text-white px-2 py-0.5 rounded-md">
                          Squad Gateway
                        </span>
                      </div>
                      <p className="text-xs sm:text-sm text-gray-600 dark:text-gray-300 leading-relaxed mb-4">
                        Pay securely using any Nigerian or International Debit Card, Direct Bank Transfer via virtual account, or GTBank USSD (*737#).
                      </p>
                      
                      <div className="grid grid-cols-3 gap-2 text-center text-xs font-medium text-gray-600 dark:text-gray-300">
                        <div className="p-2.5 bg-white/80 dark:bg-gray-850 rounded-xl border border-indigo-100/50 dark:border-gray-800">
                          💳 Cards (Mastercard, Visa, Verve)
                        </div>
                        <div className="p-2.5 bg-white/80 dark:bg-gray-850 rounded-xl border border-indigo-100/50 dark:border-gray-800">
                          🏦 Virtual Bank Transfer
                        </div>
                        <div className="p-2.5 bg-white/80 dark:bg-gray-850 rounded-xl border border-indigo-100/50 dark:border-gray-800">
                          📱 USSD (*737#)
                        </div>
                      </div>
                    </div>

                    <div className="p-4 bg-emerald-50/60 dark:bg-emerald-950/30 rounded-2xl border border-emerald-100 dark:border-emerald-900/40 flex items-start gap-3 text-xs text-emerald-800 dark:text-emerald-300">
                      <Sparkles className="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5" />
                      <div>
                        <strong className="font-semibold">Automatic AWS IVS Provisioning:</strong> Once Squad confirms your transaction, your streaming endpoint and keys are immediately generated with zero waiting time.
                      </div>
                    </div>
                  </div>
                )}

                {/* Bank Transfer Manual Fallback Content */}
                {paymentMethod === 'bank_transfer' && (
                  <div className="space-y-4 animate-in fade-in duration-200">
                    <div className="bg-[#f4f6fc] dark:bg-gray-850 rounded-2xl p-5 border border-indigo-100/50 dark:border-gray-800">
                      <div className="flex items-center gap-2 font-bold text-gray-950 dark:text-white mb-2 text-base">
                        <CreditCard className="w-5 h-5 text-[#5b3ee4]" /> Manual Bank Transfer Details
                      </div>
                      <div className="space-y-1.5 text-gray-700 dark:text-gray-300 text-sm">
                         <div className="flex items-center justify-between">
                            <span>Bank:</span>
                            <span className="font-bold text-gray-900 dark:text-white">Parallex Bank</span>
                         </div>
                         <div className="flex items-center justify-between">
                            <span>Account Name:</span>
                            <span className="font-medium text-gray-900 dark:text-white">LMAM - Music App</span>
                         </div>
                         <div className="flex items-center justify-between pt-1 border-t border-gray-200 dark:border-gray-700">
                            <span>Account Number:</span>
                            <div className="flex items-center gap-1.5">
                              <span className="font-bold text-gray-900 dark:text-white font-mono text-base">1000316347</span>
                              <button 
                                type="button"
                                onClick={() => copyToClipboard('1000316347', 'acct')}
                                className="text-[#5b3ee4] dark:text-[#a5b4fc] p-1 hover:bg-white dark:hover:bg-gray-800 rounded text-xs cursor-pointer"
                                title="Copy account number"
                              >
                                {copiedKey === 'acct' ? <Check className="w-3.5 h-3.5 text-emerald-500" /> : <Copy className="w-3.5 h-3.5" />}
                              </button>
                            </div>
                         </div>
                      </div>
                    </div>

                    <form id="upload-form" onSubmit={handleManualUpload}>
                      <label className="block text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-2">
                        Upload Proof of Payment (Image / PDF)
                      </label>
                      <label className="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed rounded-2xl cursor-pointer border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors overflow-hidden relative">
                        {proofFile ? (
                          <div className="absolute inset-0 bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center p-4 text-center">
                            <span className="text-[#5b3ee4] dark:text-[#a5b4fc] font-medium truncate max-w-full text-sm">
                              {proofFile.name}
                            </span>
                          </div>
                        ) : (
                          <div className="flex flex-col items-center justify-center p-4">
                            <Upload className="w-7 h-7 text-gray-400 mb-2" />
                            <p className="text-xs text-gray-500 dark:text-gray-400 font-medium">Click to select receipt file</p>
                          </div>
                        )}
                        <input 
                          type="file" 
                          className="hidden" 
                          accept="image/*,.pdf" 
                          required 
                          onChange={e => setProofFile(e.target.files[0])}
                        />
                      </label>
                      <p className="text-[11px] text-gray-400 dark:text-gray-500 mt-2">
                        Manual verification takes 1-2 hours after submission.
                      </p>
                    </form>
                  </div>
                )}
              </div>
              
              {/* Order Summary Column */}
              <div className="lg:w-80 shrink-0">
                <div className="bg-gray-50 dark:bg-gray-800 rounded-3xl p-6 sm:p-8 border border-gray-200 dark:border-gray-700 flex flex-col justify-between">
                  <div>
                    <h4 className="font-serif font-bold text-gray-950 dark:text-white mb-4 text-lg">
                      Order Summary
                    </h4>
                    
                    <div className="flex justify-between text-sm mb-3">
                      <span className="text-gray-500 dark:text-gray-400">{selectedPlan.name}</span>
                      <span className="text-gray-900 dark:text-white font-semibold">
                        {getPlanPricing(selectedPlan).primary}
                      </span>
                    </div>

                    <div className="flex justify-between text-xs mb-3">
                      <span className="text-gray-500 dark:text-gray-400">Resolution</span>
                      <span className="text-gray-900 dark:text-white font-medium uppercase">{selectedPlan.resolution}</span>
                    </div>

                    <div className="flex justify-between text-xs mb-3">
                      <span className="text-gray-500 dark:text-gray-400">Billing Cycle</span>
                      <span className="text-gray-900 dark:text-white font-medium capitalize">{selectedPlan.billing_period || 'Monthly'}</span>
                    </div>

                    <div className="border-t border-gray-200 dark:border-gray-700 my-4"></div>

                    <div className="flex justify-between items-baseline mb-6">
                      <span className="font-bold text-gray-900 dark:text-white text-base">Total Due</span>
                      <div className="text-right">
                        <span className="font-bold text-2xl text-[#5b3ee4] dark:text-[#818cf8]">
                          {getPlanPricing(selectedPlan).primary}
                        </span>
                        {getPlanPricing(selectedPlan).secondary && (
                          <div className="text-[11px] text-gray-400">
                            (~{getPlanPricing(selectedPlan).secondary} USD)
                          </div>
                        )}
                      </div>
                    </div>
                  </div>
                  
                  <div>
                    {paymentMethod === 'squad' ? (
                      <button 
                        type="button"
                        onClick={handleInitiateSquad}
                        disabled={submittingSquad}
                        className="w-full bg-[#5b3ee4] hover:bg-[#4f2bee] text-white py-3.5 rounded-full font-bold text-sm transition-all disabled:opacity-50 flex items-center justify-center gap-2 shadow-md active:scale-95 cursor-pointer"
                      >
                        {submittingSquad ? (
                          <>
                            <Loader2 className="w-4 h-4 animate-spin" />
                            Connecting to Squad...
                          </>
                        ) : (
                          <>
                            <Lock className="w-4 h-4" />
                            Pay with Squad ({getPlanPricing(selectedPlan).primary})
                          </>
                        )}
                      </button>
                    ) : (
                      <button 
                        type="submit"
                        form="upload-form"
                        disabled={submittingManual}
                        className="w-full bg-[#5b3ee4] hover:bg-[#4f2bee] text-white py-3.5 rounded-full font-bold text-sm transition-all disabled:opacity-50 flex items-center justify-center gap-2 shadow-md active:scale-95 cursor-pointer"
                      >
                        {submittingManual ? (
                          <>
                            <Loader2 className="w-4 h-4 animate-spin" />
                            Uploading Receipt...
                          </>
                        ) : (
                          <>
                            <Upload className="w-4 h-4" />
                            Submit Receipt
                          </>
                        )}
                      </button>
                    )}

                    <div className="mt-4 text-center">
                      <div className="inline-flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                        <ShieldCheck className="w-3.5 h-3.5 text-emerald-500" />
                        <span>Powered by Squad • 256-bit SSL Encrypted</span>
                      </div>
                    </div>
                  </div>

                </div>
              </div>

            </div>
          </div>
        </div>
      )}

      {/* Step 3: Manual Bank Transfer Receipt Success */}
      {step === 3 && (
        <div className="w-full max-w-2xl bg-white dark:bg-gray-900 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden animate-in zoom-in-95 duration-300 p-8 sm:p-12 text-center mx-auto">
          <div className="w-20 h-20 bg-green-50 dark:bg-green-950/60 rounded-full flex items-center justify-center mx-auto mb-6">
            <Check className="w-10 h-10 text-green-600 dark:text-green-400" />
          </div>
          <h3 className="font-serif text-2xl sm:text-3xl font-bold text-gray-950 dark:text-white mb-2 tracking-tight">
            Receipt Uploaded!
          </h3>
          <p className="text-gray-500 dark:text-gray-400 mx-auto mb-8 text-sm sm:text-base leading-relaxed max-w-md">
            Your transfer receipt has been submitted to the admin team. Once approved, your AWS IVS channel will be activated automatically.
          </p>
          <div className="flex flex-col sm:flex-row items-center justify-center gap-3">
            <button 
              onClick={() => navigate('/dashboard/streaming')}
              className="bg-[#5b3ee4] hover:bg-[#4f2bee] text-white px-8 py-3.5 rounded-full font-bold text-sm transition-all shadow-md active:scale-95 w-full sm:w-auto cursor-pointer"
            >
              Return to Studio
            </button>
            <button 
              onClick={() => { setStep(1); setShowPlansOverride(true); }}
              className="text-gray-600 dark:text-gray-400 hover:text-gray-950 dark:hover:text-white px-6 py-3.5 rounded-full font-medium text-sm transition-colors w-full sm:w-auto cursor-pointer"
            >
              Browse Plans
            </button>
          </div>
        </div>
      )}

    </div>
  );
}
