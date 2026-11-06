import React, { useState } from 'react';
import { useAuth } from '@/context/AuthContext';
import { Button, Input, Label } from '@/components/ui/FormElements';

export default function UsernameUpdateModal() {
  const { user, refreshUser } = useAuth();
  const [newUsername, setNewUsername] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  // If we don't have a user or they don't require an update, render nothing
  if (!user || !user.requires_username_update) {
    return null;
  }

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    
    // Client side regex matching the backend
    if (!/^[a-zA-Z0-9_]+$/.test(newUsername)) {
      setError('Username can only contain letters, numbers, and underscores.');
      return;
    }

    setLoading(true);
    try {
      const response = await fetch('/api/user/update-username', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username: newUsername })
      });
      const data = await response.json();
      
      if (data.success) {
        // Refresh the user context so the modal disappears
        await refreshUser();
      } else {
        setError(data.message || 'Failed to update username.');
      }
    } catch (err) {
      setError('An error occurred. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 dark:bg-black/80 backdrop-blur-sm p-4 animate-in fade-in">
      <div className="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-6 sm:p-8 rounded-3xl shadow-2xl max-w-md w-full">
        <h2 className="font-serif text-2xl font-bold text-gray-950 dark:text-white mb-2">Update Required</h2>
        <p className="text-gray-600 dark:text-gray-400 mb-6 text-sm leading-relaxed">
          Your current username contains invalid characters. To continue using the platform and enable streaming features, please choose a new username containing only letters, numbers, and underscores.
        </p>
        
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <Label htmlFor="username">New Username</Label>
            <Input 
              id="username"
              type="text" 
              value={newUsername} 
              onChange={(e) => setNewUsername(e.target.value)}
              placeholder="e.g. john_doe"
              required
            />
          </div>
          
          {error && <p className="text-red-500 text-xs font-semibold">{error}</p>}
          
          <Button type="submit" className="w-full" disabled={loading}>
            {loading ? 'Updating...' : 'Update Username'}
          </Button>
        </form>
      </div>
    </div>
  );
}
