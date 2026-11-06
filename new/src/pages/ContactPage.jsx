import { MapPin, Mail, Clock, Send } from 'lucide-react';

export default function ContactPage() {
  return (
    <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-8 pb-28 lg:pb-16 animate-in fade-in duration-300">
      
      {/* Header */}
      <section className="text-center max-w-3xl mx-auto mb-10 sm:mb-16">
        <h1 className="font-serif text-3xl sm:text-5xl font-bold text-gray-950 dark:text-white mb-3 tracking-tight">
          Contact Us
        </h1>
        <p className="text-sm sm:text-base text-gray-600 dark:text-gray-300 max-w-xl mx-auto leading-relaxed">
          We'd love to hear from you. Send prayer requests, partnership inquiries, or platform questions to our team.
        </p>
      </section>

      {/* Content */}
      <div className="grid lg:grid-cols-5 gap-8 lg:gap-12 items-start max-w-6xl mx-auto">
        
        {/* Info Column */}
        <div className="lg:col-span-2 space-y-6">
          <div className="bg-white dark:bg-gray-900 rounded-3xl p-6 sm:p-8 border border-gray-100 dark:border-gray-800 shadow-sm space-y-6">
            <h2 className="font-serif text-2xl font-bold text-gray-950 dark:text-white">
              Get in Touch
            </h2>
            <p className="text-xs sm:text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
              Our ministry team is here to support you in your spiritual walk and assist with your broadcasting experience.
            </p>
            
            <div className="space-y-4 pt-2">
              <ContactInfoBlock 
                icon={<MapPin className="w-5 h-5" />}
                title="Our Location"
                details={["Loveworld City, Asese", "Ogun State, Nigeria"]}
                colorClass="bg-indigo-50 dark:bg-indigo-950/60 text-[#5b3ee4] dark:text-[#a5b4fc]"
              />
              <ContactInfoBlock 
                icon={<Mail className="w-5 h-5" />}
                title="Email Us"
                details={["support@myvpr.org", "info@myvpr.org"]}
                colorClass="bg-indigo-50 dark:bg-indigo-950/60 text-[#5b3ee4] dark:text-[#a5b4fc]"
              />
              <ContactInfoBlock 
                icon={<Clock className="w-5 h-5" />}
                title="Active Support"
                details={["Mon - Fri: 9am - 5pm", "24/7 during Live Broadcasts"]}
                colorClass="bg-indigo-50 dark:bg-indigo-950/60 text-[#5b3ee4] dark:text-[#a5b4fc]"
              />
            </div>
          </div>
        </div>

        {/* Form Column */}
        <div className="lg:col-span-3">
          <div className="bg-white dark:bg-gray-900 rounded-3xl p-6 sm:p-10 border border-gray-100 dark:border-gray-800 shadow-sm">
            <h2 className="font-serif text-2xl font-bold mb-2 text-gray-950 dark:text-white">Send a Message</h2>
            <p className="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mb-6">Fill in the form below and we will get back to you promptly.</p>
            
            <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); alert('Message sent successfully!'); }}>
              <div className="grid sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-1.5">Your Name</label>
                  <input 
                    type="text" 
                    className="w-full px-4 py-3 rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white text-sm outline-none focus:ring-2 focus:ring-[#5b3ee4]/20 transition-all" 
                    placeholder="Brother Michael" 
                    required 
                  />
                </div>
                <div>
                  <label className="block text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-1.5">Your Email</label>
                  <input 
                    type="email" 
                    className="w-full px-4 py-3 rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white text-sm outline-none focus:ring-2 focus:ring-[#5b3ee4]/20 transition-all" 
                    placeholder="michael@example.com" 
                    required 
                  />
                </div>
              </div>
              
              <div>
                <label className="block text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-1.5">Subject</label>
                <input 
                  type="text" 
                  className="w-full px-4 py-3 rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white text-sm outline-none focus:ring-2 focus:ring-[#5b3ee4]/20 transition-all" 
                  placeholder="Prayer Request / Question" 
                  required 
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-1.5">Message</label>
                <textarea 
                  rows={4} 
                  className="w-full px-4 py-3 rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white text-sm outline-none focus:ring-2 focus:ring-[#5b3ee4]/20 transition-all resize-none" 
                  placeholder="How can we stand in agreement with you?" 
                  required 
                />
              </div>

              <button 
                type="submit" 
                className="w-full py-3.5 px-6 bg-[#5b3ee4] hover:bg-[#4f2bee] text-white rounded-full font-semibold text-sm transition-all shadow-md active:scale-95 flex items-center justify-center gap-2 cursor-pointer mt-2"
              >
                <Send className="w-4 h-4" /> Send Message
              </button>
            </form>
          </div>
        </div>

      </div>

    </div>
  );
}

function ContactInfoBlock({ icon, title, details, colorClass }) {
  return (
    <div className="flex items-start gap-3.5">
      <div className={`p-2.5 rounded-2xl shrink-0 ${colorClass}`}>
        {icon}
      </div>
      <div>
        <h4 className="font-serif font-bold text-sm text-gray-900 dark:text-white mb-0.5">{title}</h4>
        {details.map((d, i) => (
          <p key={i} className="text-xs text-gray-500 dark:text-gray-400 leading-snug">{d}</p>
        ))}
      </div>
    </div>
  );
}
