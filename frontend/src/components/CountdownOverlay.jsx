import React, { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import FloatingParticles from './FloatingParticles';

export default function CountdownOverlay({ settings, onComplete }) {
  const targetTime = settings ? new Date(settings.target_date).getTime() : new Date().getTime();
  const [timeLeft, setTimeLeft] = useState(calculateTimeLeft());

  function calculateTimeLeft() {
    const difference = targetTime - new Date().getTime();
    if (difference <= 0) return { days: 0, hours: 0, minutes: 0, seconds: 0 };
    return {
      days: Math.floor(difference / (1000 * 60 * 60 * 24)),
      hours: Math.floor((difference / (1000 * 60 * 60)) % 24),
      minutes: Math.floor((difference / 1000 / 60) % 60),
      seconds: Math.floor((difference / 1000) % 60),
    };
  }

  useEffect(() => {
    // Si ya pasó la fecha, no hacemos el intervalo
    if (targetTime - new Date().getTime() <= 0) {
      if (onComplete) onComplete();
      return;
    }

    const timer = setInterval(() => {
      const newTime = calculateTimeLeft();
      setTimeLeft(newTime);
      if (
        newTime.days === 0 &&
        newTime.hours === 0 &&
        newTime.minutes === 0 &&
        newTime.seconds === 0
      ) {
        clearInterval(timer);
        if (onComplete) onComplete();
      }
    }, 1000);

    return () => clearInterval(timer);
  }, [onComplete]);

  return (
    <div className="countdown-container">
      <FloatingParticles count={40} />
      <motion.div 
        className="countdown-content glass-card"
        initial={{ opacity: 0, scale: 0.9, y: 20 }}
        animate={{ opacity: 1, scale: 1, y: 0 }}
        transition={{ duration: 0.8, ease: "easeOut" }}
      >
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 0.5, duration: 1 }}
        >
          <h1 className="countdown-title">{settings?.countdown_title || 'Próximamente'}</h1>
          <p className="countdown-subtitle">{settings?.countdown_subtitle || 'Pronto estará disponible'}</p>
        </motion.div>
        
        <motion.div 
          className="countdown-timer"
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.8, duration: 0.8 }}
        >
          <div className="time-box">
            <span className="time-value">{timeLeft.days}</span>
            <span className="time-label">Días</span>
          </div>
          <div className="time-separator">:</div>
          <div className="time-box">
            <span className="time-value">{String(timeLeft.hours).padStart(2, '0')}</span>
            <span className="time-label">Horas</span>
          </div>
          <div className="time-separator">:</div>
          <div className="time-box">
            <span className="time-value">{String(timeLeft.minutes).padStart(2, '0')}</span>
            <span className="time-label">Min</span>
          </div>
          <div className="time-separator">:</div>
          <div className="time-box">
            <span className="time-value">{String(timeLeft.seconds).padStart(2, '0')}</span>
            <span className="time-label">Seg</span>
          </div>
        </motion.div>
      </motion.div>
    </div>
  );
}
