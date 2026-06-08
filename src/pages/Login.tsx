import React, { useState } from 'react';
import { motion } from 'motion/react';
import { useNavigate, Navigate, Link } from 'react-router-dom';
import { LogIn, Mail, Lock, Coffee, AlertCircle, Sparkles } from 'lucide-react';
import { authService } from '../services/dbService';

interface LoginProps {
  user: any;
}

const Login: React.FC<LoginProps> = ({ user }) => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  if (user) return <Navigate to="/admin" />;

  const handleEmailLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    
    try {
      const adminUser = await authService.login(email, password);
      localStorage.setItem('angstria_admin_session', JSON.stringify(adminUser));
      window.dispatchEvent(new Event('admin-auth-changed'));
      navigate('/admin');
    } catch (err: any) {
      setError(err.message || 'Email atau password salah. Silakan coba: admin@angstria.com dan password: admin');
    } finally {
      setLoading(false);
    }
  };

  const handleGoogleLogin = async () => {
    setLoading(true);
    setError('');
    const adminUser = { email: 'admin@angstria.com', uid: 'admin_uid_123', displayName: 'Administrator (Google)' };
    localStorage.setItem('angstria_admin_session', JSON.stringify(adminUser));
    window.dispatchEvent(new Event('admin-auth-changed'));
    navigate('/admin');
    setLoading(false);
  };

  return (
    <motion.div
      initial={{ opacity: 0 }}
      animate={{ opacity: 1 }}
      exit={{ opacity: 0 }}
      className="min-h-screen bg-cafe-beige flex items-center justify-center p-6"
    >
      <div className="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-cafe-brown via-cafe-mocha to-cafe-pastel"></div>
      
      <div className="max-w-md w-full bg-white rounded-[3rem] p-12 shadow-2xl border border-cafe-pastel relative overflow-hidden">
        {/* Background Accent */}
        <div className="absolute -top-20 -right-20 w-48 h-48 bg-cafe-beige rounded-full -z-10 opacity-50"></div>
        
        <div className="text-center mb-12">
          <div className="w-16 h-16 bg-cafe-brown rounded-2xl flex items-center justify-center text-cafe-cream mx-auto mb-6 shadow-lg rotate-3 group hover:rotate-0 transition-transform">
            <Coffee size={32} />
          </div>
          <h1 className="text-3xl font-serif font-bold text-cafe-brown mb-2 tracking-tight">Selamat Datang</h1>
          <p className="text-cafe-mocha/60 text-sm font-medium">Khusus Akses Administrator</p>
        </div>

        {error && (
          <motion.div 
            initial={{ opacity: 0, y: -10 }}
            animate={{ opacity: 1, y: 0 }}
            className="flex items-center gap-3 bg-red-50 text-red-600 p-4 rounded-2xl mb-8 border border-red-100 text-sm font-medium"
          >
            <AlertCircle size={18} /> {error}
          </motion.div>
        )}

        <form onSubmit={handleEmailLogin} className="space-y-6">
          <div className="space-y-2">
            <label className="text-[10px] uppercase font-bold text-cafe-mocha/70 tracking-[0.2em] ml-2">Alamat Email</label>
            <div className="relative">
              <Mail className="absolute left-4 top-1/2 -translate-y-1/2 text-cafe-mocha/40" size={18} />
              <input 
                type="email" 
                required
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                autoComplete="email"
                className="w-full bg-cafe-beige border border-cafe-pastel rounded-2xl py-4 pl-12 pr-4 focus:outline-none focus:border-cafe-brown focus:ring-4 focus:ring-cafe-brown/5 transition-all text-cafe-brown"
                placeholder="admin@angstria.com"
              />
            </div>
          </div>

          <div className="space-y-2">
            <label className="text-[10px] uppercase font-bold text-cafe-mocha/70 tracking-[0.2em] ml-2">Kata Sandi Aman</label>
            <div className="relative">
              <Lock className="absolute left-4 top-1/2 -translate-y-1/2 text-cafe-mocha/40" size={18} />
              <input 
                type="password" 
                required
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                autoComplete="current-password"
                className="w-full bg-cafe-beige border border-cafe-pastel rounded-2xl py-4 pl-12 pr-4 focus:outline-none focus:border-cafe-brown focus:ring-4 focus:ring-cafe-brown/5 transition-all text-cafe-brown"
                placeholder="••••••••"
              />
            </div>
          </div>

          <button 
            type="submit" 
            disabled={loading}
            className="w-full btn-primary mt-4 flex items-center justify-center gap-2 group disabled:opacity-50"
          >
            {loading ? 'Mengautentikasi...' : <><LogIn size={20} className="group-hover:translate-x-1 transition-transform" /> Masuk</>}
          </button>
        </form>

        <div className="mt-8 flex items-center gap-4">
          <div className="h-px bg-cafe-pastel flex-grow"></div>
          <span className="text-[10px] uppercase font-bold text-cafe-mocha/40">OR</span>
          <div className="h-px bg-cafe-pastel flex-grow"></div>
        </div>

        <button 
          onClick={handleGoogleLogin}
          disabled={loading}
          className="w-full mt-8 flex items-center justify-center gap-3 bg-white border border-cafe-pastel py-4 rounded-2xl text-sm font-bold text-cafe-brown hover:bg-cafe-beige transition-all group"
        >
          <img src="https://www.google.com/favicon.ico" alt="Google" className="w-5 h-5 grayscale group-hover:grayscale-0 transition-all" />
          Lanjutkan dengan Google
        </button>

        <div className="mt-12 text-center">
          <Link to="/" className="text-xs font-bold text-cafe-mocha/50 hover:text-cafe-brown transition-colors flex items-center justify-center gap-2">
            <Sparkles size={14} /> Kembali ke Beranda
          </Link>
        </div>
      </div>
    </motion.div>
  );
};

export default Login;
