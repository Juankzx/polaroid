import React, { useState, useCallback } from 'react';
import { useDropzone } from 'react-dropzone';
import axios from 'axios';
import { UploadCloud, CheckCircle, AlertCircle, X, Loader2 } from 'lucide-react';

export default function BulkUploader({ onUploadComplete, onClose }) {
  const [files, setFiles] = useState([]);
  const [uploading, setUploading] = useState(false);
  const [progress, setProgress] = useState(0);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(false);

  // Configuraciones (¡El usuario debe cambiar esto!)
  const CLOUDINARY_CLOUD_NAME = 'tu_cloud_name';
  const CLOUDINARY_UPLOAD_PRESET = 'tu_upload_preset';
  
  // Asumimos que la API de Laravel está en el mismo host o en localhost:8000
  // Deberíamos usar variables de entorno idealmente
  const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000';

  const onDrop = useCallback((acceptedFiles) => {
    // Añadimos un estado inicial a cada archivo
    const mappedFiles = acceptedFiles.map(file => ({
      file,
      preview: URL.createObjectURL(file),
      status: 'pending', // pending, uploading, success, error
      url: null
    }));
    
    setFiles(prev => [...prev, ...mappedFiles]);
  }, []);

  const removeFile = (indexToRemove) => {
    setFiles(files.filter((_, index) => index !== indexToRemove));
  };

  const uploadToCloudinary = async (fileObj) => {
    const formData = new FormData();
    formData.append('file', fileObj.file);
    formData.append('upload_preset', CLOUDINARY_UPLOAD_PRESET);

    try {
      const response = await axios.post(
        `https://api.cloudinary.com/v1_1/${CLOUDINARY_CLOUD_NAME}/image/upload`,
        formData
      );
      return response.data.secure_url;
    } catch (err) {
      console.error("Error subiendo a Cloudinary:", err);
      throw err;
    }
  };

  const saveToLaravel = async (urls) => {
    try {
      await axios.post(`${API_URL}/api/memories/bulk`, {
        urls: urls
      });
    } catch (err) {
      console.error("Error guardando en Laravel:", err);
      throw err;
    }
  };

  const handleUpload = async () => {
    if (files.length === 0) return;
    
    setUploading(true);
    setError(null);
    setProgress(0);
    
    const uploadedUrls = [];
    const newFilesState = [...files];

    try {
      for (let i = 0; i < files.length; i++) {
        if (files[i].status === 'success') {
          uploadedUrls.push(files[i].url);
          continue;
        }

        newFilesState[i].status = 'uploading';
        setFiles([...newFilesState]);

        const url = await uploadToCloudinary(files[i]);
        
        uploadedUrls.push(url);
        newFilesState[i].status = 'success';
        newFilesState[i].url = url;
        setFiles([...newFilesState]);
        
        setProgress(Math.round(((i + 1) / files.length) * 100));
      }

      // 2. Guardar en Laravel
      setProgress(99); // Casi listo
      await saveToLaravel(uploadedUrls);
      
      setProgress(100);
      setSuccess(true);
      if (onUploadComplete) onUploadComplete();
      
    } catch (err) {
      setError("Ocurrió un error durante la subida. Revisa las configuraciones de Cloudinary.");
    } finally {
      setUploading(false);
    }
  };

  const { getRootProps, getInputProps, isDragActive } = useDropzone({ 
    onDrop, 
    accept: {
      'image/*': ['.jpeg', '.jpg', '.png', '.gif', '.webp']
    }
  });

  return (
    <div className="bg-white/90 backdrop-blur-md rounded-2xl shadow-xl p-8 max-w-3xl w-full mx-auto relative border border-white/20">
      {onClose && (
        <button onClick={onClose} className="absolute top-4 right-4 text-gray-500 hover:text-gray-800">
          <X className="w-6 h-6" />
        </button>
      )}

      <div className="text-center mb-8">
        <h2 className="text-3xl font-bold text-gray-800 mb-2 font-serif">Carga Masiva de Recuerdos</h2>
        <p className="text-gray-600">Sube múltiples fotos de una sola vez. Se optimizarán automáticamente.</p>
      </div>

      {!success ? (
        <>
          {/* Dropzone */}
          <div 
            {...getRootProps()} 
            className={`border-4 border-dashed rounded-xl p-12 text-center cursor-pointer transition-all duration-300 ${
              isDragActive ? 'border-pink-500 bg-pink-50/50 scale-[1.02]' : 'border-gray-300 hover:border-pink-400 hover:bg-gray-50'
            }`}
          >
            <input {...getInputProps()} />
            <UploadCloud className={`w-16 h-16 mx-auto mb-4 ${isDragActive ? 'text-pink-500 animate-bounce' : 'text-gray-400'}`} />
            {isDragActive ? (
              <p className="text-xl text-pink-600 font-bold">¡Suelta las fotos aquí!</p>
            ) : (
              <div>
                <p className="text-xl text-gray-700 font-semibold mb-2">Arrastra tus fotos aquí</p>
                <p className="text-gray-500">o haz clic para seleccionar archivos</p>
              </div>
            )}
          </div>

          {/* Previsualización */}
          {files.length > 0 && (
            <div className="mt-8">
              <h3 className="text-lg font-semibold text-gray-700 mb-4">Fotos seleccionadas ({files.length})</h3>
              <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 max-h-64 overflow-y-auto p-2">
                {files.map((f, i) => (
                  <div key={i} className="relative group rounded-lg overflow-hidden shadow-sm border border-gray-200">
                    <img src={f.preview} alt="preview" className="w-full h-24 object-cover" />
                    
                    {/* Botón de eliminar (solo si no está subiendo) */}
                    {!uploading && f.status !== 'success' && (
                      <button 
                        onClick={() => removeFile(i)}
                        className="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity"
                      >
                        <X className="w-4 h-4" />
                      </button>
                    )}

                    {/* Estados */}
                    {f.status === 'uploading' && (
                      <div className="absolute inset-0 bg-black/50 flex items-center justify-center">
                        <Loader2 className="w-8 h-8 text-white animate-spin" />
                      </div>
                    )}
                    {f.status === 'success' && (
                      <div className="absolute inset-0 bg-green-500/30 flex items-center justify-center">
                        <CheckCircle className="w-8 h-8 text-white drop-shadow-md" />
                      </div>
                    )}
                  </div>
                ))}
              </div>

              {/* Controles de Subida */}
              <div className="mt-8 flex flex-col items-center">
                {error && (
                  <div className="flex items-center gap-2 text-red-600 mb-4 bg-red-50 p-3 rounded-lg w-full">
                    <AlertCircle className="w-5 h-5 flex-shrink-0" />
                    <p className="text-sm">{error}</p>
                  </div>
                )}
                
                {uploading && (
                  <div className="w-full mb-4">
                    <div className="flex justify-between text-sm text-gray-600 mb-1">
                      <span>Subiendo fotos...</span>
                      <span>{progress}%</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2.5">
                      <div className="bg-pink-500 h-2.5 rounded-full transition-all duration-300" style={{ width: `${progress}%` }}></div>
                    </div>
                  </div>
                )}

                <button 
                  onClick={handleUpload}
                  disabled={uploading}
                  className={`w-full sm:w-auto px-8 py-3 rounded-xl text-white font-bold text-lg shadow-lg transition-all ${
                    uploading ? 'bg-gray-400 cursor-not-allowed' : 'bg-gradient-to-r from-pink-500 to-rose-500 hover:shadow-pink-500/25 hover:scale-105 active:scale-95'
                  }`}
                >
                  {uploading ? (
                    <span className="flex items-center gap-2 justify-center">
                      <Loader2 className="w-5 h-5 animate-spin" /> Procesando...
                    </span>
                  ) : (
                    `Subir ${files.length} foto${files.length > 1 ? 's' : ''}`
                  )}
                </button>
              </div>
            </div>
          )}
        </>
      ) : (
        /* Pantalla de Éxito */
        <div className="text-center py-12 animate-in fade-in zoom-in duration-500">
          <div className="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <CheckCircle className="w-12 h-12 text-green-500" />
          </div>
          <h3 className="text-3xl font-bold text-gray-800 mb-2">¡Completado!</h3>
          <p className="text-gray-600 mb-8">Tus fotos se han subido a Cloudinary y se han guardado en tu panel de administración.</p>
          <button 
            onClick={() => {
              setSuccess(false);
              setFiles([]);
            }}
            className="px-6 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold rounded-lg transition-colors"
          >
            Subir más fotos
          </button>
        </div>
      )}
    </div>
  );
}
