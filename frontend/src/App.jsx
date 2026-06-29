import { Routes, Route } from 'react-router-dom';
import Home from './pages/Home';
import AdminUpload from './pages/AdminUpload';

function App() {
  return (
    <Routes>
      <Route path="/" element={<Home />} />
      <Route path="/admin/subir-fotos" element={<AdminUpload />} />
    </Routes>
  );
}

export default App;
