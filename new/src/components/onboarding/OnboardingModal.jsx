import { useState, useEffect } from 'react';
import { Sparkles, Check, ChevronRight, X, Play, Video, Tv, Radio } from 'lucide-react';

export default function OnboardingModal({ user }) {
  const [isOpen, setIsOpen] = useState(false);
  const [step, setStep] = useState(0);
  const [interests, setInterests] = useState([]);

  useEffect(() => {
    // Check if they have seen the onboarding
    const hasSeen = localStorage.getItem('has_completed_onboarding_v1');
    if (!hasSeen) {
      // Small delay for better UX
      const timer = setTimeout(() => setIsOpen(true), 1000);
      return () => clearTimeout(timer);
    }
  }, []);

  const closeAndSave = () => {
    localStorage.setItem('has_completed_onboarding_v1', 'true');
    setIsOpen(false);
    // Ideally, here we would also fire an API call to save `interests` if backend supported it.
    // userApi.updatePreferences({ interests }).catch(() => {});
  };

  const handleInterestToggle = (interest) => {
    setInterests(prev => 
      prev.includes(interest) 
        ? prev.filter(i => i !== interest)
        : [...prev, interest]
    );
  };

  if (!isOpen) return null;

  const steps = [
    {
      id: 'welcome',
      title: 'Welcome to the New Experience',
      content: (
        <div className="text-center">
          <div className="w-20 h-20 bg-blue-50 dark:bg-blue-950/40 rounded-full flex items-center justify-center mx-auto mb-6">
            <Sparkles className="w-10 h-10 text-[var(--color-brand-accent)]" />
          </div>
          <h2 className="text-2xl font-bold mb-4 text-gray-950 dark:text-white">
            Hello, {user?.name ? user.name.split(' ')[0] : 'there'}
          </h2>
          <p className="text-gray-500 dark:text-gray-400 mb-8 max-w-sm mx-auto">
            We've completely redesigned the platform to make discovering and sharing premium content easier than ever.
          </p>
        </div>
      )
    },
    {
      id: 'interests',
      title: 'What brings you here?',
      content: (
        <div>
          <h2 className="text-2xl font-bold mb-2 text-center text-gray-950 dark:text-white">Personalize your feed</h2>
          <p className="text-gray-500 dark:text-gray-400 text-center mb-6">Select the topics you're most interested in.</p>
          
          <div className="grid grid-cols-2 gap-3 mb-8">
            {[
              { id: 'broadcasting', label: 'Broadcasting', icon: Radio },
              { id: 'movies', label: 'Movies & TV', icon: Tv },
              { id: 'ministrations', label: 'Ministrations', icon: Play },
              { id: 'meetings', label: 'Hosting Meetings', icon: Video },
            ].map(item => {
              const Icon = item.icon || Check;
              const isSelected = interests.includes(item.id);
              return (
                <button
                  key={item.id}
                  onClick={() => handleInterestToggle(item.id)}
                  className={`p-4 rounded-xl border-2 text-left flex flex-col items-center gap-2 transition-all ${
                    isSelected 
                      ? 'border-[var(--color-brand-accent)] bg-blue-50 dark:bg-blue-950/40' 
                      : 'border-gray-100 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700 bg-white dark:bg-gray-800'
                  }`}
                >
                  <Icon className={`w-6 h-6 ${isSelected ? 'text-[var(--color-brand-accent)]' : 'text-gray-400'}`} />
                  <span className={`text-sm font-semibold ${isSelected ? 'text-gray-900 dark:text-white' : 'text-gray-600 dark:text-gray-300'}`}>
                    {item.label}
                  </span>
                </button>
              );
            })}
          </div>
        </div>
      )
    },
    {
      id: 'ready',
      title: 'You\'re all set',
      content: (
        <div className="text-center">
          <div className="w-20 h-20 bg-green-50 dark:bg-green-950/40 rounded-full flex items-center justify-center mx-auto mb-6">
            <Check className="w-10 h-10 text-green-500" />
          </div>
          <h2 className="text-2xl font-bold mb-4 text-gray-950 dark:text-white">
            Your platform is ready
          </h2>
          <p className="text-gray-500 dark:text-gray-400 mb-8 max-w-sm mx-auto">
            Explore the new library, join live crusades, or start broadcasting your own content to the world.
          </p>
        </div>
      )
    }
  ];

  const currentStep = steps[step];

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in duration-300">
      <div className="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 w-full max-w-md rounded-3xl shadow-2xl overflow-hidden relative animate-in zoom-in-95 duration-500">
        
        {/* Close Button */}
        <button 
          onClick={closeAndSave}
          className="absolute top-4 right-4 p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 bg-gray-100 dark:bg-gray-800 rounded-full transition-colors"
        >
          <X className="w-5 h-5" />
        </button>

        {/* Content */}
        <div className="p-8 pb-6">
          {currentStep.content}
        </div>

        {/* Footer */}
        <div className="p-6 pt-0 flex flex-col gap-4">
          <div className="flex justify-center gap-2 mb-2">
            {steps.map((_, i) => (
              <div 
                key={i} 
                className={`h-1.5 rounded-full transition-all duration-300 ${i === step ? 'w-8 bg-[var(--color-brand-accent)]' : 'w-2 bg-gray-200 dark:bg-gray-700'}`} 
              />
            ))}
          </div>
          
          <button
            onClick={() => {
              if (step < steps.length - 1) {
                setStep(s => s + 1);
              } else {
                closeAndSave();
              }
            }}
            className="w-full bg-[var(--color-brand-primary)] text-white py-3.5 rounded-xl font-bold flex items-center justify-center gap-2 hover:opacity-90 transition-opacity"
          >
            {step < steps.length - 1 ? (
              <>Continue <ChevronRight className="w-5 h-5" /></>
            ) : (
              'Start Exploring'
            )}
          </button>
        </div>

      </div>
    </div>
  );
}
