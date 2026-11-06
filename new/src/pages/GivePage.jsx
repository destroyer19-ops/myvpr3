import React, { useState } from 'react'
import { Landmark, Smartphone, UploadCloud, HeartHandshake, CheckCircle2, Gift } from 'lucide-react'

export default function GivePage() {
  const [submitted, setSubmitted] = useState(false)

  const handleSubmit = async (e) => {
    e.preventDefault()
    
    try {
      const formData = new FormData(e.target);
      const res = await fetch('/api/offerings', {
        method: 'POST',
        body: formData,
      });
      const data = await res.json();
      if (data.success) {
        setSubmitted(true);
      } else {
        alert(data.message || 'Failed to submit offering.');
      }
    } catch (err) {
      console.error(err);
      alert('An error occurred. Please try again.');
    }
  }

  return (
    <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-8 pb-28 lg:pb-16 animate-in fade-in duration-300">
      
      {/* Header */}
      <div className="text-center max-w-3xl mx-auto mb-10 sm:mb-14">
        <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-[#fdf0f4] dark:bg-pink-950/60 text-[#e11d48] mb-4 shadow-2xs">
          <Gift className="w-7 h-7" />
        </div>
        <h1 className="font-serif text-3xl sm:text-4xl lg:text-5xl font-bold text-gray-950 dark:text-white tracking-tight mb-3">
          Give Offerings & Partnership
        </h1>
        <p className="text-sm sm:text-base text-gray-600 dark:text-gray-300 leading-relaxed max-w-2xl mx-auto">
          Your generous partnership helps us take the gospel and worship to the ends of the earth. Thank you for standing with us.
        </p>
      </div>

      <div className="grid lg:grid-cols-12 gap-8 items-start">
        {/* Left Column: Payment Details */}
        <div className="lg:col-span-5 space-y-6">
          
          {/* Bank Transfer Card */}
          <div className="bg-white dark:bg-gray-900 rounded-3xl p-6 sm:p-8 border border-gray-100 dark:border-gray-800 shadow-sm relative overflow-hidden">
            <div className="flex items-center gap-4 mb-6">
              <div className="w-11 h-11 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-[#5b3ee4] flex items-center justify-center">
                <Landmark className="w-6 h-6" />
              </div>
              <div>
                <h2 className="font-serif font-bold text-xl text-gray-950 dark:text-white">Bank Transfer</h2>
                <p className="text-xs text-gray-500 dark:text-gray-400">Direct deposit or internet banking</p>
              </div>
            </div>
            
            <div className="space-y-3.5">
              <div className="bg-[#f4f6fc] dark:bg-gray-850 rounded-2xl p-4 border border-indigo-100/50 dark:border-gray-800">
                <p className="text-[10px] text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider mb-0.5">Account Number</p>
                <p className="text-2xl font-bold text-gray-950 dark:text-white font-mono tracking-wider">1000316347</p>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div className="bg-[#f4f6fc] dark:bg-gray-850 rounded-2xl p-4 border border-indigo-100/50 dark:border-gray-800">
                  <p className="text-[10px] text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider mb-0.5">Bank</p>
                  <p className="font-bold text-sm text-gray-800 dark:text-gray-200">Parallex Bank</p>
                </div>
                <div className="bg-[#f4f6fc] dark:bg-gray-850 rounded-2xl p-4 border border-indigo-100/50 dark:border-gray-800">
                  <p className="text-[10px] text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider mb-0.5">Account Name</p>
                  <p className="font-bold text-sm text-gray-800 dark:text-gray-200 line-clamp-1">LMAM - Music App</p>
                </div>
              </div>
            </div>
          </div>

          {/* Espees Card */}
          <div className="bg-gradient-to-br from-[#f59e0b] to-[#d97706] rounded-3xl p-6 sm:p-8 text-white shadow-lg relative overflow-hidden">
            <div className="flex items-center gap-4 mb-6">
              <div className="w-11 h-11 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center">
                <Smartphone className="w-6 h-6 text-white" />
              </div>
              <div>
                <h2 className="font-serif font-bold text-xl text-white">Espees Transfer</h2>
                <p className="text-amber-100 text-xs">Send instantly via KingsPay</p>
              </div>
            </div>

            <div className="bg-white/15 backdrop-blur-md rounded-2xl p-5 border border-white/20 text-center">
              <p className="text-[11px] text-amber-100 font-semibold uppercase tracking-wider mb-1">Merchant Code</p>
              <p className="text-4xl font-black tracking-widest text-white drop-shadow-sm font-mono">LMM01</p>
            </div>
          </div>
        </div>

        {/* Right Column: Upload Form */}
        <div className="lg:col-span-7">
          <div className="bg-white dark:bg-gray-900 rounded-3xl p-6 sm:p-10 border border-gray-100 dark:border-gray-800 shadow-sm">
            {!submitted ? (
              <>
                <div className="mb-6">
                  <h2 className="font-serif font-bold text-2xl text-gray-950 dark:text-white mb-1">
                    Upload Proof of Payment
                  </h2>
                  <p className="text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                    Once your transfer is complete, submit your details here to confirm your record.
                  </p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                  <div className="grid sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-1.5" htmlFor="name">Full Name</label>
                      <input 
                        type="text" 
                        id="name" 
                        name="name" 
                        required 
                        className="w-full px-4 py-3 rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white text-sm outline-none focus:ring-2 focus:ring-[#5b3ee4]/20 transition-all" 
                        placeholder="E.g. Sister Grace" 
                      />
                    </div>
                    <div>
                      <label className="block text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-1.5" htmlFor="amount">Amount Given (₦ or Espees)</label>
                      <input 
                        type="number" 
                        id="amount" 
                        name="amount" 
                        required 
                        min="1" 
                        step="any" 
                        className="w-full px-4 py-3 rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white text-sm outline-none focus:ring-2 focus:ring-[#5b3ee4]/20 transition-all" 
                        placeholder="5000" 
                      />
                    </div>
                  </div>

                  <div>
                    <label className="block text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-1.5">Receipt / Screenshot</label>
                    <div className="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-2xl hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors cursor-pointer group relative">
                      <input id="proof" name="proof" type="file" className="absolute inset-0 w-full h-full opacity-0 cursor-pointer" required accept="image/*,.pdf" />
                      <div className="space-y-1.5 text-center pointer-events-none">
                        <UploadCloud className="mx-auto h-10 w-10 text-gray-400 group-hover:text-[#5b3ee4] transition-colors" />
                        <div className="text-xs sm:text-sm text-gray-600 dark:text-gray-300">
                          <span className="font-semibold text-[#5b3ee4]">Upload a receipt</span> or drag and drop
                        </div>
                        <p className="text-[11px] text-gray-400">PNG, JPG, PDF up to 5MB</p>
                      </div>
                    </div>
                  </div>

                  <button 
                    type="submit" 
                    className="w-full py-3.5 px-6 rounded-full bg-[#5b3ee4] hover:bg-[#4f2bee] text-white font-semibold text-sm transition-all shadow-md active:scale-95 cursor-pointer"
                  >
                    Submit Record
                  </button>
                </form>
              </>
            ) : (
              <div className="py-12 text-center animate-in zoom-in duration-300">
                <div className="w-16 h-16 bg-emerald-50 dark:bg-emerald-950/60 rounded-full flex items-center justify-center mx-auto mb-4">
                  <CheckCircle2 className="w-8 h-8 text-emerald-600" />
                </div>
                <h2 className="font-serif text-3xl font-bold text-gray-950 dark:text-white mb-2">Thank You!</h2>
                <p className="text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto mb-6 leading-relaxed">
                  Your offering record has been submitted successfully. God bless you abundantly for your partnership!
                </p>
                <button 
                  onClick={() => setSubmitted(false)}
                  className="px-6 py-2.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-sm font-semibold hover:bg-gray-200 transition-colors cursor-pointer"
                >
                  Submit Another Record
                </button>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  )
}
