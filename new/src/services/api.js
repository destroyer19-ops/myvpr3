/**
 * api.js
 * Central API client. All requests to the PHP backend go through here.
 * The Vite dev proxy rewrites /api → http://localhost:8000/api
 */

const BASE_URL = '/api/index.php'

let csrfToken = null;

/**
 * Core fetch wrapper — handles JSON, errors, and credentials (session cookies).
 */
export async function request(endpoint, options = {}) {
  const url = `${BASE_URL}${endpoint}`

  const config = {
    credentials: 'include', // send session cookies with every request
    headers: {
      'Content-Type': 'application/json',
      ...(options.headers || {}),
    },
    ...options,
  }

  // Handle CSRF Token automatically for state-changing requests
  const method = config.method || 'GET';
  if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method.toUpperCase())) {
    if (!csrfToken) {
      const csrfRes = await fetch(`${BASE_URL}/csrf`, { credentials: 'include' });
      const csrfData = await csrfRes.json();
      if (csrfData.success) {
        csrfToken = csrfData.csrf_token;
      }
    }
    if (csrfToken) {
      config.headers['X-CSRF-Token'] = csrfToken;
    }
  }

  // Remove Content-Type for FormData (browser sets it with boundary)
  if (config.body instanceof FormData) {
    delete config.headers['Content-Type']
  }

  const res = await fetch(url, config)

  // Try to parse JSON regardless of status
  let data
  try {
    data = await res.json()
  } catch {
    data = { success: false, message: 'Unexpected server response.' }
  }

  if (!res.ok) {
    const err = new Error(data?.message || `HTTP ${res.status}`)
    err.status = res.status
    err.data = data
    throw err
  }

  return data
}

// ─── Auth ─────────────────────────────────────────────────────────────────
export const authApi = {
  getCsrf: () => request('/csrf'),
  login: (body) => request('/auth/login', { method: 'POST', body: JSON.stringify(body) }),
  register: (body) => request('/auth/register', { method: 'POST', body: JSON.stringify(body) }),
  logout: () => request('/auth/logout', { method: 'POST' }),
  forgotPassword: (body) => request('/auth/forgot', { method: 'POST', body: JSON.stringify(body) }),
  resetPassword: (body) => request('/auth/reset', { method: 'POST', body: JSON.stringify(body) }),
  kingschatStart: (redirect) => request(`/auth/kingschat/start${redirect ? `?redirect=${encodeURIComponent(redirect)}` : ''}`),
}

// ─── User / Profile ───────────────────────────────────────────────────────
export const userApi = {
  getProfile: () => request('/user/profile'),
  updateProfile: (body) => request('/user/profile', { method: 'PUT', body: JSON.stringify(body) }),
  updateUsername: (body) => request('/user/update-username', { method: 'POST', body: JSON.stringify(body) }),
  submitManualSubscription: (body) => request('/subscriptions/manual', { method: 'POST', body }),
}

// ─── Videos (VOD) ────────────────────────────────────────────────────────
export const videosApi = {
  list: (params = {}) => {
    const qs = new URLSearchParams(params).toString()
    return request(`/videos${qs ? `?${qs}` : ''}`)
  },
  getById: (id) => request(`/videos/${id}`),
  getLanguages: () => request('/videos/languages'),
}

// ─── Crusades ─────────────────────────────────────────────────────────────
export const crusadesApi = {
  list: () => request('/crusades?mine=true'),
  getById: (id) => request(`/crusades/${id}`),
  create: (body) => request('/crusades', { method: 'POST', body: body instanceof FormData ? body : JSON.stringify(body) }),
  end: (id) => request(`/crusades/${id}/end`, { method: 'POST' }),
  delete: (id) => request(`/crusades/${id}`, { method: 'DELETE' }),
  getAnalytics: (code) => request(`/crusades/${code}/analytics`),
  identify: (code, body) => request(`/crusades/${code}/track/identify`, { method: 'PATCH', body: JSON.stringify(body) }),
}

// ─── Meetings ─────────────────────────────────────────────────────────────
export const meetingsApi = {
  list: () => request('/meetings'),
  getByCode: (code) => request(`/meetings/${code}`),
  create: (body) => request('/meetings', { method: 'POST', body: JSON.stringify(body) }),
  start: (id) => request('/meetings/start', { method: 'POST', body: JSON.stringify({ id }) }),
  end: (id) => request('/meetings/end', { method: 'POST', body: JSON.stringify({ id }) }),
}

