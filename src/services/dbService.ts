import { Place, Comment } from '../types';

// Detect whether we are currently using fallback demo data
export const isDatabaseDemoMode = (): boolean => {
  return false;
};

// Helper to resolve clean URL path for api.php (loads from persisted settings in localStorage if present, or defaults to local proxy)
export const getApiUrl = (): string => {
  if (typeof window !== 'undefined') {
    const customUrl = localStorage.getItem('angstria_api_url');
    if (customUrl && customUrl.trim()) {
      return customUrl.trim();
    }
  }
  return '/api.php';
};

// Set and persist custom api.php url (useful for cross-device/public preview environments)
export const setApiUrl = (url: string) => {
  if (typeof window !== 'undefined') {
    if (url && url.trim()) {
      localStorage.setItem('angstria_api_url', url.trim());
    } else {
      localStorage.removeItem('angstria_api_url');
    }
    window.dispatchEvent(new Event('angstria-api-url-changed'));
  }
};

const normalizePlace = (p: any): Place => {
  if (!p) return p;
  
  let facilities: string[] = [];
  if (Array.isArray(p.facilities)) {
    facilities = p.facilities;
  } else if (typeof p.facilities === 'string') {
    facilities = p.facilities ? p.facilities.split(',').map((f: string) => f.trim()) : [];
  }

  let tags: string[] = [];
  if (Array.isArray(p.tags)) {
    tags = p.tags;
  } else if (typeof p.tags === 'string') {
    tags = p.tags ? p.tags.split(',').map((t: string) => t.trim()) : [];
  }

  let images: string[] = [];
  if (Array.isArray(p.images)) {
    images = p.images;
  } else if (typeof p.images === 'string') {
    try {
      const parsed = JSON.parse(p.images);
      images = Array.isArray(parsed) ? parsed : [];
    } catch {
      images = p.image ? [p.image] : [];
    }
  } else {
    images = p.image ? [p.image] : [];
  }

  let socials = p.socials;
  if (typeof p.socials === 'string') {
    try {
      socials = JSON.parse(p.socials);
    } catch {
      socials = undefined;
    }
  } else if (!p.socials && (p.instagram || p.website || p.tiktok)) {
    socials = {
      instagram: p.instagram || undefined,
      website: p.website || undefined,
      tiktok: p.tiktok || undefined
    };
  }

  return {
    ...p,
    id: p.id || '',
    name: p.name || '',
    description: p.description || '',
    location: p.location || '',
    facilities,
    tags,
    images: images.length > 0 ? images : (p.image ? [p.image] : []),
    featured: p.featured === true || p.featured === 1 || p.featured === '1' || p.featured === 'true',
    views: typeof p.views === 'number' ? p.views : parseInt(p.views || '0', 10),
    rating: typeof p.rating === 'number' ? p.rating : parseFloat(p.rating || '0'),
    lat: p.lat !== null && p.lat !== undefined ? parseFloat(p.lat) : null,
    lng: p.lng !== null && p.lng !== undefined ? parseFloat(p.lng) : null,
    socials: socials || undefined
  };
};

