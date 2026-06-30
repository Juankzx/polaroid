import React, { useState, useEffect, useMemo } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import confetti from 'canvas-confetti';
import { Heart, Sparkles, Star, Gift } from 'lucide-react';

// Partículas flotantes de corazones
function FloatingHearts() {
  const hearts = useMemo(() => Array.from({ length: 15 }, (_, i) => ({
    id: i,
    x: Math.random() * 100,
    size: Math.random() * 16 + 8,
    delay: Math.random() * 5,
    duration: Math.random() * 6 + 8,
    opacity: Math.random() * 0.3 + 0.1,
  })), []);

  return (
    <div className="absolute inset-0 pointer-events-none overflow-hidden">
      {hearts.map(h => (
        <motion.div
          key={h.id}
          className="absolute"
          style={{ left: h.x + '%', bottom: '-30px', fontSize: h.size }}
          animate={{
            y: [0, -window.innerHeight - 100],
            x: [0, Math.sin(h.id) * 40, 0],
            opacity: [0, h.opacity, h.opacity, 0],
            rotate: [0, 20, -20, 0],
          }}
          transition={{
            duration: h.duration,
            delay: h.delay,
            repeat: Infinity,
            ease: "linear"
          }}
        >
          💕
        </motion.div>
      ))}
    </div>
  );
}

// Estrellas del fondo
function StarField() {
  const stars = useMemo(() => Array.from({ length: 50 }, (_, i) => ({
    id: i,
    x: Math.random() * 100,
    y: Math.random() * 100,
    size: Math.random() * 3 + 1,
    delay: Math.random() * 3,
    duration: Math.random() * 3 + 2,
  })), []);

  return (
    <div className="absolute inset-0 pointer-events-none">
      {stars.map(s => (
        <motion.div
          key={s.id}
          className="absolute rounded-full bg-white"
          style={{
            left: s.x + '%',
            top: s.y + '%',
            width: s.size,
            height: s.size,
            boxShadow: `0 0 ${s.size * 3}px ${s.size}px rgba(255,255,255,0.3)`
          }}
          animate={{
            opacity: [0.1, 0.9, 0.1],
            scale: [1, 1.5, 1],
          }}
          transition={{
            duration: s.duration,
            delay: s.delay,
            repeat: Infinity,
            ease: "easeInOut"
          }}
        />
      ))}
    </div>
  );
}

