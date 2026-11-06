import { Users, Globe, Video, MessageCircle, Sparkles } from 'lucide-react';

export default function AboutPage() {
  return (
    <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-8 pb-28 lg:pb-16 animate-in fade-in duration-300">
      
      {/* Header */}
      <section className="text-center max-w-3xl mx-auto mb-12 sm:mb-16">
        <div className="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-[#5b3ee4] dark:text-[#a5b4fc] font-semibold text-xs mb-4">
          <Sparkles className="w-3.5 h-3.5" />
          <span>Our Vision</span>
        </div>
        <h1 className="font-serif text-3xl sm:text-5xl font-bold text-gray-950 dark:text-white mb-3 tracking-tight">
          About Virtual Praise Room
        </h1>
        <p className="text-sm sm:text-base text-gray-600 dark:text-gray-300 leading-relaxed max-w-2xl mx-auto">
          Connecting believers worldwide through non-stop worship, high-definition streaming, and global digital crusades.
        </p>
      </section>

      {/* Content */}
      <section className="max-w-5xl mx-auto space-y-16">
        
        {/* Mission */}
        <div className="grid md:grid-cols-2 gap-8 sm:gap-12 items-center">
          <div>
            <h2 className="font-serif text-3xl font-bold mb-3 text-gray-950 dark:text-white">Our Mission</h2>
            <div className="w-16 h-1 bg-[#5b3ee4] rounded-full mb-5"></div>
            <p className="text-sm sm:text-base text-gray-600 dark:text-gray-300 leading-relaxed">
              Virtual Praise Room (VPR) is a premier digital worship platform dedicated to fostering spiritual connection and global community engagement. Our mission is to provide an intuitive, high-quality environment where individuals, ministries, and faith-based organizations can gather for praise, worship, and teachings without geographical limitations.
            </p>
          </div>
          <div className="relative aspect-[4/3] rounded-3xl overflow-hidden shadow-xl">
            <img src="https://images.unsplash.com/photo-1438232992991-995b7058bbb3?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80" alt="Worship" className="w-full h-full object-cover" />
            <div className="absolute inset-0 bg-gradient-to-t from-gray-950/80 via-transparent to-transparent"></div>
          </div>
        </div>

        {/* Services */}
        <div>
          <div className="text-center max-w-2xl mx-auto mb-10">
            <h2 className="font-serif text-3xl font-bold mb-2 text-gray-950 dark:text-white">Platform Features</h2>
            <p className="text-sm text-gray-500 dark:text-gray-400">Designed intentionally for interactive praise and seamless broadcasting</p>
          </div>
          
          <div className="grid md:grid-cols-2 gap-6">
            <ServiceCard 
              icon={<Users className="w-6 h-6" />}
              color="bg-purple-100 dark:bg-purple-950/60 text-[#5b3ee4] dark:text-[#a5b4fc]"
              title="Virtual Praise Rooms"
              description="Host and participate in real-time video worship sessions with reactions, prayer requests, and live praise along with thousands."
            />
            <ServiceCard 
              icon={<Globe className="w-6 h-6" />}
              color="bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400"
              title="Digital Crusades"
              description="Organize and schedule digital crusades that bring your church, cell group, or ministry together from any device."
            />
            <ServiceCard 
              icon={<Video className="w-6 h-6" />}
              color="bg-teal-100 dark:bg-teal-950/60 text-teal-700 dark:text-teal-400"
              title="Video On Demand"
              description="Access a rich catalog of recorded teachings, worship nights, children's praise, and ministrations in high definition."
            />
            <ServiceCard 
              icon={<MessageCircle className="w-6 h-6" />}
              color="bg-pink-100 dark:bg-pink-950/60 text-[#e11d48] dark:text-pink-400"
              title="Browser Studio"
              description="Go live directly from your web browser with camera and microphone, or connect professional software like OBS and vMix."
            />
          </div>
        </div>

      </section>
    </div>
  );
}

function ServiceCard({ icon, color, title, description }) {
  return (
    <div className="bg-white dark:bg-gray-900 p-6 sm:p-8 rounded-3xl border border-gray-100 dark:border-gray-800 shadow-sm hover:shadow-md transition-all">
      <div className={`w-12 h-12 rounded-2xl ${color} flex items-center justify-center mb-5`}>
        {icon}
      </div>
      <h3 className="font-serif text-xl font-bold text-gray-950 dark:text-white mb-2">{title}</h3>
      <p className="text-gray-600 dark:text-gray-400 leading-relaxed text-sm">
        {description}
      </p>
    </div>
  );
}