export const placesService = {
  // 1. Ambil semua kafe dari MySQL phpMyAdmin
  async getAllPlaces(): Promise<Place[]> {
    const API_BASE_URL = getApiUrl();
    const response = await fetch(`${API_BASE_URL}?action=places`);
    if (!response.ok) throw new Error('Query server database gagal.');

    const text = await response.text();
    if (text.trim().startsWith('<?php') || text.includes('<?php')) {
      throw new Error('Server mengembalikan kode PHP mentah. Diperlukan server PHP.');
    }

    const data = JSON.parse(text);

    if (data && data.success === true && Array.isArray(data.data)) {
      return data.data.map(normalizePlace);
    }
    if (Array.isArray(data)) {
      return data.map(normalizePlace);
    }
    throw new Error(data.message || 'Data tidak sesuai format');
  },

  // 2. Ambil kafe unggulan
  async getFeaturedPlaces(): Promise<Place[]> {
    const places = await this.getAllPlaces();
    return places.filter(p => p.featured);
  },

  // 3. Ambil data kafe spesifik berdasarkan ID
  async getPlaceById(id: string): Promise<(Place & { comments?: Comment[] }) | null> {
    const API_BASE_URL = getApiUrl();
    const response = await fetch(`${API_BASE_URL}?action=place&id=${id}`);
    if (!response.ok) throw new Error('Gagal memuat detail.');

    const text = await response.text();
    if (text.trim().startsWith('<?php') || text.includes('<?php')) {
      throw new Error('Kode PHP mentah.');
    }

    const data = JSON.parse(text);

    if (data && data.success === true && data.data) {
      return {
        ...normalizePlace(data.data),
        comments: Array.isArray(data.comments) ? data.comments : []
      };
    }
    if (data && data.name) {
      return {
        ...normalizePlace(data),
        comments: Array.isArray(data.comments) ? data.comments : []
      };
    }
    return null;
  },

  // 4. Tambah kafe baru (Dashboard Admin)
  async createPlace(place: Omit<Place, 'id' | 'views' | 'rating' | 'createdAt'>): Promise<string | null> {
    const API_BASE_URL = getApiUrl();
    let generatedId = 'place-' + Math.random().toString(36).substr(2, 9);
    if (place.name) {
      const slug = place.name
        .toString()
        .toLowerCase()
        .trim()
        .replace(/\s+/g, '-')
        .replace(/[^\w\-]+/g, '')
        .replace(/\-\-+/g, '-')
        .substring(0, 42);
      const suffix = Math.random().toString(36).substr(2, 4);
      generatedId = `${slug}-${suffix}`;
    }
    const payload = {
      id: generatedId,
      views: 0,
      rating: 0,
      ...place
    };

    const response = await fetch(`${API_BASE_URL}?action=create_place`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const result = await response.json();
    if (result && (result.success === true || result.status === 'success')) {
      return result.id || generatedId;
    }
    throw new Error(result.message || 'Gagal menyimpan ke phpMyAdmin');
  },

  // 5. Perbarui data kafe
  async updatePlace(id: string, updates: Partial<Place>): Promise<void> {
    const API_BASE_URL = getApiUrl();
    const response = await fetch(`${API_BASE_URL}?action=update_place`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id, ...updates })
    });
    const result = await response.json();
    if (result && result.success !== true && result.status !== 'success') {
      throw new Error(result.message || 'Gagal memperbarui data kafe');
    }
  },

  // 6. Hapus kafe
  async deletePlace(id: string): Promise<void> {
    const API_BASE_URL = getApiUrl();
    const response = await fetch(`${API_BASE_URL}?action=delete_place&id=${id}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    });
    const result = await response.json();
    if (result && result.success !== true && result.status !== 'success') {
      throw new Error(result.message || 'Gagal menghapus kafe');
    }
  },

  // 7. Tambahkan jumlah kunjungan / views
  async incrementViews(id: string): Promise<void> {
    const API_BASE_URL = getApiUrl();
    await fetch(`${API_BASE_URL}?action=increment_views&id=${id}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' }
    });
  },

  // 8. Reset database
  async resetDatabase(): Promise<void> {
    const API_BASE_URL = getApiUrl();
    const response = await fetch(`${API_BASE_URL}?action=reset_db`, {
      method: 'POST'
    });
    const result = await response.json();
    if (result && result.success !== true) {
      throw new Error(result.message || 'Gagal mereset database phpMyAdmin');
    }
  }
};

export const commentsService = {
  // 1. Ambil ulasan berdasarkan ID Kafe
  async getCommentsByPlaceId(placeId: string): Promise<Comment[]> {
    const API_BASE_URL = getApiUrl();
    try {
      const response = await fetch(`${API_BASE_URL}?action=place&id=${placeId}`);
      if (!response.ok) throw new Error('Gagal mengambil data ulasan');
      const placeData = await response.json();
      return placeData.comments || [];
    } catch {
      return [];
    }
  },

  // 2. Kirim ulasan / komentar baru
  async addComment(placeId: string, username: string, comment: string, rating: number): Promise<void> {
    const API_BASE_URL = getApiUrl();
    const response = await fetch(`${API_BASE_URL}?action=add_comment`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ placeId, username, comment, rating })
    });
    const result = await response.json();
    if (result.success !== true && result.status !== 'success') {
      throw new Error(result.message || 'Ulasan gagal dikirim');
    }
  }
};