// ─── Live Streams ─────────────────────────────────────────────────────────
export const streamsApi = {
  getLive: () => request('/streams/live'),
}

// ─── Programs & Recordings (CMS) ──────────────────────────────────────────
export const programsApi = {
  getPrograms: () => request('/programs'),
  getRecording: (id) => request(`/recordings/${id}`),
}

// ─── Continue Watching / Watch History ────────────────────────────────────
export const watchHistoryApi = {
  getContinueWatching: () => request('/user/continue-watching'),
  saveProgress: (data) =>
    request('/watch-history', { method: 'POST', body: JSON.stringify(data) }),
}

// ─── Comments ─────────────────────────────────────────────────────────────
export const commentsApi = {
  list: (params = {}) => {
    const qs = new URLSearchParams(params).toString()
    return request(`/comments${qs ? `?${qs}` : ''}`)
  },
  post: (body) => request('/comments', { method: 'POST', body: JSON.stringify(body) }),
}

// ─── CMS (Banners & Notifications) ───────────────────────────────────────
// NOTE: These endpoints need to be created in the PHP API (see API.md)
export const cmsApi = {
  getBanners: (placement) => request(`/cms/banners?placement=${placement}`),
  getNotifications: () => request('/cms/notifications'),
  markNotificationRead: (id) =>
    request(`/cms/notifications/${id}/read`, { method: 'POST' }),
}

// ─── Subscriptions ────────────────────────────────────────────────────────
export const subscriptionsApi = {
  getPlans: () => request('/subscriptions/plans'),
  getStatus: () => request('/user/subscription'),
  initiateSquadPayment: (body) => request('/subscriptions/squad/initiate', { method: 'POST', body: JSON.stringify(body) }),
  verifySquadPayment: (reference) => request(`/subscriptions/squad/verify/${reference}`),
  submitManualSubscription: (body) => request('/subscriptions/manual', { method: 'POST', body }),
  giftSubscription: (body) =>
    request('/subscriptions/gift', { method: 'POST', body: JSON.stringify(body) }),
}

