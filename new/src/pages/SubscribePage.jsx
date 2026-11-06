import { useState, useEffect, useMemo } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { 
  Loader2, Crown, Check, ChevronRight, Upload, X, 
  Lock, Sparkles, ShieldCheck, Zap, Radio, 
  CheckCircle2, AlertCircle 
} from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import { subscriptionsApi } from '../services/api';

// Default static fallback plans in case API is loading or offline
const DEFAULT_PLANS = [
  {
    id: 1,
    plan_code: 'member_free',
    name: 'Free Worship',
    description: 'Basic access to daily praise broadcasts and public crusade viewings.',
    category: 'membership',
    price: 0,
    currency: 'NGN',
    interval_unit: 'month',
    interval_count: 1,
    features: [
      'Unlimited public live broadcasts',
      'Access to open crusade rooms',
      'Standard video quality (720p)',
      'Community live chat participation'
    ],
    limits: { max_crusade_attendees: 0, can_host_crusade: false },
    is_active: 1,
    is_featured: 0,
    badge_text: null
  },
  {
    id: 2,
    plan_code: 'member_monthly',
    name: 'Premium Member',
    description: 'Complete individual access with HD streams, digital crusade hosting, and full VOD library.',
    category: 'membership',
    price: 3000,
    currency: 'NGN',
    interval_unit: 'month',
    interval_count: 1,
    features: [
      'Full 1080p Full HD praise broadcasts',
      'Host digital crusades with up to 5,000 attendees',
      'Create praise meeting rooms & watch parties',
      'Complete on-demand video & ministration library',
      'Exclusive children outreach series & teachings',
      'No interruptions or stream cooldowns'
    ],
    limits: { max_crusade_attendees: 5000, can_host_crusade: true },
    is_active: 1,
    is_featured: 1,
    badge_text: 'Most Popular'
  },
  {
    id: 3,
    plan_code: 'member_annual',
    name: 'Premium Annual',
    description: 'Full premium access for a full year with 2 months free savings.',
    category: 'membership',
    price: 30000,
    currency: 'NGN',
    interval_unit: 'year',
    interval_count: 1,
    features: [
      'Everything in Premium Member plan',
      'Save ₦6,000 annually (2 months free)',
      'Priority access to special praise festivals',
      'Dedicated prayer & testimony spotlight eligibility',
      'Early access to upcoming ministration releases'
    ],
    limits: { max_crusade_attendees: 5000, can_host_crusade: true },
    is_active: 1,
    is_featured: 0,
    badge_text: 'Best Value (Save 17%)'
  },
  {
    id: 4,
    plan_code: 'studio_basic',
    name: 'Studio Broadcaster',
    description: 'Professional broadcasting suite with dedicated live channel and OBS support.',
    category: 'streaming',
    price: 15000,
    currency: 'NGN',
    interval_unit: 'month',
    interval_count: 1,
    features: [
      'Dedicated AWS IVS live broadcasting channel',
      'Up to 100 concurrent live viewers',
      '4 hours max continuous broadcast duration',
      '720p HD streaming with OBS or Browser Studio',
      'Real-time viewer engagement analytics'
    ],
    limits: { max_viewers: 100, max_duration_hours: 4 },
    is_active: 1,
    is_featured: 0,
    badge_text: 'Broadcasters'
  }
];

