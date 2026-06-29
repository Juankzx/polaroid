import { useState } from 'react';
import { motion } from 'framer-motion';

export default function LockedMemory({ memory, onUnlock }) {
  const [answer, setAnswer] = useState('');
  const [error, setError] = useState(false);

  const checkAnswer = () => {
    // Comparación simple ignorando mayúsculas y espacios
    if (answer.toLowerCase().trim() === memory.unlock_answer.toLowerCase().trim()) {
      onUnlock();
    } else {
      setError(true);
      setTimeout(() => setError(false), 2000);
    }
  };

  return (
    <div className="locked-memory glass-card">
      <div className="locked-icon">🔒</div>
      <h3 className="locked-title">Recuerdo Bloqueado</h3>
      <p className="locked-question">{memory.unlock_question}</p>
      
      <motion.div 
        className="locked-input-group"
        animate={error ? { x: [-10, 10, -10, 10, 0] } : {}}
        transition={{ duration: 0.4 }}
      >
        <input 
          type="text" 
          value={answer}
          onChange={(e) => setAnswer(e.target.value)}
          placeholder="Escribe la respuesta secreta..."
          className={error ? 'error' : ''}
          onKeyDown={(e) => e.key === 'Enter' && checkAnswer()}
        />
        <button onClick={checkAnswer}>Desbloquear</button>
      </motion.div>
      {error && <p className="error-text">Respuesta incorrecta. Piensa bien... ❤️</p>}
    </div>
  );
}
