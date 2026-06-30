import { useState, useRef, useEffect } from 'react';
import { motion } from 'framer-motion';

export default function MusicPlayer({ settings }) {
  const [isPlaying, setIsPlaying] = useState(false);
  const [volume, setVolume] = useState(0.5);
  const audioRef = useRef(null);

  // Audio por defecto
  let songUrl = "https://cdn.pixabay.com/audio/2022/05/27/audio_1808fbf07a.mp3"; 

  const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000';

  // Si se subió un MP3 personalizado, usamos ese
  if (settings?.custom_audio_url) {
    songUrl = settings.custom_audio_url;
  } else if (settings?.custom_audio_path) {
    // Retrocompatibilidad por si falla la API
    songUrl = settings.custom_audio_path.startsWith('http') 
      ? settings.custom_audio_path 
      : `${API_URL}/storage/${settings.custom_audio_path}`;
  }

  useEffect(() => {
    if (audioRef.current) {
      audioRef.current.volume = volume;
    }
  }, []);

  const togglePlay = () => {
    if (isPlaying) {
      audioRef.current.pause();
    } else {
      audioRef.current.play();
    }
    setIsPlaying(!isPlaying);
  };

  const handleVolumeChange = (e) => {
    const newVolume = parseFloat(e.target.value);
    setVolume(newVolume);
    if (audioRef.current) {
      audioRef.current.volume = newVolume;
    }
  };

  return (
    <motion.div 
      className="music-player"
      initial={{ y: 50, opacity: 0 }}
      animate={{ y: 0, opacity: 1 }}
      transition={{ delay: 1, duration: 0.5 }}
    >
      <audio ref={audioRef} src={songUrl} loop />
      <button onClick={togglePlay} className={`music-btn ${isPlaying ? 'playing' : ''}`}>
        {isPlaying ? '⏸' : '▶'}
      </button>
      <div className="music-info">
        <span className="music-title">Nuestra Canción</span>
        {isPlaying && (
          <div className="music-waves">
            <span className="wave"></span>
            <span className="wave"></span>
            <span className="wave"></span>
          </div>
        )}
      </div>
      <div className="music-volume-container">
        <span className="volume-icon">{volume === 0 ? '🔇' : (volume < 0.5 ? '🔉' : '🔊')}</span>
        <input 
          type="range" 
          min="0" 
          max="1" 
          step="0.01" 
          value={volume} 
          onChange={handleVolumeChange} 
          className="volume-slider" 
        />
      </div>
    </motion.div>
  );
}
