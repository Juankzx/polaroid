import { useState, useEffect, useMemo, useCallback } from 'react';
import { motion, AnimatePresence, useScroll } from 'framer-motion';
import Polaroid from '../components/Polaroid';
import MusicPlayer from '../components/MusicPlayer';
import GiftOverlay from '../components/GiftOverlay';
import YearNavigator from '../components/YearNavigator';
import FloatingParticles from '../components/FloatingParticles';
import MagicCursor from '../components/MagicCursor';
import CountdownOverlay from '../components/CountdownOverlay';

// Helper para formatear fecha
function formatMonthYear(dateStr) {
  if (!dateStr) return null;
  const d = new Date(dateStr);
  const month = d.toLocaleDateString('es-ES', { month: 'long' });
  const year = d.getFullYear();
  return { month: month.charAt(0).toUpperCase() + month.slice(1), year };
}

// Agrupar memorias por "Mes Año"
function groupByMonth(memories) {
  const groups = [];
  let currentKey = null;

  for (const memory of memories) {
    const info = formatMonthYear(memory.date);
    const key = info ? `${info.month} ${info.year}` : 'Momentos Especiales';
    
    if (key !== currentKey) {
      groups.push({ type: 'header', key, info });
      currentKey = key;
    }
    groups.push({ type: 'memory', data: memory });
  }
  return groups;
}

// Emoji por categoría
function getCategoryEmoji(cat) {
  if (!cat) return '📸';
  if (cat.includes('Viajes')) return '✈️';
  if (cat.includes('Fechas')) return '🎂';
  if (cat.includes('Divertidos')) return '🤪';
  if (cat.includes('Logros')) return '🏆';
  return '📸';
}