export const authService = {
  async login(email: string, password: string): Promise<any> {
    const API_BASE_URL = getApiUrl();
    const response = await fetch(`${API_BASE_URL}?action=login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password })
    });
    const result = await response.json();
    if (result && (result.success === true || result.status === 'success')) {
      const userObj = result.user;
      return {
        id: userObj.id,
        username: userObj.username || 'admin',
        name: userObj.name || userObj.displayName || 'Administrator',
        email: userObj.email || `${userObj.username || 'admin'}@angstria.com` || 'admin@angstria.com',
        uid: userObj.uid || `admin_uid_${userObj.id || '123'}`
      };
    }
    throw new Error(result.message || 'Username atau password salah.');
  }
};

export const ownerService = {
  async register(name: string, email: string, password: string): Promise<any> {
    const API_BASE_URL = getApiUrl();
    const response = await fetch(`${API_BASE_URL}?action=owner_register`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name, email, password })
    });
    const result = await response.json();
    if (result && result.success === true) {
      return result.owner;
    }
    throw new Error(result.message || 'Gagal mendaftar pemilik kafe');
  },

  async login(email: string, password: string): Promise<any> {
    const API_BASE_URL = getApiUrl();
    const response = await fetch(`${API_BASE_URL}?action=owner_login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password })
    });
    const result = await response.json();
    if (result && result.success === true) {
      return result.owner;
    }
    throw new Error(result.message || 'Login gagal. Silakan coba kembali.');
  },

  async getProfile(email: string): Promise<any> {
    const API_BASE_URL = getApiUrl();
    const response = await fetch(`${API_BASE_URL}?action=get_owner&email=${encodeURIComponent(email)}`);
    const result = await response.json();
    if (result && result.success === true) {
      return result.owner;
    }
    throw new Error(result.message || 'Gagal memuat profil pemilik kafe');
  }
};

export const reservationsService = {
  async getReservations(params: { placeId?: string; ownerEmail?: string }): Promise<any[]> {
    const API_BASE_URL = getApiUrl();
    let url = `${API_BASE_URL}?action=get_reservations`;
    if (params.placeId) url += `&placeId=${params.placeId}`;
    if (params.ownerEmail) url += `&ownerEmail=${encodeURIComponent(params.ownerEmail)}`;

    const response = await fetch(url);
    const result = await response.json();
    return result.data || [];
  },

  async createReservation(data: {
    placeId: string;
    customerName: string;
    customerPhone: string;
    bookingDate: string;
    bookingTime: string;
    guests: number;
    notes: string;
  }): Promise<any> {
    const API_BASE_URL = getApiUrl();
    const response = await fetch(`${API_BASE_URL}?action=add_reservation`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    const result = await response.json();
    if (result && result.success === true) {
      return result.data;
    }
    throw new Error(result.message || 'Gagal mengirim reservasi');
  },

  async updateReservationStatus(reservationId: number, status: string): Promise<void> {
    const API_BASE_URL = getApiUrl();
    const response = await fetch(`${API_BASE_URL}?action=update_reservation_status`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ reservationId, status })
    });
    const result = await response.json();
    if (result && result.success !== true) {
      throw new Error(result.message || 'Gagal memperbarui status reservasi');
    }
  }
};

export const paymentsService = {
  async getPayments(ownerEmail: string): Promise<any[]> {
    const API_BASE_URL = getApiUrl();
    const response = await fetch(`${API_BASE_URL}?action=get_payments&ownerEmail=${encodeURIComponent(ownerEmail)}`);
    const result = await response.json();
    return result.data || [];
  },

  async addPayment(data: {
    ownerEmail: string;
    cafeId?: string;
    amount: number;
    type: 'registration' | 'promotion';
    method: string;
  }): Promise<any> {
    const API_BASE_URL = getApiUrl();
    const response = await fetch(`${API_BASE_URL}?action=add_payment`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    const result = await response.json();
    if (result && result.success === true) {
      return result.data;
    }
    throw new Error(result.message || 'Gagal menyimpan transaksi pembayaran');
  }
};
