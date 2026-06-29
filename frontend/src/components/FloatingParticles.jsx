import { useEffect, useState, useMemo } from 'react';
import { motion } from 'framer-motion';

export default function FloatingParticles({ count = 30, themeType = 'love' }) {
  const [windowSize, setWindowSize] = useState({ width: 0, height: 0 });

  useEffect(() => {
    setWindowSize({ width: window.innerWidth, height: window.innerHeight });
    
    const handleResize = () => {
      setWindowSize({ width: window.innerWidth, height: window.innerHeight });
    };
    
    window.addEventListener('resize', handleResize);
    return () => window.removeEventListener('resize', handleResize);
  }, []);

  // Generar partículas aleatorias estáticas para no recalcular en cada render
  const particles = useMemo(() => {
    if (windowSize.width === 0) return [];
    
    return Array.from({ length: count }).map((_, i) => ({
      id: i,
      x: Math.random() * windowSize.width,
      y: Math.random() * windowSize.height,
      size: Math.random() * 4 + 1,
      duration: Math.random() * 20 + 10,
      delay: Math.random() * 5,
      opacity: Math.random() * 0.5 + 0.1,
    }));
  }, [count, windowSize.width, windowSize.height]);

  if (windowSize.width === 0) return null;

  const getParticleContent = () => {
    switch(themeType) {
      case 'love': return '💖';
      case 'birthday': return ['🎉', '🎈', '🍰', '🎁'][Math.floor(Math.random() * 4)];
      case 'anniversary': return '✨';
      default: return '';
    }
  };

  return (
    <div className="particles-container">
      {particles.map((particle) => (
        <motion.div
          key={particle.id}
          className={`particle ${themeType === 'classic' ? 'classic-dot' : 'emoji-particle'}`}
          initial={{
            x: particle.x,
            y: particle.y,
            opacity: particle.opacity,
            scale: particle.size * (themeType === 'classic' ? 1 : 0.4),
          }}
          animate={{
            y: [particle.y, particle.y - 150, particle.y],
            x: [particle.x, particle.x + 80, particle.x - 80, particle.x],
            opacity: [particle.opacity, particle.opacity * 2, particle.opacity],
            rotate: themeType !== 'classic' ? [0, 10, -10, 0] : 0
          }}
          transition={{
            duration: particle.duration,
            repeat: Infinity,
            ease: "easeInOut",
            delay: particle.delay,
          }}
        >
          {themeType !== 'classic' && getParticleContent()}
        </motion.div>
      ))}
    </div>
  );
}