function Home() {
  const [memories, setMemories] = useState([]);
  const [loading, setLoading] = useState(true);
  const [isGiftOpened, setIsGiftOpened] = useState(false);
  const [selectedCategory, setSelectedCategory] = useState('Todas');
  const [activeYear, setActiveYear] = useState(null);
  
  // Settings state
  const [settings, setSettings] = useState(null);
  const [isLocked, setIsLocked] = useState(false);
  
  const { scrollYProgress } = useScroll();

  // Asumimos que la API de Laravel está en el mismo host o en localhost:8000
  const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000';

  useEffect(() => {
    // Fetch memories
    fetch(`${API_URL}/api/memories`)
      .then(response => response.json())
      .then(data => {
        const sorted = [...data].sort((a, b) => new Date(a.date) - new Date(b.date));
        setMemories(sorted);
      })
      .catch(error => {
        console.error('Error fetching memories:', error);
      });

    // Fetch settings
    fetch(`${API_URL}/api/settings`)
      .then(response => response.json())
      .then(data => {
        setSettings(data);
        if (data.is_locked) {
          setIsLocked(new Date().getTime() < new Date(data.target_date).getTime());
        } else {
          setIsLocked(false);
        }
        
        if (data.is_gift_enabled === false || data.is_gift_enabled === 0) {
          setIsGiftOpened(true);
        }
        
        setLoading(false);
      })
      .catch(error => {
        console.error('Error fetching settings:', error);
        setLoading(false);
      });
  }, [API_URL]);

  // Obtener categorías únicas
  const categories = useMemo(() => {
    const cats = memories.map(m => m.category || 'Sin Categoría');
    return ['Todas', ...new Set(cats)];
  }, [memories]);

  // Filtrar memorias
  const filteredMemories = useMemo(() => {
    if (selectedCategory === 'Todas') return memories;
    return memories.filter(m => (m.category || 'Sin Categoría') === selectedCategory);
  }, [memories, selectedCategory]);

  // Agrupar por mes
  const grouped = useMemo(() => groupByMonth(filteredMemories), [filteredMemories]);

  // Obtener años únicos
  const years = useMemo(() => {
    const yearSet = new Set();
    filteredMemories.forEach(m => {
      if (m.date) yearSet.add(new Date(m.date).getFullYear());
    });
    return [...yearSet].sort();
  }, [filteredMemories]);

  // Navegar a un año
  const scrollToYear = useCallback((year) => {
    setActiveYear(year);
    const el = document.getElementById(`year-${year}`);
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }, []);

  // Detectar el año visible con IntersectionObserver
  useEffect(() => {
    if (!isGiftOpened || loading) return;

    const markers = document.querySelectorAll('[data-year]');
    if (markers.length === 0) return;

    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            setActiveYear(Number(entry.target.dataset.year));
          }
        }
      },
      { rootMargin: '-30% 0px -60% 0px' }
    );

    markers.forEach(m => observer.observe(m));
    return () => observer.disconnect();
  }, [isGiftOpened, loading, grouped]);

  // Contador de memorias para el índice de animación
  let memoryIndex = 0;

  if (isLocked && settings) {
    return <CountdownOverlay settings={settings} onComplete={() => setIsLocked(false)} />;
  }

  return (
    <>
      <AnimatePresence>
        {!isGiftOpened && (
          <GiftOverlay settings={settings} onOpen={() => setIsGiftOpened(true)} />
        )}
      </AnimatePresence>

      {isGiftOpened && <MagicCursor />}
      {isGiftOpened && <FloatingParticles count={40} themeType={settings?.theme_type || 'love'} />}
      
      <motion.div 
        className="scroll-progress-bar"
        style={{ scaleX: scrollYProgress }}
      />

      <motion.div 
        className="app-container"
        style={{ '--accent-color': settings?.theme_color || '#f43f5e' }}
        initial={{ opacity: 0 }}
        animate={{ opacity: isGiftOpened ? 1 : 0 }}
        transition={{ duration: 1.5, delay: 0.5 }}
      >
        <MusicPlayer settings={settings} />
        
        <header className="header">
          <motion.h1 
            initial="hidden"
            animate={isGiftOpened ? "visible" : "hidden"}
            variants={{
              hidden: { opacity: 0 },
              visible: {
                opacity: 1,
                transition: { staggerChildren: 0.1, delayChildren: 1 }
              }
            }}
          >
            {(settings?.hero_title || "Nuestra Historia").split("").map((char, index) => (
              <motion.span
                key={index}
                variants={{
                  hidden: { opacity: 0, y: 20 },
                  visible: { opacity: 1, y: 0 }
                }}
              >
                {char === " " ? "\u00A0" : char}
              </motion.span>
            ))}
          </motion.h1>
          <motion.p
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ delay: 1.5, duration: 1 }}
          >
            {settings?.hero_subtitle || "Un recorrido por nuestros mejores momentos"}
          </motion.p>
        </header>

        {/* Category Selector */}
        {!loading && memories.length > 0 && (
          <motion.div 
            className="category-selector hide-scrollbar"
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 2, duration: 0.8 }}
          >
            <div className="category-pills">
              {categories.map((cat) => (
                <button
                  key={cat}
                  onClick={() => setSelectedCategory(cat)}
                  className={`category-pill ${selectedCategory === cat ? 'active' : ''}`}
                >
                  {cat}
                </button>
              ))}
            </div>
          </motion.div>
        )}

        {/* Year Navigator */}
        {!loading && years.length > 0 && (
          <YearNavigator 
            years={years} 
            activeYear={activeYear} 
            onYearClick={scrollToYear} 
          />
        )}

        {/* Timeline */}
        <main className="timeline">
          {loading ? (
            <div className="loading-state">
              <div className="spinner"></div>
              <p>Cargando recuerdos mágicos...</p>
            </div>
          ) : filteredMemories.length === 0 ? (
            <div className="empty-state glass-card">
              <p>Aún no hay recuerdos en esta categoría.</p>
              <p className="small-text">Sube fotos en el panel de administrador para comenzar.</p>
            </div>
          ) : (
            grouped.map((item, idx) => {
              if (item.type === 'header') {
                const yearId = item.info?.year ? `year-${item.info.year}` : `header-${idx}`;
                // Solo poner el ID en el primer header de cada año
                const isFirstOfYear = !grouped.slice(0, idx).some(
                  g => g.type === 'header' && g.info?.year === item.info?.year
                );

                return (
                  <motion.div 
                    key={'h-' + item.key}
                    id={isFirstOfYear ? yearId : undefined}
                    data-year={item.info?.year}
                    className="timeline-date-marker"
                    initial={{ opacity: 0, scale: 0.8 }}
                    whileInView={{ opacity: 1, scale: 1 }}
                    viewport={{ once: true, margin: "-50px" }}
                    transition={{ duration: 0.6, type: "spring" }}
                  >
                    <div className="timeline-date-line"></div>
                    <div className="timeline-date-badge">
                      <span className="timeline-date-month">{item.info?.month || 'Especial'}</span>
                      <span className="timeline-date-year">{item.info?.year || '💕'}</span>
                    </div>
                    <div className="timeline-date-line"></div>
                  </motion.div>
                );
              }
              
              const currentIndex = memoryIndex++;
              const memory = item.data;
              return (
                <motion.div 
                  key={memory.id} 
                  className="timeline-item"
                  initial={{ opacity: 0, x: currentIndex % 2 === 0 ? -50 : 50 }}
                  whileInView={{ opacity: 1, x: 0 }}
                  viewport={{ once: true, margin: "-80px" }}
                  transition={{ duration: 0.7, delay: 0.1 }}
                >
                  <div className="timeline-dot">
                    <div className="timeline-dot-ring"></div>
                  </div>
                  {memory.category && (
                    <div className="timeline-category-tag">
                      {getCategoryEmoji(memory.category)}
                    </div>
                  )}
                  <Polaroid memory={memory} />
                </motion.div>
              );
            })
          )}
          
          {/* Punto final del timeline */}
          {!loading && filteredMemories.length > 0 && (
            <motion.div 
               className="timeline-end"
               initial={{ opacity: 0 }}
               whileInView={{ opacity: 1 }}
               viewport={{ once: true }}
               transition={{ duration: 1 }}
             >
               <div className="timeline-end-heart">💕</div>
               <p className="timeline-end-text">Continuará...</p>
             </motion.div>
           )}
         </main>
 
         <footer className="footer">
           <p>Hecho con ❤️ para ti</p>
         </footer>
       </motion.div>
     </>
   );
}

export default Home;
