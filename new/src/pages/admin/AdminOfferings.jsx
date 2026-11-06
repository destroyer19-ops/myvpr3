import { useState, useEffect } from 'react'
import { Eye, Download, CheckCircle, XCircle } from 'lucide-react'

export default function AdminOfferings() {
  const [offerings, setOfferings] = useState([])

  useEffect(() => {
    // In a real app, this would fetch from adminApi.listOfferings()
    setOfferings([
      { id: 1, name: 'John Doe', amount: 50.00, status: 'pending', date: '2026-09-26', proofUrl: '#' },
      { id: 2, name: 'Jane Smith', amount: 150.00, status: 'approved', date: '2026-09-25', proofUrl: '#' },
      { id: 3, name: 'David Johnson', amount: 20.00, status: 'pending', date: '2026-09-26', proofUrl: '#' }
    ])
  }, [])

  const handleApprove = (id) => {
    setOfferings(prev => prev.map(o => o.id === id ? { ...o, status: 'approved' } : o))
  }

  const handleReject = (id) => {
    setOfferings(prev => prev.map(o => o.id === id ? { ...o, status: 'rejected' } : o))
  }

  return (
    <div className="container max-w-7xl mx-auto px-4 py-8">
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-3xl font-bold text-gray-950 dark:text-white">Offerings & Partnerships</h1>
          <p className="text-gray-500 dark:text-gray-400 mt-1">Review and manage uploaded proofs of payment.</p>
        </div>
      </div>

      <div className="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="bg-gray-50 dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
                <th className="p-4 font-semibold text-sm text-gray-600 dark:text-gray-400">Date</th>
                <th className="p-4 font-semibold text-sm text-gray-600 dark:text-gray-400">Name</th>
                <th className="p-4 font-semibold text-sm text-gray-600 dark:text-gray-400">Amount</th>
                <th className="p-4 font-semibold text-sm text-gray-600 dark:text-gray-400">Status</th>
                <th className="p-4 font-semibold text-sm text-gray-600 dark:text-gray-400">Proof</th>
                <th className="p-4 font-semibold text-sm text-gray-600 dark:text-gray-400 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
              {offerings.map(offering => (
                <tr key={offering.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                  <td className="p-4 text-sm text-gray-600 dark:text-gray-400">
                    {offering.date}
                  </td>
                  <td className="p-4 font-medium text-gray-900 dark:text-white">
                    {offering.name}
                  </td>
                  <td className="p-4 text-sm font-semibold text-gray-700 dark:text-gray-300">
                    {offering.amount.toFixed(2)} Espees
                  </td>
                  <td className="p-4">
                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                      offering.status === 'approved' ? 'bg-green-100 dark:bg-green-950/60 text-green-800 dark:text-green-300' :
                      offering.status === 'rejected' ? 'bg-red-100 dark:bg-red-950/60 text-red-800 dark:text-red-300' :
                      'bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300'
                    }`}>
                      {offering.status.charAt(0).toUpperCase() + offering.status.slice(1)}
                    </span>
                  </td>
                  <td className="p-4">
                    <button className="flex items-center gap-1 text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors">
                      <Eye className="w-4 h-4" /> View
                    </button>
                  </td>
                  <td className="p-4 text-right space-x-2">
                    {offering.status === 'pending' && (
                      <>
                        <button 
                          onClick={() => handleApprove(offering.id)}
                          className="p-1.5 text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-950/40 hover:bg-green-100 dark:hover:bg-green-900/50 rounded-lg transition-colors"
                          title="Approve"
                        >
                          <CheckCircle className="w-5 h-5" />
                        </button>
                        <button 
                          onClick={() => handleReject(offering.id)}
                          className="p-1.5 text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/40 hover:bg-red-100 dark:hover:bg-red-900/50 rounded-lg transition-colors"
                          title="Reject"
                        >
                          <XCircle className="w-5 h-5" />
                        </button>
                      </>
                    )}
                  </td>
                </tr>
              ))}
              {offerings.length === 0 && (
                <tr>
                  <td colSpan="6" className="p-8 text-center text-gray-500">
                    No offerings found.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}
