import { useState } from 'react';
import { motion } from 'framer-motion';
import Tilt from 'react-parallax-tilt';
import LockedMemory from './LockedMemory';

export default function Polaroid({ memory }) {
  const [isFlipped, setIsFlipped] = useState(false);
  // Un recuerdo está desbloqueado si is_locked es falso
  const [unlocked, setUnlocked] = useState(!memory.is_locked);

  const handleFlip = () => {
    if (unlocked) {
      setIsFlipped(!isFlipped);
    }
  };

  if (!unlocked) {
    return <LockedMemory memory={memory} onUnlock={() => setUnlocked(true)} />;
  }

  return (
    <Tilt
      tiltMaxAngleX={10}
      tiltMaxAngleY={10}
      perspective={1000}
      scale={1.02}
      transitionSpeed={1500}
      className="polaroid-container"
    >
      <div onClick={handleFlip} style={{ width: '100%', height: '100%' }}>
        <motion.div
          className="polaroid-inner"
        animate={{ rotateY: isFlipped ? 180 : 0 }}
        transition={{ duration: 0.6, type: "spring", stiffness: 260, damping: 20 }}
      >
        {/* Lado Frontal de la Polaroid */}
        <div className="polaroid-front">
          <div className="polaroid-image-wrapper">
            {memory.image_path ? (
              <img 
                src={memory.image_path.startsWith('http') ? memory.image_path : `${import.meta.env.VITE_API_URL || 'http://localhost:8000'}/storage/${memory.image_path}`} 
                alt={memory.title} 
              />
            ) : (
              <div className="no-image-placeholder">✨ Un Recuerdo Especial ✨</div>
            )}
          </div>
          <div className="polaroid-caption">
            <h3>{memory.title}</h3>
            <p>{memory.date ? new Date(memory.date).toLocaleDateString('es-ES', { month: 'long', year: 'numeric' }) : 'Fecha especial'}</p>
            {memory.location && (
              <p className="mt-1 text-[0.65rem] text-rose-500 font-semibold uppercase flex items-center justify-center gap-1">
                📍 {memory.location}
              </p>
            )}
          </div>
          <div className="flip-hint">Toca para voltear ⤵</div>
        </div>

        {/* Lado Trasero (Dedicatoria) */}
        <div className="polaroid-back">
          <div className="polaroid-back-content">
            <h4 className="handwriting-title">{memory.title}</h4>
            <p className="handwriting">{memory.description}</p>
            <div className="polaroid-tape"></div>
          </div>
        </div>
        </motion.div>
      </div>
    </Tilt>
  );
}