// ─── Admin ────────────────────────────────────────────────────────────────
export const adminApi = {
  // Videos
  listVideos: () => request('/admin/videos'),
  createVideo: (body) => request('/admin/videos', { method: 'POST', body: body instanceof FormData ? body : JSON.stringify(body) }),
  updateVideo: (id, body) =>
    request(`/admin/videos/${id}`, { method: 'PUT', body: JSON.stringify(body) }),
  deleteVideo: (id) => request(`/admin/videos/${id}`, { method: 'DELETE' }),

  // Recorded Programs CMS
  listRecordings: () => request('/admin/recordings'),
  createRecording: (body) =>
    request('/admin/recordings', { method: 'POST', body: body instanceof FormData ? body : JSON.stringify(body) }),
  updateRecording: (id, body) =>
    request(`/admin/recordings/${id}`, { method: 'POST', body: body instanceof FormData ? body : JSON.stringify(body) }),
  deleteRecording: (id) => request(`/admin/recordings/${id}`, { method: 'DELETE' }),
  listPrograms: () => request('/admin/programs'),
  createProgram: (body) => request('/admin/programs', { method: 'POST', body: JSON.stringify(body) }),
  setProgramLive: (id, isLive) =>
    request(`/admin/programs/${id}/set-live`, { method: 'POST', body: JSON.stringify({ is_live: isLive ? 1 : 0 }) }),
  uploadVideoFile: (file, onProgress) => {
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      const formData = new FormData();
      formData.append('video', file);

      xhr.open('POST', `${BASE_URL}/admin/upload/video`);
      xhr.withCredentials = true;

      if (xhr.upload && onProgress) {
        xhr.upload.onprogress = (e) => {
          if (e.lengthComputable) {
            const percent = Math.round((e.loaded / e.total) * 100);
            onProgress(percent);
          }
        };
      }

      xhr.onload = () => {
        try {
          const res = JSON.parse(xhr.responseText);
          if (xhr.status >= 200 && xhr.status < 300 && res.success) {
            resolve(res);
          } else {
            reject(new Error(res.message || `Upload failed with status ${xhr.status}`));
          }
        } catch {
          reject(new Error('Invalid server response during video upload'));
        }
      };

      xhr.onerror = () => reject(new Error('Network error during video upload'));
      xhr.send(formData);
    });
  },

  // S3 Direct Upload
  getS3Config: () => request('/admin/upload/s3-config'),
  getS3PresignedUrl: (params) => {
    const qs = new URLSearchParams(params).toString();
    return request(`/admin/upload/s3-presigned?${qs}`);
  },
  uploadDirectToS3: (presignedUrl, file, onProgress) => {
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      xhr.open('PUT', presignedUrl);
      xhr.setRequestHeader('Content-Type', file.type || 'application/octet-stream');

      if (xhr.upload && onProgress) {
        xhr.upload.onprogress = (e) => {
          if (e.lengthComputable) {
            const percent = Math.round((e.loaded / e.total) * 100);
            onProgress(percent);
          }
        };
      }

      xhr.onload = () => {
        if (xhr.status >= 200 && xhr.status < 300) {
          resolve({ success: true, status: xhr.status });
        } else {
          reject(new Error(`S3 upload failed with HTTP status ${xhr.status} (${xhr.statusText || 'Upload error'})`));
        }
      };

      xhr.onerror = () => reject(new Error('Network error during S3 upload. Check S3 CORS configuration.'));
      xhr.send(file);
    });
  },

  // Live Streams
  listStreams: () => request('/admin/streams'),
  createStream: (body) => request('/admin/streams', { method: 'POST', body: JSON.stringify(body) }),
  updateStream: (id, body) =>
    request(`/admin/streams/${id}`, { method: 'PUT', body: JSON.stringify(body) }),
  deleteStream: (id) => request(`/admin/streams/${id}`, { method: 'DELETE' }),

  // Users
  listUsers: () => request('/admin/users'),
  resetUserPassword: (id, body) =>
    request(`/admin/users/${id}/reset-password`, { method: 'POST', body: JSON.stringify(body) }),

  // Subscriptions & Payments management
  listSubscriptions: () => request('/admin/subscriptions'),
  approveSubscription: (id) =>
    request(`/admin/subscriptions/${id}/approve`, { method: 'POST' }),
  rejectSubscription: (id) =>
    request(`/admin/subscriptions/${id}/reject`, { method: 'POST' }),
  cancelSubscription: (id, body = {}) =>
    request(`/admin/subscriptions/${id}/cancel`, { method: 'POST', body: JSON.stringify(body) }),
  getPaymentOverview: () => request('/admin/payments/overview'),
  listTransactions: (params = {}) => {
    const qs = new URLSearchParams(params).toString();
    return request(`/admin/payments/transactions${qs ? `?${qs}` : ''}`);
  },
  getTransactionAudit: (id) => request(`/admin/payments/transactions/${id}/audit`),
  getPaymentConfig: () => request('/admin/payments/config'),
  listSubscriptionPlans: () => request('/admin/subscription-plans'),
  createSubscriptionPlan: (body) =>
    request('/admin/subscription-plans', { method: 'POST', body: JSON.stringify(body) }),
  updateSubscriptionPlan: (id, body) =>
    request(`/admin/subscription-plans/${id}`, { method: 'PUT', body: JSON.stringify(body) }),
  toggleSubscriptionPlan: (id) =>
    request(`/admin/subscription-plans/${id}/toggle`, { method: 'POST' }),


  // CMS
  listBanners: () => request('/admin/cms/banners'),
  createBanner: (body) =>
    request('/admin/cms/banners', { method: 'POST', body: JSON.stringify(body) }),
  updateBanner: (id, body) =>
    request(`/admin/cms/banners/${id}`, { method: 'PUT', body: JSON.stringify(body) }),
  deleteBanner: (id) => request(`/admin/cms/banners/${id}`, { method: 'DELETE' }),

  listNotifications: () => request('/admin/cms/notifications'),
  createNotification: (body) =>
    request('/admin/cms/notifications', { method: 'POST', body: JSON.stringify(body) }),
  deleteNotification: (id) => request(`/admin/cms/notifications/${id}`, { method: 'DELETE' }),

  // Stats
  getStats: () => request('/admin/stats'),

  // Salvation decisions
  listSalvationDecisions: () => request('/admin/salvations'),
}

// ─── Testimonies ──────────────────────────────────────────────────────────
export const testimoniesApi = {
  list: () => request('/testimonies'),
  submit: (body) => request('/testimonies', { method: 'POST', body: JSON.stringify(body) }),
  // Admin
  adminList: () => request('/admin/testimonies'),
  updateStatus: (id, status) => request(`/admin/testimonies/${id}`, { method: 'PATCH', body: JSON.stringify({ status }) }),
}

// ─── Metadata (Zones, Countries) ─────────────────────────────────────────
export const metadataApi = {
  getZones: () => request('/metadata/zones'),
  getCountries: () => request('/metadata/countries'),
}