// Fase 2: Mensaje de cumpleaños
function BirthdayMessage({ settings, onContinue }) {
  useEffect(() => {
    // Lanzar confeti de celebración
    const timer1 = setTimeout(() => {
      confetti({
        particleCount: 100,
        spread: 70,
        origin: { y: 0.6, x: 0.5 },
        colors: ['#f43f5e', '#fb7185', '#fde047', '#a78bfa', '#38bdf8']
      });
    }, 500);

    const timer2 = setTimeout(() => {
      confetti({
        particleCount: 60,
        angle: 60,
        spread: 55,
        origin: { x: 0 },
        colors: ['#f43f5e', '#fde047', '#a78bfa']
      });
      confetti({
        particleCount: 60,
        angle: 120,
        spread: 55,
        origin: { x: 1 },
        colors: ['#fb7185', '#38bdf8', '#34d399']
      });
    }, 1500);

    return () => {
      clearTimeout(timer1);
      clearTimeout(timer2);
    };
  }, []);

  return (
    <motion.div
      className="fixed inset-0 z-50 flex flex-col items-center justify-center overflow-hidden"
      style={{ background: 'radial-gradient(ellipse at center, #1a0a2e 0%, #050510 70%)' }}
      initial={{ opacity: 0 }}
      animate={{ opacity: 1 }}
      exit={{ opacity: 0, scale: 1.1, filter: 'blur(15px)' }}
      transition={{ duration: 1.2 }}
    >
      <StarField />
      <FloatingHearts />

      {/* Emoji Cake animado */}
      <motion.div
        className="text-7xl md:text-8xl mb-6"
        initial={{ scale: 0, rotate: -180 }}
        animate={{ scale: 1, rotate: 0 }}
        transition={{ type: "spring", stiffness: 200, damping: 15, delay: 0.3 }}
      >
        🎂
      </motion.div>

      {/* Feliz Cumpleaños con letras animadas */}
      <div className="relative mb-6">
        <motion.h1
          className="text-5xl md:text-7xl font-bold text-center leading-tight"
          style={{
            fontFamily: "'Dancing Script', cursive",
            background: 'linear-gradient(135deg, #f43f5e 0%, #fb7185 25%, #fde047 50%, #a78bfa 75%, #38bdf8 100%)',
            backgroundSize: '200% 200%',
            WebkitBackgroundClip: 'text',
            WebkitTextFillColor: 'transparent',
            filter: 'drop-shadow(0 0 30px rgba(244,63,94,0.5))'
          }}
          initial={{ y: 40, opacity: 0 }}
          animate={{ 
            y: 0, 
            opacity: 1,
            backgroundPosition: ['0% 50%', '100% 50%', '0% 50%']
          }}
          transition={{ 
            y: { delay: 0.6, duration: 1, ease: "easeOut" },
            opacity: { delay: 0.6, duration: 1 },
            backgroundPosition: { duration: 4, repeat: Infinity, ease: "linear" }
          }}
        >
          {settings?.birthday_title || '¡Feliz Cumpleaños!'}
        </motion.h1>
      </div>

      {/* Decoradores: Sparkles */}
      <motion.div
        className="flex items-center gap-3 mb-8"
        initial={{ scale: 0 }}
        animate={{ scale: 1 }}
        transition={{ delay: 1.2, type: "spring" }}
      >
        <Sparkles className="w-5 h-5 text-amber-400" />
        <motion.p 
          className="text-rose-200/80 text-lg md:text-xl tracking-wide text-center"
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 1.5, duration: 1 }}
        >
          Este día es especial porque existes tú
        </motion.p>
        <Sparkles className="w-5 h-5 text-amber-400" />
      </motion.div>

      {/* Mensaje emocional */}
      <motion.p
        className="text-slate-300/60 text-base md:text-lg max-w-md text-center mb-14 px-6"
        style={{ fontFamily: "'Dancing Script', cursive", fontSize: '1.4rem' }}
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 2, duration: 1 }}
      >
        {settings?.birthday_message || 'Preparé algo muy especial para ti, un viaje por nuestros momentos más bonitos juntos... 💫'}
      </motion.p>

      {/* Botón de Continuar */}
      <motion.button
        className="relative rounded-full text-white font-semibold text-lg overflow-hidden group cursor-pointer border-0"
        style={{
          padding: '1rem 3rem',
          minWidth: '240px',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          background: 'linear-gradient(135deg, #f43f5e, #ec4899)',
          boxShadow: '0 0 30px rgba(244,63,94,0.4), 0 10px 40px rgba(0,0,0,0.3)',
        }}
        initial={{ opacity: 0, y: 30 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 2.8, duration: 0.8 }}
        whileHover={{ scale: 1.05, boxShadow: '0 0 50px rgba(244,63,94,0.6)' }}
        whileTap={{ scale: 0.95 }}
        onClick={onContinue}
      >
        <motion.span
          className="absolute inset-0 bg-white/20"
          animate={{ x: ['-100%', '200%'] }}
          transition={{ duration: 2, repeat: Infinity, repeatDelay: 1 }}
          style={{ skewX: '-20deg' }}
        />
        <span className="relative z-10 flex items-center justify-center gap-3 w-full">
          <span style={{ letterSpacing: '1px' }}>Ver mi sorpresa</span>
          <Heart className="w-6 h-6 fill-current" />
        </span>
      </motion.button>

      {/* Emojis decorativos flotando */}
      {['🎈', '🎀', '🎊', '✨', '🌟', '🎉'].map((emoji, i) => (
        <motion.div
          key={i}
          className="absolute text-3xl pointer-events-none"
          style={{
            left: `${15 + i * 14}%`,
            top: `${10 + (i % 3) * 30}%`,
          }}
          animate={{
            y: [0, -15, 0],
            rotate: [0, 10, -10, 0],
            opacity: [0.4, 0.8, 0.4],
          }}
          transition={{
            duration: 3 + i * 0.5,
            delay: 1 + i * 0.3,
            repeat: Infinity,
            ease: "easeInOut"
          }}
        >
          {emoji}
        </motion.div>
      ))}
    </motion.div>
  );
}

