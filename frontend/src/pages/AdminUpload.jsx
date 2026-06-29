import { useState } from 'react';
import { Lock, ArrowLeft } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import BulkUploader from '../components/BulkUploader';

export default function AdminUpload() {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [password, setPassword] = useState('');
  const [error, setError] = useState(false);
  const navigate = useNavigate();

  // CONTRASEÑA POR DEFECTO PARA EL FRONTEND
  // Puedes cambiar "babu2026" por lo que quieras.
  const ADMIN_PIN = 'babu2026';

  const handleSubmit = (e) => {
    e.preventDefault();
    if (password === ADMIN_PIN) {
      setIsAuthenticated(true);
      setError(false);
    } else {
      setError(true);
      setPassword('');
    }
  };

  if (!isAuthenticated) {
    return (
      <div className="min-h-screen bg-pink-50 flex items-center justify-center p-4">
        <button 
          onClick={() => navigate('/')}
          className="absolute top-6 left-6 text-pink-600 hover:text-pink-800 flex items-center gap-2 bg-white/50 px-4 py-2 rounded-full shadow-sm"
        >
          <ArrowLeft className="w-5 h-5" /> Volver al Inicio
        </button>

        <div className="max-w-md w-full bg-white rounded-3xl shadow-xl p-8 border border-pink-100 text-center relative overflow-hidden">
          <div className="w-20 h-20 bg-pink-100 rounded-full flex items-center justify-center mx-auto mb-6 text-pink-500">
            <Lock className="w-10 h-10" />
          </div>
          <h2 className="text-2xl font-bold text-gray-800 mb-2">Acceso Restringido</h2>
          <p className="text-gray-500 mb-8">Ingresa el PIN de administrador para cargar fotos masivamente.</p>

          <form onSubmit={handleSubmit}>
            <input
              type="password"
              placeholder="••••••••"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className={`w-full text-center text-3xl tracking-widest p-4 rounded-xl border-2 mb-4 outline-none transition-colors ${
                error ? 'border-red-400 bg-red-50 text-red-700' : 'border-gray-200 focus:border-pink-500 bg-gray-50'
              }`}
              autoFocus
            />
            {error && <p className="text-red-500 text-sm font-medium mb-4">PIN incorrecto. Intenta de nuevo.</p>}
            
            <button 
              type="submit"
              className="w-full bg-gradient-to-r from-pink-500 to-rose-500 text-white font-bold py-4 rounded-xl shadow-lg hover:shadow-pink-500/30 hover:scale-[1.02] active:scale-[0.98] transition-all"
            >
              Desbloquear
            </button>
          </form>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-pink-50 py-12 px-4 relative">
      <button 
        onClick={() => navigate('/')}
        className="absolute top-6 left-6 text-pink-600 hover:text-pink-800 flex items-center gap-2 bg-white/80 backdrop-blur-sm px-4 py-2 rounded-full shadow-md z-10"
      >
        <ArrowLeft className="w-5 h-5" /> Volver al Inicio
      </button>

      {/* Renderizamos el BulkUploader que ya creamos */}
      <div className="pt-10">
        <BulkUploader />
      </div>
    </div>
  );
}
