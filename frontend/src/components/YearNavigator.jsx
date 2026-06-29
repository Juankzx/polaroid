import { motion } from 'framer-motion';

export default function YearNavigator({ years, activeYear, onYearClick }) {
  if (!years || years.length === 0) return null;

  return (
    <motion.nav 
      className="year-navigator"
      initial={{ opacity: 0, x: 30 }}
      animate={{ opacity: 1, x: 0 }}
      transition={{ delay: 2.5, duration: 0.8 }}
    >
      <div className="year-nav-track">
        {years.map((year) => (
          <button
            key={year}
            onClick={() => onYearClick(year)}
            className={`year-nav-btn ${activeYear === year ? 'active' : ''}`}
            title={`Ir a ${year}`}
          >
            <span className="year-nav-dot"></span>
            <span className="year-nav-label">{year}</span>
          </button>
        ))}
      </div>
    </motion.nav>
  );
}