// Fase 1: La Caja de Regalo
export default function GiftOverlay({ settings, onOpen }) {
  const [phase, setPhase] = useState('gift'); // 'gift' -> 'birthday' -> done
  const [isOpening, setIsOpening] = useState(false);

  const handleOpenGift = () => {
    if (isOpening) return;
    setIsOpening(true);

    // Confeti de apertura
    const duration = 2000;
    const end = Date.now() + duration;

    const frame = () => {
      confetti({
        particleCount: 6,
        angle: 60,
        spread: 55,
        origin: { x: 0 },
        colors: ['#f43f5e', '#fb7185', '#fde047', '#a78bfa']
      });
      confetti({
        particleCount: 6,
        angle: 120,
        spread: 55,
        origin: { x: 1 },
        colors: ['#f43f5e', '#fb7185', '#fde047', '#a78bfa']
      });

      if (Date.now() < end) {
        requestAnimationFrame(frame);
      }
    };
    frame();

    // Transición a la fase de cumpleaños
    setTimeout(() => {
      setPhase('birthday');
    }, 2200);
  };

  const handleContinue = () => {
    setPhase('done');
    setTimeout(() => onOpen(), 800);
  };

  if (phase === 'done') {
    return (
      <motion.div
        className="fixed inset-0 z-50 bg-black"
        initial={{ opacity: 1 }}
        animate={{ opacity: 0 }}
        transition={{ duration: 0.8 }}
      />
    );
  }

  return (
    <AnimatePresence mode="wait">
      {phase === 'birthday' && (
        <BirthdayMessage key="birthday" settings={settings} onContinue={handleContinue} />
      )}

      {phase === 'gift' && (
        <motion.div
          key="gift"
          className="fixed inset-0 z-50 flex flex-col items-center justify-center overflow-hidden"
          style={{ background: 'radial-gradient(ellipse at center, #1a0a2e 0%, #050510 70%)' }}
          initial={{ opacity: 1 }}
          exit={{ opacity: 0, scale: 1.05 }}
          transition={{ duration: 0.8 }}
        >
          <StarField />

          {/* Textos */}
          <motion.div 
              initial={{ y: 30, opacity: 0 }}
              animate={{ y: 0, opacity: 1 }}
              transition={{ delay: 0.5, duration: 1.2, ease: "easeOut" }}
              className="text-center z-10 mb-48 md:mb-56"
          >
              <motion.div
                className="text-5xl mb-4"
                animate={{ rotate: [0, 10, -10, 0] }}
                transition={{ duration: 2, repeat: Infinity, repeatDelay: 1 }}
              >
                🎁
              </motion.div>
              <h1 
                className="text-4xl md:text-6xl font-bold mb-4"
                style={{
                  fontFamily: "'Dancing Script', cursive",
                  background: 'linear-gradient(to right, #f9a8d4, #fda4af, #fcd34d)',
                  WebkitBackgroundClip: 'text',
                  WebkitTextFillColor: 'transparent',
                  filter: 'drop-shadow(0 0 20px rgba(244,63,94,0.4))'
                }}
              >
                {settings?.gift_title || 'Tengo un regalo para ti...'}
              </h1>
              <motion.p 
                className="text-rose-200/50 text-sm tracking-[0.3em] uppercase"
                animate={{ opacity: [0.4, 0.8, 0.4] }}
                transition={{ duration: 2, repeat: Infinity }}
              >
                ✨ Toca la caja para descubrirlo ✨
              </motion.p>
          </motion.div>

          {/* Contenedor de la caja */}
          <motion.div 
              className="relative cursor-pointer group flex flex-col items-center z-20 mt-20 md:mt-28" 
              onClick={handleOpenGift}
              whileHover={!isOpening ? { scale: 1.06 } : {}}
              animate={!isOpening ? { y: [0, -8, 0] } : {}}
              transition={{ duration: 3, repeat: Infinity, ease: "easeInOut" }}
          >
              {/* Resplandor */}
              <motion.div 
                  className="absolute rounded-full blur-[100px]"
                  style={{ 
                    width: 300, height: 300, 
                    background: 'radial-gradient(circle, rgba(244,63,94,0.4), transparent)',
                    top: '50%', left: '50%', transform: 'translate(-50%, -50%)'
                  }}
                  animate={isOpening ? { scale: 4, opacity: 0 } : { scale: [1, 1.15, 1], opacity: [0.3, 0.5, 0.3] }}
                  transition={{ duration: 3, repeat: Infinity }}
              />

              {/* === TAPA === */}
              <motion.div
                  className="relative z-30 flex justify-center"
                  style={{ width: 240 }}
                  animate={isOpening ? { 
                      rotateZ: 40, 
                      x: 200, 
                      y: -300, 
                      opacity: 0,
                      scale: 1.3
                  } : {}}
                  transition={isOpening ? { duration: 1.5, ease: [0.22, 1, 0.36, 1] } : {}}
              >
                  {/* Moño */}
                  <div className="absolute -top-12 left-1/2 -translate-x-1/2 flex items-end z-10">
                      <div className="w-14 h-16 rounded-t-full border-[8px] border-amber-400 bg-gradient-to-tr from-amber-200/40 to-transparent -rotate-[15deg] translate-x-1 shadow-inner"></div>
                      <div className="w-7 h-7 bg-gradient-to-br from-amber-300 to-amber-600 rounded-full z-10 shadow-lg -mx-1 border-2 border-amber-200 flex items-center justify-center">
                          <div className="w-2 h-2 bg-amber-100 rounded-full"></div>
                      </div>
                      <div className="w-14 h-16 rounded-t-full border-[8px] border-amber-400 bg-gradient-to-tl from-amber-200/40 to-transparent rotate-[15deg] -translate-x-1 shadow-inner"></div>
                  </div>
                  {/* Cinta colgantes del moño */}
                  <div className="absolute -top-2 left-1/2 -translate-x-[60%] w-3 h-8 bg-gradient-to-b from-amber-400 to-amber-500 rounded-b-md rotate-[-8deg]"></div>
                  <div className="absolute -top-2 left-1/2 -translate-x-[40%] w-3 h-8 bg-gradient-to-b from-amber-400 to-amber-500 rounded-b-md rotate-[8deg]"></div>
                  
                  {/* Superficie de la tapa */}
                  <div className="w-full h-14 bg-gradient-to-b from-rose-400 via-rose-500 to-rose-600 rounded-t-xl rounded-b-sm shadow-[0_8px_25px_rgba(0,0,0,0.4),inset_0_2px_4px_rgba(255,255,255,0.3)]">
                      {/* Lazo horizontal */}
                      <div className="absolute top-1/2 -translate-y-1/2 left-0 w-full h-10 flex justify-center">
                          <div className="w-12 h-full bg-gradient-to-b from-amber-300 via-yellow-500 to-amber-600 shadow-[inset_0_0_8px_rgba(0,0,0,0.15)]"></div>
                      </div>
                      {/* Brillo superior */}
                      <div className="absolute top-0 left-0 w-full h-4 bg-gradient-to-b from-white/20 to-transparent rounded-t-xl"></div>
                  </div>
              </motion.div>

              {/* === CUERPO === */}
              <motion.div
                  className="relative z-20 overflow-hidden flex justify-center -mt-1"
                  style={{ width: 220, height: 170 }}
                  animate={isOpening ? { scale: 0.8, opacity: 0, y: 80 } : {}}
                  transition={{ delay: 0.5, duration: 1.2, ease: "easeIn" }}
              >
                  {/* Fondo de la caja */}
                  <div className="absolute inset-0 bg-gradient-to-b from-rose-500 via-rose-600 to-rose-900 rounded-b-xl shadow-[0_20px_50px_rgba(0,0,0,0.6),inset_0_-15px_30px_rgba(0,0,0,0.3)]">
                      {/* Lazo vertical */}
                      <div className="absolute top-0 left-1/2 -translate-x-1/2 w-12 h-full bg-gradient-to-b from-amber-300 via-yellow-500 to-amber-700 shadow-[inset_0_0_12px_rgba(0,0,0,0.2)]"></div>
                      {/* Reflejo lateral */}
                      <div className="absolute top-0 left-0 w-8 h-full bg-gradient-to-r from-white/10 to-transparent"></div>
                      {/* Sombra inferior */}
                      <div className="absolute bottom-0 left-0 w-full h-1/3 bg-gradient-to-t from-black/40 to-transparent rounded-b-xl"></div>
                  </div>

                  {/* Corazón que sale cuando se abre */}
                  <AnimatePresence>
                      {isOpening && (
                          <>
                              <motion.div
                                  initial={{ scale: 0, opacity: 0, y: 80 }}
                                  animate={{ scale: 3, opacity: [0, 1, 1, 0], y: -200 }}
                                  transition={{ duration: 2, ease: "easeOut" }}
                                  className="absolute z-30 bottom-0"
                              >
                                  <Heart className="w-16 h-16 text-rose-300 fill-rose-400" style={{ filter: 'drop-shadow(0 0 25px rgba(251,113,133,0.9))' }} />
                              </motion.div>
                              {/* Mini corazones extra */}
                              {[...Array(5)].map((_, i) => (
                                  <motion.div
                                      key={i}
                                      className="absolute z-20 bottom-5 text-xl"
                                      initial={{ scale: 0, opacity: 0 }}
                                      animate={{ 
                                          scale: 1, 
                                          opacity: [0, 1, 0], 
                                          y: -150 - Math.random() * 100,
                                          x: (Math.random() - 0.5) * 200,
                                      }}
                                      transition={{ duration: 1.5, delay: 0.3 + i * 0.15, ease: "easeOut" }}
                                  >
                                      {['💕', '💖', '✨', '🌟', '💗'][i]}
                                  </motion.div>
                              ))}
                          </>
                      )}
                  </AnimatePresence>
              </motion.div>

              {/* Sombra de la caja en el piso */}
              <motion.div
                  className="mt-4 rounded-full bg-black/30 blur-xl"
                  style={{ width: 180, height: 15 }}
                  animate={isOpening ? { opacity: 0, scale: 0.5 } : { scaleX: [1, 1.05, 1] }}
                  transition={{ duration: 3, repeat: Infinity }}
              />
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}
