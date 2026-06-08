import React, { useState, useEffect } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { Menu, X, Coffee, LayoutDashboard, LogIn, Sun, Moon, Database, Wifi, WifiOff, RefreshCw, Save, RotateCcw, Check, Sparkles } from 'lucide-react';
import { motion, AnimatePresence } from 'motion/react';
import { getApiUrl, setApiUrl, isDatabaseDemoMode } from '../services/dbService';

interface NavbarProps {
  user: any;
  darkMode: boolean;
  toggleDarkMode: () => void;
}

const Navbar: React.FC<NavbarProps> = ({ user, darkMode, toggleDarkMode }) => {
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const [isApiModalOpen, setIsApiModalOpen] = useState(false);
  const [apiUrl, setApiUrlState] = useState(getApiUrl());
  const [apiStatus, setApiStatus] = useState<'testing' | 'success' | 'failed' | 'idle'>('idle');
  const [apiStatusMessage, setApiStatusMessage] = useState('');
  
  const location = useLocation();

  const navLinks = [
    { name: 'Beranda', path: '/' },
    { name: 'Kafe', path: '/places' },
    { name: 'Favorit', path: '/places?favorites=true' },
    { name: 'Galeri', path: '/gallery' },
    { name: 'Pemilik Kafe', path: '/owner' },
  ];

  const isActive = (path: string) => location.pathname === path;

  // Sync state if storage changes elsewhere
  useEffect(() => {
    // Ambil konfigurasi URL database dari server node (global) agar tersinkronisasi otomatis
    fetch('/api/backend-url')
      .then(res => res.json())
      .then(data => {
        if (data && data.target_api_url) {
          setApiUrlState(data.target_api_url);
        }
      })
      .catch(err => console.error('Gagal memuat konfigurasi backend server:', err));

    const handleUrlChange = () => {
      setApiUrlState(getApiUrl());
    };
    window.addEventListener('angstria-api-url-changed', handleUrlChange);
    return () => {
      window.removeEventListener('angstria-api-url-changed', handleUrlChange);
    };
  }, []);

  const handleTestConnection = async () => {
    setApiStatus('testing');
    setApiStatusMessage('Sedang menguji koneksi...');
    try {
      const response = await fetch(`${apiUrl}?action=places`);
      if (response.ok) {
        const text = await response.text();
        let json;
        try {
          json = JSON.parse(text);
        } catch (e) {
          throw new Error('Server tidak mengembalikan tanggapan JSON. Pastikan XAMPP aktif.');
        }

        if (json && json.success === false) {
          throw new Error(json.message || 'Gagal terhubung ke database');
        }

        setApiStatus('success');
        setApiStatusMessage('Terhubung! Database phpMyAdmin terdeteksi dengan sukses secara langsung.');
      } else {
        throw new Error('Server merespon dengan status error HTTP ' + response.status);
      }
    } catch (err: any) {
      // Coba alternatif melalui proxy server
      try {
        const proxyResponse = await fetch('/api.php?action=places');
        if (proxyResponse.ok) {
          const text = await proxyResponse.text();
          let json;
          try {
            json = JSON.parse(text);
          } catch (e) {}

          if (json && json.success !== false) {
            setApiStatus('success');
            setApiStatusMessage('Selesai! Terhubung secara sukses dan aman via proxy server.');
            return;
          } else if (json && json.success === false) {
            setApiStatus('failed');
            setApiStatusMessage(`Gagal terhubung via proxy: ${json.message}`);
            return;
          }
        }
      } catch (proxyErr) {}
      
      setApiStatus('failed');
      setApiStatusMessage(err.message || `Gagal terhubung secara langsung atau melalui proxy. Pastikan XAMPP Apache/MySQL aktif, file api.php berada di lokasi yang tepat, atau jalankan tunneling jika diakses di luar laptop.`);
    }
  };

  const handleSaveApi = async () => {
    try {
      setApiStatus('testing');
      setApiStatusMessage('Sedang menyimpan konfigurasi ke server pusat...');
      
      const response = await fetch('/api/backend-url', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ target_api_url: apiUrl, use_live: true })
      });
      
      if (response.ok) {
        setApiUrl(apiUrl); // Tetap simpan di localStorage penjelajah saat ini sebagai cadangan
        setApiStatus('success');
        setApiStatusMessage('Konfigurasi database berhasil disimpan secara global untuk semua gadget!');
        setTimeout(() => {
          setIsApiModalOpen(false);
          window.location.reload();
        }, 1200);
      } else {
        throw new Error('Gagal memperbarui konfigurasi di server');
      }
    } catch (err: any) {
      setApiStatus('failed');
      setApiStatusMessage(`Gagal menyimpan ke server: ${err.message}`);
    }
  };

  const handleResetApi = async () => {
    try {
      localStorage.removeItem('angstria_api_url');
      const defaultUrl = 'http://localhost/api.php';
      
      const response = await fetch('/api/backend-url', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ target_api_url: defaultUrl, use_live: true })
      });
      
      if (response.ok) {
        setApiUrlState(defaultUrl);
        setApiUrl(defaultUrl);
        setApiStatus('idle');
        setApiStatusMessage('Konfigurasi database server dikembalikan ke bawaan localhost.');
      } else {
        throw new Error('Gagal mereset konfigurasi server');
      }
    } catch (err: any) {
      setApiStatus('failed');
      setApiStatusMessage(`Gagal mereset di server: ${err.message}`);
    }
  };

  return (
    <nav className="glass-nav px-6 py-4">
      <div className="max-w-7xl mx-auto flex justify-between items-center">
        {/* Logo */}
        <Link to="/" className="flex items-center gap-2 group cursor-pointer">
          <div className="w-10 h-10 bg-cafe-brown rounded-xl flex items-center justify-center text-cafe-cream group-hover:rotate-12 transition-transform">
            <Coffee size={24} />
          </div>
          <span className="text-xl font-serif font-bold tracking-tight text-cafe-brown">
            Angstria Hangout
          </span>
        </Link>

        {/* Desktop Nav */}
        <div className="hidden md:flex items-center gap-8">
          {navLinks.map((link) => (
            <Link
              key={link.path}
              to={link.path}
              className={`text-sm font-medium transition-colors hover:text-cafe-mocha ${
                isActive(link.path) ? 'text-cafe-brown border-b-2 border-cafe-brown pb-1' : 'text-cafe-mocha/60'
              }`}
            >
              {link.name}
            </Link>
          ))}
          
          <div className="h-6 w-px bg-cafe-pastel mx-2"></div>

          {/* Database Setup Trigger */}
          <button 
            onClick={() => {
              setApiUrlState(getApiUrl());
              setIsApiModalOpen(true);
            }}
            className="p-2 rounded-full hover:bg-cafe-pastel transition-colors text-cafe-brown flex items-center gap-1.5 cursor-pointer relative"
            title="Koneksi phpMyAdmin"
          >
            <Database size={20} className="hover:rotate-12 transition-transform" />
            <span className={`w-2 h-2 rounded-full animate-pulse ${isDatabaseDemoMode() ? 'bg-amber-500' : 'bg-emerald-500'}`}></span>
          </button>

          <button 
            onClick={toggleDarkMode}
            className="p-2 rounded-full hover:bg-cafe-pastel transition-colors text-cafe-brown cursor-pointer"
          >
            {darkMode ? <Sun size={20} /> : <Moon size={20} />}
          </button>

          {user ? (
            <Link to="/admin" className="p-2 rounded-full hover:bg-cafe-pastel transition-colors text-cafe-brown cursor-pointer">
              <LayoutDashboard size={20} />
            </Link>
          ) : (
            <Link to="/login" className="p-2 rounded-full hover:bg-cafe-pastel transition-colors text-cafe-brown cursor-pointer">
              <LogIn size={20} />
            </Link>
          )}
        </div>

        {/* Mobile Nav Actions */}
        <div className="md:hidden flex items-center gap-2">
          {/* Mobile Database Trigger */}
          <button 
            onClick={() => {
              setApiUrlState(getApiUrl());
              setIsApiModalOpen(true);
            }}
            className="p-2 rounded-full hover:bg-cafe-pastel transition-colors text-cafe-brown flex items-center justify-center cursor-pointer"
            title="Koneksi phpMyAdmin"
          >
            <Database size={20} />
            <span className={`w-1.5 h-1.5 rounded-full ml-1 ${isDatabaseDemoMode() ? 'bg-amber-500' : 'bg-emerald-500'}`}></span>
          </button>
          
          <button 
            onClick={toggleDarkMode}
            className="p-2 rounded-full hover:bg-cafe-pastel transition-colors text-cafe-brown cursor-pointer"
          >
            {darkMode ? <Sun size={20} /> : <Moon size={20} />}
          </button>
          
          <button 
            onClick={() => setIsMenuOpen(!isMenuOpen)}
            className="text-cafe-brown p-2 cursor-pointer"
          >
            {isMenuOpen ? <X size={28} /> : <Menu size={28} />}
          </button>
        </div>
      </div>

      {/* Mobile Nav Modal */}
      <AnimatePresence>
        {isMenuOpen && (
          <motion.div
            initial={{ opacity: 0, height: 0 }}
            animate={{ opacity: 1, height: 'auto' }}
            exit={{ opacity: 0, height: 0 }}
            className="md:hidden absolute top-18 left-0 right-0 bg-cafe-beige border-b border-cafe-pastel px-6 py-8 shadow-2xl flex flex-col gap-6"
          >
            {navLinks.map((link) => (
              <Link
                key={link.path}
                to={link.path}
                onClick={() => setIsMenuOpen(false)}
                className={`text-lg font-medium ${
                  isActive(link.path) ? 'text-cafe-brown' : 'text-cafe-mocha/60'
                }`}
              >
                {link.name}
              </Link>
            ))}
            <div className="h-px bg-cafe-pastel w-full"></div>
            {user ? (
              <Link
                to="/admin"
                onClick={() => setIsMenuOpen(false)}
                className="flex items-center gap-2 text-cafe-brown font-medium"
              >
                <LayoutDashboard size={20} /> Dasbor Admin
              </Link>
            ) : (
              <Link
                to="/login"
                onClick={() => setIsMenuOpen(false)}
                className="flex items-center gap-2 text-cafe-brown font-medium"
              >
                <LogIn size={20} /> Masuk Admin
              </Link>
            )}
          </motion.div>
        )}
      </AnimatePresence>

      {/* API Connections settings Modal */}
      <AnimatePresence>
        {isApiModalOpen && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-6 bg-black/50 backdrop-blur-sm">
            <motion.div
              initial={{ scale: 0.95, opacity: 0 }}
              animate={{ scale: 1, opacity: 1 }}
              exit={{ scale: 0.95, opacity: 0 }}
              className="bg-cafe-cream border border-cafe-pastel rounded-[2rem] p-8 max-w-lg w-full shadow-2xl space-y-6 relative max-h-[90vh] overflow-y-auto"
            >
              <button 
                onClick={() => setIsApiModalOpen(false)} 
                className="absolute top-6 right-6 p-2 rounded-full hover:bg-cafe-pastel transition-colors text-cafe-brown cursor-pointer"
              >
                <X size={20} />
              </button>

              <div className="space-y-4">
                <div className="flex items-center gap-2 text-amber-900">
                  <Database size={24} className="text-cafe-brown" />
                  <h3 className="font-serif font-black text-xl">Integrasi phpMyAdmin</h3>
                </div>

                <div className="p-4 rounded-xl border text-xs leading-relaxed bg-emerald-50 text-emerald-800 border-emerald-200">
                  <div>
                    <p className="font-bold mb-1">🟢 Status: Terhubung ke MySQL</p>
                    <p className="text-cafe-mocha">Aplikasi beroperasi penuh menggunakan data langsung dari phpMyAdmin database Anda!</p>
                  </div>
                </div>

                <p className="text-xs text-cafe-mocha/70">
                  Secara default, jika dikunjungi di <strong>localhost</strong>, aplikasi membaca database lokal XAMPP Anda. Ubah isian di bawah ini jika ingin menghubungkan ke domain database cPanel online Anda.
                </p>
              </div>

              <div className="h-px bg-cafe-pastel w-full"></div>

              <div className="space-y-4">
                <div className="space-y-1">
                  <label className="text-xs font-bold text-cafe-brown uppercase tracking-wider block">Alamat API (api.php)</label>
                  <input 
                    type="text" 
                    value={apiUrl}
                    onChange={(e) => setApiUrlState(e.target.value)}
                    placeholder="Contoh: http://localhost/api.php atau https://domainanda.com/api.php"
                    className="w-full bg-cafe-beige/70 border border-cafe-pastel rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-cafe-brown/20 font-mono text-cafe-brown"
                  />
                </div>

                {/* Status box */}
                {apiStatus !== 'idle' && (
                  <div className={`p-4 rounded-xl text-xs flex gap-2 border leading-relaxed ${
                    apiStatus === 'testing' ? 'bg-amber-50 text-amber-800 border-amber-200' :
                    apiStatus === 'success' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' :
                    'bg-rose-50 text-rose-800 border-rose-200'
                  }`}>
                    {apiStatus === 'testing' && <RefreshCw size={16} className="animate-spin flex-shrink-0 text-amber-600" />}
                    {apiStatus === 'success' && <Check size={16} className="text-emerald-600 flex-shrink-0" />}
                    {apiStatus === 'failed' && <X size={16} className="text-rose-600 flex-shrink-0" />}
                    <span>{apiStatusMessage}</span>
                  </div>
                )}

                {/* API Action buttons */}
                <div className="flex flex-wrap gap-2 pt-2">
                  <button 
                    onClick={handleTestConnection}
                    disabled={apiStatus === 'testing'}
                    className="px-4 py-2 bg-cafe-beige text-cafe-brown border border-cafe-pastel hover:bg-cafe-pastel text-xs font-semibold rounded-lg flex items-center gap-1.5 transition-all cursor-pointer disabled:opacity-50"
                  >
                    <RefreshCw size={14} className={apiStatus === 'testing' ? 'animate-spin' : ''} />
                    Uji Koneksi
                  </button>

                  <button 
                    onClick={handleResetApi}
                    className="px-4 py-2 bg-cafe-beige text-cafe-brown border border-cafe-pastel hover:bg-cafe-pastel text-xs font-semibold rounded-lg flex items-center gap-1.5 transition-all cursor-pointer"
                  >
                    <RotateCcw size={14} />
                    Reset ke Bawaan
                  </button>
                </div>
              </div>

              <div className="h-px bg-cafe-pastel w-full"></div>

              {/* Confirm / Close */}
              <div className="flex justify-end gap-3 pt-2">
                <button 
                  onClick={() => setIsApiModalOpen(false)}
                  className="px-6 py-2.5 text-xs font-bold text-cafe-brown hover:bg-cafe-beige rounded-xl transition-all cursor-pointer"
                >
                  Batal
                </button>
                <button 
                  onClick={handleSaveApi}
                  className="px-6 py-2.5 text-xs font-bold bg-cafe-brown text-cafe-cream hover:bg-cafe-mocha rounded-xl shadow-md transition-all cursor-pointer flex items-center gap-1.5"
                >
                  <Save size={14} />
                  Simpan & Terapkan
                </button>
              </div>
            </motion.div>
          </div>
        )}
      </AnimatePresence>
    </nav>
  );
};

export default Navbar;
