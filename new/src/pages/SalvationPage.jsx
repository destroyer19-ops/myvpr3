import React, { useState } from 'react'
import { Heart, Sparkles, Gift, User, Mail, CheckCircle2, Quote } from 'lucide-react'

export default function SalvationPage() {
  const [step, setStep] = useState(1) // 1: Prayer, 2: Form, 3: Success

  const handleSubmit = async (e) => {
    e.preventDefault()
    
    try {
      const formData = new FormData(e.target)
      await fetch('/api/salvations', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          name: formData.get('name'),
          email: formData.get('email')
        })
      });
    } catch (err) {
      console.error(err);
    }

    setStep(3)
    
    // Trigger direct download
    const link = document.createElement('a')
    link.href = '/Now-That-Your-Born-Again.pdf'
    link.download = 'Now-That-Your-Born-Again.pdf'
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  }

  return (
    <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-8 pb-28 lg:pb-16 animate-in fade-in duration-300">
      
      {/* Header */}
      <div className="text-center max-w-3xl mx-auto mb-10 sm:mb-14">
        <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-[#fdf0f4] dark:bg-pink-950/60 text-[#e11d48] mb-4 shadow-2xs">
          <Heart className="w-7 h-7 fill-[#e11d48]" />
        </div>
        <h1 className="font-serif text-3xl sm:text-4xl lg:text-5xl font-bold text-gray-950 dark:text-white tracking-tight mb-3">
          Give Your Life to Christ
        </h1>
        <p className="text-sm sm:text-base text-gray-600 dark:text-gray-300 leading-relaxed max-w-2xl mx-auto">
          There is no greater decision you can make than to accept Jesus Christ as your Lord and Savior. We invite you to pray the prayer of salvation today.
        </p>
      </div>

      <div className="grid lg:grid-cols-12 gap-8 lg:gap-12 items-center max-w-5xl mx-auto">
        
        {/* Left Side: Video */}
        <div className="lg:col-span-6">
          <div className="bg-white dark:bg-gray-900 p-2 sm:p-3 rounded-3xl shadow-xl border border-gray-100 dark:border-gray-800">
            <div className="bg-black rounded-2xl overflow-hidden aspect-video relative">
              <video controls className="w-full h-full object-cover">
                <source src="https://worshiplyrics.s3.amazonaws.com/filler.mp4" type="video/mp4" />
                Your browser does not support the video tag.
              </video>
            </div>
          </div>
        </div>

        {/* Right Side: Interactive Panel */}
        <div className="lg:col-span-6 relative">
          
          {/* Step 1: Prayer */}
          {step === 1 && (
            <div className="bg-white dark:bg-gray-900 rounded-3xl p-6 sm:p-8 border border-gray-100 dark:border-gray-800 shadow-sm animate-in fade-in duration-300">
              <div className="flex items-center gap-2.5 mb-4">
                <Sparkles className="w-5 h-5 text-[#f59e0b]" />
                <h2 className="font-serif text-2xl font-bold text-gray-950 dark:text-white">The Prayer of Salvation</h2>
              </div>
              
              <div className="relative bg-[#fdf2f8]/50 dark:bg-pink-950/20 rounded-2xl p-5 sm:p-6 border border-pink-100/60 dark:border-pink-900/40 mb-6">
                <p className="relative z-10 text-gray-800 dark:text-gray-200 leading-relaxed text-sm sm:text-base font-medium italic">
                  "O Lord God, I believe with all my heart in Jesus Christ, Son of the living God. I believe He died for me and God raised Him from the dead. I believe He’s alive today. I confess with my mouth that Jesus Christ is the Lord of my life from this day. Through Him and in His Name, I have eternal life; I’m born again. Thank you Lord, for saving my soul! I’m now a child of God. Hallelujah!"
                </p>
              </div>

              <div className="space-y-3">
                <button 
                  onClick={() => setStep(2)}
                  className="w-full py-3.5 px-6 rounded-full bg-[#5b3ee4] hover:bg-[#4f2bee] text-white font-semibold text-sm transition-all shadow-md active:scale-95 flex items-center justify-center gap-2 cursor-pointer"
                >
                  I Prayed This Prayer
                  <Heart className="w-4 h-4 fill-white" />
                </button>
                <p className="text-center text-xs text-gray-400">
                  If you prayed this, we have a special gift book for you!
                </p>
              </div>
            </div>
          )}

          {/* Step 2: Gift Form */}
          {step === 2 && (
            <div className="bg-white dark:bg-gray-900 rounded-3xl p-6 sm:p-8 border border-gray-100 dark:border-gray-800 shadow-sm animate-in fade-in duration-300 text-center">
              <div className="w-14 h-14 bg-amber-50 dark:bg-amber-950/60 text-[#f59e0b] rounded-2xl flex items-center justify-center mx-auto mb-4">
                <Gift className="w-7 h-7" />
              </div>
              <h2 className="font-serif text-2xl font-bold text-gray-950 dark:text-white mb-2">Congratulations!</h2>
              <p className="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mb-6 max-w-sm mx-auto">
                We rejoice with you! Receive your free digital copy of <strong className="text-gray-900 dark:text-white">"Now That You Are Born Again"</strong> to help you in your faith walk.
              </p>

              <form onSubmit={handleSubmit} className="space-y-4 text-left">
                <div>
                  <label className="block text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-1.5">Your Name</label>
                  <input 
                    type="text" 
                    name="name" 
                    required 
                    className="w-full px-4 py-3 rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white text-sm outline-none focus:ring-2 focus:ring-[#5b3ee4]/20 transition-all" 
                    placeholder="E.g. Brother John" 
                  />
                </div>
                
                <div>
                  <label className="block text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-1.5">Your Email</label>
                  <input 
                    type="email" 
                    name="email" 
                    required 
                    className="w-full px-4 py-3 rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white text-sm outline-none focus:ring-2 focus:ring-[#5b3ee4]/20 transition-all" 
                    placeholder="john@example.com" 
                  />
                </div>

                <button 
                  type="submit" 
                  className="w-full py-3.5 px-6 rounded-full bg-[#5b3ee4] hover:bg-[#4f2bee] text-white font-semibold text-sm transition-all shadow-md active:scale-95 cursor-pointer mt-2"
                >
                  Claim Your Free Gift
                </button>
                <button 
                  type="button"
                  onClick={() => setStep(1)}
                  className="w-full py-2 text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors cursor-pointer"
                >
                  ← Go back to prayer
                </button>
              </form>
            </div>
          )}

          {/* Step 3: Success */}
          {step === 3 && (
            <div className="bg-white dark:bg-gray-900 rounded-3xl p-8 sm:p-12 border border-gray-100 dark:border-gray-800 shadow-sm animate-in zoom-in-95 duration-300 text-center">
              <div className="w-16 h-16 bg-emerald-50 dark:bg-emerald-950/60 rounded-full flex items-center justify-center mx-auto mb-4">
                <CheckCircle2 className="w-8 h-8 text-emerald-600" />
              </div>
              <h2 className="font-serif text-3xl font-bold text-gray-950 dark:text-white mb-2">Welcome to the Family!</h2>
              <p className="text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto leading-relaxed">
                Your free gift download is starting. You are now a child of God, filled with His love and purpose!
              </p>
            </div>
          )}

        </div>
      </div>
    </div>
  )
}
