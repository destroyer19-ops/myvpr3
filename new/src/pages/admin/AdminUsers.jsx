import { useState, useEffect } from 'react';
import { Loader2, Search, Edit2, ShieldAlert } from 'lucide-react';
import { adminApi } from '../../services/api';

export default function AdminUsers() {
  const [loading, setLoading] = useState(true);
  const [users, setUsers] = useState([]);
  const [error, setError] = useState('');
  const [searchTerm, setSearchTerm] = useState('');

  useEffect(() => {
    async function fetchUsers() {
      try {
        const res = await adminApi.listUsers();
        if (res.success) {
          setUsers(res.data);
        } else {
          setError(res.message);
        }
      } catch (err) {
        setError('Failed to fetch users.');
      } finally {
        setLoading(false);
      }
    }
    fetchUsers();
  }, []);

  const filteredUsers = users.filter(user => 
    user.username?.toLowerCase().includes(searchTerm.toLowerCase()) || 
    user.email?.toLowerCase().includes(searchTerm.toLowerCase())
  );

  return (
    <div className="container max-w-7xl mx-auto px-4 py-8">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold mb-1 text-gray-950 dark:text-white">Manage Users</h1>
          <p className="text-gray-500 dark:text-gray-400 text-sm">View and manage registered accounts.</p>
        </div>
        
        <div className="relative w-full md:w-64">
          <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
          <input 
            type="text" 
            placeholder="Search users..." 
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full pl-9 pr-4 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>
      </div>

      {error && (
        <div className="bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900/50 text-red-600 dark:text-red-400 p-4 rounded-lg mb-6 flex items-center gap-2">
          <ShieldAlert className="w-5 h-5" />
          {error}
        </div>
      )}

      {loading ? (
        <div className="flex justify-center p-12">
          <Loader2 className="w-8 h-8 animate-spin text-blue-600" />
        </div>
      ) : (
        <div className="bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                <tr>
                  <th className="p-4 font-medium text-gray-500 dark:text-gray-400">Username</th>
                  <th className="p-4 font-medium text-gray-500 dark:text-gray-400">Email</th>
                  <th className="p-4 font-medium text-gray-500 dark:text-gray-400">Role</th>
                  <th className="p-4 font-medium text-gray-500 dark:text-gray-400">Subscription</th>
                  <th className="p-4 font-medium text-gray-500 dark:text-gray-400">Joined</th>
                  <th className="p-4 font-medium text-gray-500 dark:text-gray-400 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                {filteredUsers.length > 0 ? (
                  filteredUsers.map((user) => (
                    <tr key={user.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                      <td className="p-4 font-medium text-gray-900 dark:text-white">
                        {user.username}
                      </td>
                      <td className="p-4 text-gray-500 dark:text-gray-400">{user.email || '-'}</td>
                      <td className="p-4">
                        <span className="capitalize text-gray-700 dark:text-gray-300">
                          {user.account_type || 'User'}
                        </span>
                      </td>
                      <td className="p-4">
                        {user.subscription_status === 'active' ? (
                          <span className="bg-green-100 dark:bg-green-950/60 text-green-700 dark:text-green-300 px-2 py-0.5 rounded text-xs font-medium">Active</span>
                        ) : (
                          <span className="bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 px-2 py-0.5 rounded text-xs font-medium">Inactive</span>
                        )}
                      </td>
                      <td className="p-4 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                        {new Date(user.created_at).toLocaleDateString()}
                      </td>
                      <td className="p-4 text-right">
                        <button className="p-1.5 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 rounded transition-colors" title="Edit User">
                          <Edit2 className="w-4 h-4" />
                        </button>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan="6" className="p-8 text-center text-gray-500 dark:text-gray-400">
                      No users found.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
}