export default function SubscribePage() {
  const { user, refreshUser } = useAuth();
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();

  // State
  const [plans, setPlans] = useState(DEFAULT_PLANS);
  const [loadingPlans, setLoadingPlans] = useState(true);
  const [subscriptionData, setSubscriptionData] = useState(null);
  const [billingInterval, setBillingInterval] = useState('month'); // 'month' or 'year'
  
  // Checkout Modal State
  const [checkoutModalOpen, setCheckoutModalOpen] = useState(false);
  const [selectedPlan, setSelectedPlan] = useState(null);
  const [paymentMethod, setPaymentMethod] = useState('squad'); // 'squad' or 'manual' or 'espees'
  const [submittingPayment, setSubmittingPayment] = useState(false);
  const [enableAutoRenew, setEnableAutoRenew] = useState(false);
  
  // Manual Proof Form State
  const [formData, setFormData] = useState({
    fullName: user?.name || user?.username || '',
    kcHandle: user?.username || '',
    giftedUsername: '',
    giftMessage: '',
    isGift: false
  });
  const [proofFile, setProofFile] = useState(null);
  const [manualSuccess, setManualSuccess] = useState(false);

  // Verification State (from Squad redirect)
  const [verifyingPayment, setVerifyingPayment] = useState(false);
  const [verificationResult, setVerificationResult] = useState(null);
  const [verificationError, setVerificationError] = useState('');

  // 1. Fetch plans & current subscription status
  useEffect(() => {
    async function loadData() {
      setLoadingPlans(true);
      try {
        const [plansRes, subRes] = await Promise.all([
          subscriptionsApi.getPlans().catch(() => null),
          user ? subscriptionsApi.getStatus().catch(() => null) : Promise.resolve(null)
        ]);

        if (plansRes?.success && Array.isArray(plansRes.data) && plansRes.data.length > 0) {
          setPlans(plansRes.data);
        }
        if (subRes?.success) {
          setSubscriptionData(subRes.data);
        }
      } catch (err) {
        console.error('Error fetching subscription data', err);
      } finally {
        setLoadingPlans(false);
      }
    }
    loadData();
  }, [user]);

  // 2. Handle Squad Return Callback (?squad_ref=vpr_sq_... or ?reference=... or ?transaction_ref=...)
  useEffect(() => {
    const squadRef = searchParams.get('squad_ref') || searchParams.get('reference') || searchParams.get('transaction_ref');
    if (squadRef) {
      async function verify() {
        setVerifyingPayment(true);
        setVerificationError('');
        try {
          const res = await subscriptionsApi.verifySquadPayment(squadRef);
          if (res?.success) {
            setVerificationResult(res);
            await refreshUser();
            const subRes = await subscriptionsApi.getStatus().catch(() => null);
            if (subRes?.success) setSubscriptionData(subRes.data);
          } else {
            setVerificationError(res?.message || 'Payment verification was not successful. Please contact support.');
          }
        } catch (err) {
          setVerificationError(err?.message || 'Unable to verify payment with gateway. If debited, your subscription will be activated automatically via webhook.');
        } finally {
          setVerifyingPayment(false);
          // Clean URL params so refresh doesn't re-trigger
          searchParams.delete('squad_ref');
          searchParams.delete('reference');
          searchParams.delete('transaction_ref');
          searchParams.delete('squad_simulated');
          setSearchParams(searchParams, { replace: true });
        }
      }
      verify();
    }
  }, [searchParams]);

  // Filter plans according to selected interval (monthly vs annual)
  const displayedPlans = useMemo(() => {
    return plans.filter(p => {
      if (p.plan_code === 'member_free') return true;
      if (billingInterval === 'year') {
        return p.interval_unit === 'year' || p.category === 'streaming';
      }
      return p.interval_unit === 'month' || p.interval_unit === 'day';
    });
  }, [plans, billingInterval]);

  // Open checkout modal for a plan
  const handleSelectPlan = (plan) => {
    if (plan.price === 0) {
      navigate('/dashboard');
      return;
    }
    if (!user) {
      sessionStorage.setItem('pendingPlanCode', plan.plan_code);
      navigate(`/login?redirect=/subscribe`);
      return;
    }
    setSelectedPlan(plan);
    setPaymentMethod('squad');
    setCheckoutModalOpen(true);
  };

  // Initiate Squad payment flow
  const handleInitiateSquad = async () => {
    if (!selectedPlan) return;
    setSubmittingPayment(true);
    try {
      const payload = {
        plan_code: selectedPlan.plan_code,
        is_recurring: enableAutoRenew,
        gifted_username: formData.isGift ? formData.giftedUsername : undefined,
        gift_message: formData.isGift ? formData.giftMessage : undefined
      };

      const res = await subscriptionsApi.initiateSquadPayment(payload);
      if (res?.success && res.checkout_url) {
        // Redirect to Squad hosted checkout
        window.location.href = res.checkout_url;
      } else {
        alert(res?.message || 'Failed to initiate payment with Squad gateway.');
      }
    } catch (err) {
      console.error(err);
      alert(err?.message || 'An error occurred while connecting to Squad.');
    } finally {
      setSubmittingPayment(false);
    }
  };

  // Handle manual bank transfer proof submission
  const handleManualSubmit = async (e) => {
    e.preventDefault();
    if (!proofFile) {
      alert('Please upload a proof of transfer receipt (JPG, PNG, or PDF).');
      return;
    }
    setSubmittingPayment(true);
    try {
      const formPayload = new FormData();
      formPayload.append('plan', selectedPlan.plan_code);
      formPayload.append('full_name', formData.fullName);
      formPayload.append('proof', proofFile);
      if (formData.isGift && formData.giftedUsername) {
        formPayload.append('gifted_username', formData.giftedUsername);
      }

      const res = await subscriptionsApi.submitManualSubscription(formPayload);
      if (res?.success) {
        setManualSuccess(true);
        await refreshUser();
      } else {
        alert(res?.message || 'Failed to upload proof. Please try again.');
      }
    } catch (err) {
      console.error(err);
      alert('Error submitting payment proof. Please try again.');
    } finally {
      setSubmittingPayment(false);
    }
  };

  // Current subscription status checks
  const isSubscribed = user?.isSubscribed || subscriptionData?.is_subscribed;
  const currentPlanCode = subscriptionData?.current?.plan_code || (isSubscribed ? 'member_monthly' : null);
  const subscriptionEndDate = subscriptionData?.current?.end_date;

  return (
    <div className="min-h-screen bg-[#fcfcfd] dark:bg-[#090d16] text-gray-950 dark:text-white transition-colors duration-200">
      
      {/* ── Verification Banner / Modal State ─────────────────────────────── */}
      {verifyingPayment && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-white dark:bg-gray-900 rounded-3xl p-8 max-w-md w-full text-center shadow-2xl border border-gray-100 dark:border-gray-800">
            <Loader2 className="w-12 h-12 text-[#5b3ee4] animate-spin mx-auto mb-4" />
            <h3 className="font-serif text-2xl font-bold mb-2">Verifying Your Payment</h3>
            <p className="text-gray-500 dark:text-gray-400 text-sm">
              Please wait while we confirm your transaction with Squad...
            </p>
          </div>
        </div>
      )}

      {/* Verification Success Alert */}
      {verificationResult && (
        <div className="max-w-5xl mx-auto px-4 pt-8">
          <div className="bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-3xl p-6 sm:p-8 flex flex-col sm:flex-row items-center justify-between gap-4 shadow-sm animate-in fade-in duration-300">
            <div className="flex items-center gap-4">
              <div className="w-12 h-12 bg-emerald-600 text-white rounded-2xl flex items-center justify-center shrink-0">
                <CheckCircle2 className="w-7 h-7" />
              </div>
              <div>
                <h4 className="font-serif text-xl font-bold text-emerald-950 dark:text-emerald-100">
                  Payment Confirmed & Subscription Active!
                </h4>
                <p className="text-sm text-emerald-700 dark:text-emerald-300 mt-0.5">
                  Reference: <span className="font-mono font-semibold">{verificationResult.reference}</span>
                  {verificationResult.data?.end_date && ` • Valid until ${new Date(verificationResult.data.end_date).toLocaleDateString()}`}
                </p>
              </div>
            </div>
            <button
              onClick={() => setVerificationResult(null)}
              className="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-full text-xs font-semibold cursor-pointer transition-colors shrink-0"
            >
              Continue to Worship
            </button>
          </div>
        </div>
      )}

      {/* Verification Error Alert */}
      {verificationError && (
        <div className="max-w-5xl mx-auto px-4 pt-8">
          <div className="bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-3xl p-6 flex items-start gap-4 shadow-sm">
            <AlertCircle className="w-6 h-6 text-amber-600 shrink-0 mt-0.5" />
            <div className="flex-1">
              <h4 className="font-bold text-amber-950 dark:text-amber-100 text-sm">Verification Notice</h4>
              <p className="text-sm text-amber-800 dark:text-amber-300 mt-1">{verificationError}</p>
            </div>
            <button
              onClick={() => setVerificationError('')}
              className="text-amber-700 dark:text-amber-300 hover:text-amber-950 text-xs font-bold"
            >
              Dismiss
            </button>
          </div>
        </div>
      )}

      {/* ── Active Subscription Status Ribbon (for logged in members) ───────── */}
      {isSubscribed && (
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8">
          <div className="bg-gradient-to-r from-amber-500/10 via-purple-500/10 to-blue-500/10 border border-amber-200/60 dark:border-amber-800/40 rounded-3xl p-5 sm:p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div className="flex items-center gap-3.5">
              <div className="w-10 h-10 rounded-2xl bg-amber-500 text-white flex items-center justify-center font-bold shadow-sm">
                <Crown className="w-5 h-5" />
              </div>
              <div>
                <div className="flex items-center gap-2">
                  <span className="text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">Current Plan</span>
                  <span className="px-2 py-0.5 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">Active</span>
                </div>
                <h3 className="text-lg font-bold text-gray-950 dark:text-white mt-0.5">
                  {subscriptionData?.current?.plan_name || 'Premium Member'}
                </h3>
                {subscriptionEndDate && (
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    {subscriptionData?.days_remaining > 0
                      ? `${subscriptionData.days_remaining} days remaining • Renews / expires on ${new Date(subscriptionEndDate).toLocaleDateString()}`
                      : `Expires on ${new Date(subscriptionEndDate).toLocaleDateString()}`}
                  </p>
                )}
              </div>
            </div>
            <div className="flex items-center gap-3 w-full md:w-auto">
              <button
                onClick={() => {
                  const targetPlan = plans.find(p => p.plan_code === 'member_annual') || plans[1];
                  handleSelectPlan(targetPlan);
                }}
                className="w-full md:w-auto px-5 py-2.5 rounded-full bg-white dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-900 dark:text-white text-xs font-semibold border border-gray-200 dark:border-gray-700 transition-all cursor-pointer shadow-sm text-center"
              >
                Extend or Upgrade Plan
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ── Hero Header ────────────────────────────────────────────────────── */}
      <section className="relative pt-12 pb-16 sm:pt-16 sm:pb-20 text-center px-4 overflow-hidden">
        {/* Subtle decorative glow */}
        <div className="absolute top-0 left-1/2 -translate-x-1/2 w-96 h-96 bg-purple-500/10 dark:bg-purple-500/5 rounded-full blur-3xl pointer-events-none" />

        <div className="max-w-3xl mx-auto relative z-10">
          <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-blue-50 dark:bg-blue-950/60 border border-blue-200 dark:border-blue-900/50 text-blue-700 dark:text-blue-300 text-xs font-semibold mb-6">
            <Sparkles className="w-3.5 h-3.5" />
            Transparent Pricing • Instant Squad Gateway Activation
          </div>

          <h1 className="font-serif text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight text-gray-950 dark:text-white leading-[1.15]">
            Worship without boundaries.
          </h1>
          <p className="mt-4 sm:mt-5 text-base sm:text-lg text-gray-600 dark:text-gray-300 max-w-2xl mx-auto leading-relaxed">
            Experience uncompressed praise broadcasts, host digital crusades with up to 5,000 worshippers, and explore our complete ministry library.
          </p>

          {/* Billing Interval Toggle (Monthly vs Annual) */}
          <div className="mt-8 sm:mt-10 inline-flex items-center p-1.5 rounded-full bg-gray-100 dark:bg-gray-800/80 border border-gray-200 dark:border-gray-700">
            <button
              onClick={() => setBillingInterval('month')}
              className={`px-5 py-2 rounded-full text-xs sm:text-sm font-semibold transition-all cursor-pointer ${
                billingInterval === 'month'
                  ? 'bg-white dark:bg-gray-900 text-gray-950 dark:text-white shadow-sm'
                  : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
              }`}
            >
              Monthly Billing
            </button>
            <button
              onClick={() => setBillingInterval('year')}
              className={`px-5 py-2 rounded-full text-xs sm:text-sm font-semibold transition-all flex items-center gap-2 cursor-pointer ${
                billingInterval === 'year'
                  ? 'bg-white dark:bg-gray-900 text-gray-950 dark:text-white shadow-sm'
                  : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
              }`}
            >
              <span>Annual Billing</span>
              <span className="px-2 py-0.5 text-[10px] font-bold rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                2 Mo Free
              </span>
            </button>
          </div>
        </div>
      </section>

      {/* ── Plans Discovery Grid ────────────────────────────────────────────── */}
      <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">
        {loadingPlans ? (
          <div className="flex flex-col items-center justify-center py-20">
            <Loader2 className="w-10 h-10 text-[#5b3ee4] animate-spin mb-3" />
            <p className="text-sm text-gray-500 dark:text-gray-400 font-medium">Loading subscription tiers...</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 items-stretch">
            {displayedPlans.map((plan) => {
              const isCurrent = currentPlanCode === plan.plan_code;
              const isFeatured = plan.is_featured === 1 || plan.badge_text === 'Most Popular';
              const priceDisplay = plan.price === 0 ? 'Free' : `₦${Number(plan.price).toLocaleString()}`;
              const intervalText = plan.price === 0 ? 'Forever' : (plan.interval_unit === 'year' ? 'per year' : 'per month');

              return (
                <div
                  key={plan.id || plan.plan_code}
                  className={`relative rounded-3xl transition-all duration-300 flex flex-col justify-between overflow-hidden ${
                    isFeatured
                      ? 'bg-white dark:bg-gray-900 border-2 border-[#5b3ee4] shadow-xl md:-translate-y-2'
                      : 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm hover:shadow-md'
                  }`}
                >
                  {/* Highlighted Badge */}
                  {plan.badge_text && (
                    <div className="absolute top-0 right-0 bg-[#5b3ee4] text-white text-[11px] font-bold px-4 py-1 rounded-bl-xl uppercase tracking-wider shadow-sm">
                      {plan.badge_text}
                    </div>
                  )}

                  <div className="p-6 sm:p-8">
                    {/* Header */}
                    <div className="mb-6">
                      <div className="w-12 h-12 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-900 dark:text-white mb-4">
                        {plan.category === 'streaming' ? (
                          <Radio className="w-6 h-6 text-purple-600" />
                        ) : isFeatured ? (
                          <Crown className="w-6 h-6 text-amber-500" />
                        ) : (
                          <Zap className="w-6 h-6 text-blue-600" />
                        )}
                      </div>
                      <h3 className="font-serif text-2xl font-bold text-gray-950 dark:text-white">
                        {plan.name}
                      </h3>
                      <p className="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1 min-h-[38px] leading-relaxed">
                        {plan.description}
                      </p>
                    </div>

                    {/* Price Block */}
                    <div className="pb-6 mb-6 border-b border-gray-100 dark:border-gray-800">
                      <div className="flex items-baseline gap-1.5">
                        <span className="font-serif text-3xl sm:text-4xl font-black text-gray-950 dark:text-white">
                          {priceDisplay}
                        </span>
                        <span className="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400">
                          /{intervalText}
                        </span>
                      </div>
                      {plan.interval_unit === 'year' && (
                        <p className="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold mt-1">
                          Equivalent to ₦2,500/month (Save ₦6,000/yr)
                        </p>
                      )}
                    </div>

                    {/* Features List */}
                    <div className="space-y-3.5 mb-8">
                      <p className="text-[11px] font-bold uppercase tracking-wider text-gray-400">What's included</p>
                      {(plan.features || []).map((feat, idx) => (
                        <div key={idx} className="flex items-start gap-2.5 text-xs sm:text-sm text-gray-700 dark:text-gray-300">
                          <Check className="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
                          <span>{feat}</span>
                        </div>
                      ))}
                    </div>
                  </div>

                  {/* Card Bottom CTA */}
                  <div className="p-6 sm:p-8 pt-0">
                    <button
                      onClick={() => handleSelectPlan(plan)}
                      disabled={isCurrent}
                      className={`w-full py-3.5 px-6 rounded-full text-xs sm:text-sm font-semibold transition-all flex items-center justify-center gap-2 cursor-pointer shadow-sm active:scale-[0.98] ${
                        isCurrent
                          ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 cursor-default'
                          : isFeatured
                          ? 'bg-[#5b3ee4] hover:bg-[#4f2bee] text-white shadow-md'
                          : 'bg-gray-950 hover:bg-gray-800 dark:bg-white dark:hover:bg-gray-100 text-white dark:text-gray-950'
                      }`}
                    >
                      {isCurrent ? (
                        <>
                          <Check className="w-4 h-4" /> Current Active Plan
                        </>
                      ) : (
                        <>
                          {plan.price === 0 ? 'Get Started' : 'Subscribe Now'}
                          <ChevronRight className="w-4 h-4" />
                        </>
                      )}
                    </button>
                    {plan.price > 0 && (
                      <p className="text-center text-[11px] text-gray-400 dark:text-gray-500 mt-2">
                        Instant activation via Squad Gateway
                      </p>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </section>

      {/* ── Feature Comparison Table (Netflix / Prime style) ────────────────── */}
      <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 border-t border-gray-100 dark:border-gray-800">
        <div className="text-center max-w-2xl mx-auto mb-12">
          <h2 className="font-serif text-3xl font-bold text-gray-950 dark:text-white">
            Compare Plan Features
          </h2>
          <p className="text-sm text-gray-500 dark:text-gray-400 mt-2">
            Choose the right level of fellowship and ministry impact for your personal worship or ministry leadership.
          </p>
        </div>

        <div className="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-800 overflow-hidden shadow-sm">
          <div className="overflow-x-auto">
            <table className="w-full text-sm text-left">
              <thead>
                <tr className="border-b border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/40">
                  <th className="py-4 px-6 font-semibold text-gray-950 dark:text-white">Feature</th>
                  <th className="py-4 px-6 font-semibold text-gray-950 dark:text-white text-center">Free Worship</th>
                  <th className="py-4 px-6 font-semibold text-[#5b3ee4] dark:text-purple-400 text-center">Premium Member</th>
                  <th className="py-4 px-6 font-semibold text-gray-950 dark:text-white text-center">Studio Broadcaster</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                <tr>
                  <td className="py-4 px-6 font-medium text-gray-900 dark:text-white">Video Quality</td>
                  <td className="py-4 px-6 text-center text-gray-600 dark:text-gray-400">720p HD</td>
                  <td className="py-4 px-6 text-center font-bold text-emerald-600 dark:text-emerald-400">1080p Full HD</td>
                  <td className="py-4 px-6 text-center font-bold text-emerald-600 dark:text-emerald-400">1080p 60fps Broadcast</td>
                </tr>
                <tr>
                  <td className="py-4 px-6 font-medium text-gray-900 dark:text-white">Digital Crusade Hosting</td>
                  <td className="py-4 px-6 text-center text-gray-400">—</td>
                  <td className="py-4 px-6 text-center font-bold text-gray-900 dark:text-white">Up to 5,000 Attendees</td>
                  <td className="py-4 px-6 text-center font-bold text-gray-900 dark:text-white">Up to 10,000 Attendees</td>
                </tr>
                <tr>
                  <td className="py-4 px-6 font-medium text-gray-900 dark:text-white">Praise Meetings & Watch Parties</td>
                  <td className="py-4 px-6 text-center text-gray-400">Join Only</td>
                  <td className="py-4 px-6 text-center text-emerald-600"><Check className="w-5 h-5 mx-auto" /></td>
                  <td className="py-4 px-6 text-center text-emerald-600"><Check className="w-5 h-5 mx-auto" /></td>
                </tr>
                <tr>
                  <td className="py-4 px-6 font-medium text-gray-900 dark:text-white">On-Demand VOD Library</td>
                  <td className="py-4 px-6 text-center text-gray-600 dark:text-gray-400">Limited Samples</td>
                  <td className="py-4 px-6 text-center font-bold text-gray-900 dark:text-white">Complete Catalog</td>
                  <td className="py-4 px-6 text-center font-bold text-gray-900 dark:text-white">Complete Catalog</td>
                </tr>
                <tr>
                  <td className="py-4 px-6 font-medium text-gray-900 dark:text-white">Dedicated AWS IVS Live Channel</td>
                  <td className="py-4 px-6 text-center text-gray-400">—</td>
                  <td className="py-4 px-6 text-center text-gray-400">—</td>
                  <td className="py-4 px-6 text-center text-emerald-600"><Check className="w-5 h-5 mx-auto" /></td>
                </tr>
                <tr>
                  <td className="py-4 px-6 font-medium text-gray-900 dark:text-white">Ad-free Praise Experience</td>
                  <td className="py-4 px-6 text-center text-gray-400">—</td>
                  <td className="py-4 px-6 text-center text-emerald-600"><Check className="w-5 h-5 mx-auto" /></td>
                  <td className="py-4 px-6 text-center text-emerald-600"><Check className="w-5 h-5 mx-auto" /></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      {/* ── Trust & FAQ Section ────────────────────────────────────────────── */}
      <section className="max-w-4xl mx-auto px-4 pb-24">
        <div className="bg-gray-50 dark:bg-gray-900 rounded-3xl p-6 sm:p-10 border border-gray-200 dark:border-gray-800">
          <h3 className="font-serif text-2xl font-bold text-gray-950 dark:text-white mb-6 text-center">
            Frequently Asked Questions
          </h3>
          <div className="space-y-6">
            <div>
              <h4 className="font-bold text-sm text-gray-900 dark:text-white">
                How does Squad payment processing work?
              </h4>
              <p className="text-xs sm:text-sm text-gray-600 dark:text-gray-400 mt-1 leading-relaxed">
                Payments are securely processed through Squad by HabariPay (a subsidiary of GTCO). You can pay using your debit card, bank transfer, USSD, or direct account debit. Once verified, your MyVPR subscription activates immediately.
              </p>
            </div>
            <div>
              <h4 className="font-bold text-sm text-gray-900 dark:text-white">
                Can I cancel or change my plan anytime?
              </h4>
              <p className="text-xs sm:text-sm text-gray-600 dark:text-gray-400 mt-1 leading-relaxed">
                Yes. If you decide to cancel, your access continues without interruption until the end of your current billing period. We do not surprise you with unexpected charges.
              </p>
            </div>
            <div>
              <h4 className="font-bold text-sm text-gray-900 dark:text-white">
                What happens when I renew while active?
              </h4>
              <p className="text-xs sm:text-sm text-gray-600 dark:text-gray-400 mt-1 leading-relaxed">
                Our system automatically extends your validity from your current expiration date. You will never lose days by renewing early.
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* ── Checkout Modal ─────────────────────────────────────────────────── */}
      {checkoutModalOpen && selectedPlan && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
          <div className="bg-white dark:bg-gray-900 rounded-3xl max-w-xl w-full shadow-2xl border border-gray-100 dark:border-gray-800 overflow-hidden my-8 animate-in zoom-in-95 duration-200">
            
            {/* Modal Header */}
            <div className="p-6 sm:p-8 pb-4 flex items-center justify-between border-b border-gray-100 dark:border-gray-800">
              <div>
                <span className="text-[10px] font-bold uppercase tracking-wider text-[#5b3ee4] dark:text-purple-400">Checkout</span>
                <h3 className="font-serif text-2xl font-bold text-gray-950 dark:text-white mt-0.5">
                  Complete Your Subscription
                </h3>
              </div>
              <button
                onClick={() => {
                  setCheckoutModalOpen(false);
                  setManualSuccess(false);
                }}
                className="w-9 h-9 rounded-full bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 flex items-center justify-center text-gray-500 cursor-pointer"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {manualSuccess ? (
              <div className="p-8 text-center">
                <div className="w-16 h-16 bg-emerald-50 dark:bg-emerald-950/60 rounded-full flex items-center justify-center mx-auto mb-4 text-emerald-600">
                  <CheckCircle2 className="w-8 h-8" />
                </div>
                <h4 className="font-serif text-2xl font-bold mb-2">Proof Uploaded Successfully</h4>
                <p className="text-sm text-gray-500 dark:text-gray-400 mb-6 max-w-sm mx-auto">
                  Your manual payment receipt has been submitted and is currently pending administrator verification.
                </p>
                <button
                  onClick={() => {
                    setCheckoutModalOpen(false);
                    setManualSuccess(false);
                  }}
                  className="bg-[#5b3ee4] text-white px-8 py-3 rounded-full text-xs font-semibold"
                >
                  Close
                </button>
              </div>
            ) : (
              <div className="p-6 sm:p-8 pt-4">
                {/* Plan Summary Card */}
                <div className="bg-gray-50 dark:bg-gray-800/60 rounded-2xl p-4 sm:p-5 flex items-center justify-between mb-6">
                  <div>
                    <h4 className="font-bold text-gray-950 dark:text-white text-base">{selectedPlan.name}</h4>
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                      {selectedPlan.interval_unit === 'year' ? '12-Month Access' : '1-Month Access'}
                    </p>
                  </div>
                  <div className="text-right">
                    <span className="font-serif text-2xl font-black text-gray-950 dark:text-white">
                      ₦{Number(selectedPlan.price).toLocaleString()}
                    </span>
                    <span className="text-xs text-gray-500 block">NGN</span>
                  </div>
                </div>

                {/* Payment Method Selector Tabs */}
                <div className="flex border-b border-gray-200 dark:border-gray-800 mb-6">
                  <button
                    onClick={() => setPaymentMethod('squad')}
                    className={`pb-3 px-4 font-semibold text-xs sm:text-sm border-b-2 transition-all cursor-pointer ${
                      paymentMethod === 'squad'
                        ? 'border-[#5b3ee4] text-[#5b3ee4] dark:text-purple-400'
                        : 'border-transparent text-gray-500 hover:text-gray-900 dark:hover:text-white'
                    }`}
                  >
                    Squad Instant Online
                  </button>
                  <button
                    onClick={() => setPaymentMethod('manual')}
                    className={`pb-3 px-4 font-semibold text-xs sm:text-sm border-b-2 transition-all cursor-pointer ${
                      paymentMethod === 'manual'
                        ? 'border-[#5b3ee4] text-[#5b3ee4] dark:text-purple-400'
                        : 'border-transparent text-gray-500 hover:text-gray-900 dark:hover:text-white'
                    }`}
                  >
                    Bank Transfer Proof
                  </button>
                  <button
                    onClick={() => setPaymentMethod('espees')}
                    className={`pb-3 px-4 font-semibold text-xs sm:text-sm border-b-2 transition-all cursor-pointer ${
                      paymentMethod === 'espees'
                        ? 'border-[#5b3ee4] text-[#5b3ee4] dark:text-purple-400'
                        : 'border-transparent text-gray-500 hover:text-gray-900 dark:hover:text-white'
                    }`}
                  >
                    Espees Wallet
                  </button>
                </div>

                {/* SQUAD INSTANT PAYMENT */}
                {paymentMethod === 'squad' && (
                  <div className="space-y-4">
                    <div className="bg-blue-50/60 dark:bg-blue-950/20 rounded-2xl p-4 border border-blue-100 dark:border-blue-900/40 text-xs sm:text-sm text-blue-900 dark:text-blue-200">
                      <div className="flex items-center gap-2 font-bold mb-1">
                        <ShieldCheck className="w-4 h-4 text-blue-600" /> Instant Gateway Checkout
                      </div>
                      <p className="text-xs text-blue-800 dark:text-blue-300">
                        Pay securely with Card, Bank Transfer, USSD, or Squad Account. Your subscription activates immediately upon payment confirmation.
                      </p>
                    </div>

                    {/* Gift Option Toggle */}
                    <div className="p-3 bg-gray-50 dark:bg-gray-800/40 rounded-xl border border-gray-100 dark:border-gray-800">
                      <label className="flex items-center gap-2 cursor-pointer text-xs font-semibold text-gray-700 dark:text-gray-300">
                        <input
                          type="checkbox"
                          checked={formData.isGift}
                          onChange={(e) => setFormData({ ...formData, isGift: e.target.checked })}
                          className="rounded text-[#5b3ee4]"
                        />
                        <span>Bless someone else (Gift this subscription)</span>
                      </label>
                      {formData.isGift && (
                        <div className="mt-3 space-y-2">
                          <input
                            type="text"
                            placeholder="Recipient's Username or Email"
                            value={formData.giftedUsername}
                            onChange={(e) => setFormData({ ...formData, giftedUsername: e.target.value })}
                            className="w-full px-3 py-2 rounded-xl text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 outline-none"
                          />
                          <input
                            type="text"
                            placeholder="Optional blessing message"
                            value={formData.giftMessage}
                            onChange={(e) => setFormData({ ...formData, giftMessage: e.target.value })}
                            className="w-full px-3 py-2 rounded-xl text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 outline-none"
                          />
                        </div>
                      )}
                    </div>

                    <button
                      onClick={handleInitiateSquad}
                      disabled={submittingPayment}
                      className="w-full bg-[#5b3ee4] hover:bg-[#4f2bee] text-white py-3.5 rounded-full font-semibold text-sm transition-all flex items-center justify-center gap-2 shadow-md cursor-pointer disabled:opacity-50"
                    >
                      {submittingPayment ? (
                        <>
                          <Loader2 className="w-4 h-4 animate-spin" /> Connecting to Squad...
                        </>
                      ) : (
                        <>
                          <Lock className="w-4 h-4" /> Pay ₦{Number(selectedPlan.price).toLocaleString()} with Squad
                        </>
                      )}
                    </button>
                  </div>
                )}

                {/* MANUAL BANK PROOF UPLOAD */}
                {paymentMethod === 'manual' && (
                  <form onSubmit={handleManualSubmit} className="space-y-4">
                    <div className="bg-amber-50/60 dark:bg-amber-950/20 rounded-2xl p-4 border border-amber-200/60 dark:border-amber-900/40 text-xs text-amber-900 dark:text-amber-200">
                      <p className="font-bold mb-1">Parallex Bank Account Details:</p>
                      <p>Account Number: <strong className="font-mono text-sm">1000316347</strong></p>
                      <p>Account Name: <strong>LMAM - Music App</strong></p>
                    </div>

                    <div>
                      <label className="block text-[11px] font-bold text-gray-500 uppercase mb-1">Full Name</label>
                      <input
                        type="text"
                        required
                        value={formData.fullName}
                        onChange={(e) => setFormData({ ...formData, fullName: e.target.value })}
                        className="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs outline-none"
                      />
                    </div>

                    <div>
                      <label className="block text-[11px] font-bold text-gray-500 uppercase mb-1">Transfer Receipt</label>
                      <label className="flex flex-col items-center justify-center w-full h-24 border-2 border-dashed rounded-2xl cursor-pointer border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40 hover:bg-gray-100 transition-colors">
                        <Upload className="w-5 h-5 text-gray-400 mb-1" />
                        <span className="text-xs text-gray-500">
                          {proofFile ? proofFile.name : 'Click to select receipt image/PDF'}
                        </span>
                        <input
                          type="file"
                          accept="image/*,application/pdf"
                          required
                          className="hidden"
                          onChange={(e) => setProofFile(e.target.files[0])}
                        />
                      </label>
                    </div>

                    <button
                      type="submit"
                      disabled={submittingPayment}
                      className="w-full bg-gray-950 hover:bg-gray-800 dark:bg-white dark:hover:bg-gray-100 text-white dark:text-gray-950 py-3 rounded-full font-semibold text-xs cursor-pointer disabled:opacity-50 flex items-center justify-center gap-2"
                    >
                      {submittingPayment ? <Loader2 className="w-4 h-4 animate-spin" /> : 'Submit Proof for Admin Review'}
                    </button>
                  </form>
                )}

                {/* ESPEES WALLET DETAILS */}
                {paymentMethod === 'espees' && (
                  <div className="space-y-4">
                    <div className="bg-purple-50 dark:bg-purple-950/20 rounded-2xl p-5 border border-purple-100 dark:border-purple-900/40 text-xs sm:text-sm text-purple-950 dark:text-purple-200">
                      <p className="font-bold mb-2 text-sm">Pay via Espees Wallet:</p>
                      <p className="mb-1">Merchant Code: <strong className="font-mono text-base text-[#5b3ee4] dark:text-purple-300">LMM01</strong></p>
                      <p className="mb-2">Merchant Name: <strong>Virtual Praise Room</strong></p>
                      <p className="text-xs text-gray-600 dark:text-gray-400">
                        Transfer the equivalent amount from your KingsChat / Espees wallet, then upload your transaction reference or receipt via the Bank Transfer Proof tab for fast approval.
                      </p>
                    </div>
                  </div>
                )}

              </div>
            )}

          </div>
        </div>
      )}

    </div>
  );
}
