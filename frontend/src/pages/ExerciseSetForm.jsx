import React, { useState, useEffect, useMemo, useRef } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import T2FHeader from '../components/T2FHeader';
import T2FCard from '../components/T2FCard';
import T2FButton from '../components/T2FButton';
import T2FInput from '../components/T2FInput';
import T2FTextarea from '../components/T2FTextarea';
import api from '../api/http';
import './ExerciseSetForm.css';

const ExerciseSetForm = () => {
  const { id: urlId } = useParams();
  const navigate = useNavigate();
  const [createdSetId, setCreatedSetId] = useState(null);
  
  const actualId = useMemo(() => createdSetId || urlId, [createdSetId, urlId]);
  const isEdit = useMemo(() => !!actualId && actualId !== 'new', [actualId]);

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [setData, setSetData] = useState({
    name: '',
    gymType: 'ISOTONICA',
    description: '',
  });
  const [exercises, setExercises] = useState([]);
  const [generatingDescription, setGeneratingDescription] = useState({});
  const [retryCountdown, setRetryCountdown] = useState({});
  const [showGeneratingModal, setShowGeneratingModal] = useState(false);
  const [generatingStatus, setGeneratingStatus] = useState({ message: '', elapsed: 0 });
  const countdownIntervals = React.useRef({});

  useEffect(() => {
    if (isEdit && actualId && actualId !== 'new') {
      loadSet();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [actualId]);

  // Cleanup degli interval quando il componente viene smontato
  useEffect(() => {
    return () => {
      Object.values(countdownIntervals.current).forEach(interval => {
        if (interval) clearInterval(interval);
      });
      countdownIntervals.current = {};
    };
  }, []);

  const loadSet = async () => {
    if (!actualId || actualId === 'new') return;
    setLoading(true);
    try {
      const res = await api.get(`/exercise-sets/${actualId}`);
      if (res.data.success) {
        const data = res.data.data;
        setSetData({
          name: data.name || '',
          gymType: data.gymType || 'ISOTONICA',
          description: data.description || '',
        });
        setExercises(data.exercises || []);
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Errore nel caricamento set');
    } finally {
      setLoading(false);
    }
  };

  const handleSetChange = (field, value) => {
    setSetData({ ...setData, [field]: value });
  };

  const addExercise = () => {
    setExercises([...exercises, {
      id: null,
      name: '',
      description: '',
      aiPrompt: '',
      mediaUrl: '',
      mediaType: null,
      muscleGroup: '',
    }]);
  };

  const removeExercise = (index) => {
    setExercises(exercises.filter((_, i) => i !== index));
  };

  const updateExercise = (index, field, value) => {
    if (index < 0 || index >= exercises.length) {
      console.error('Invalid exercise index:', index);
      return;
    }
    try {
      const updated = [...exercises];
      updated[index] = { ...updated[index], [field]: value };
      setExercises(updated);
    } catch (err) {
      console.error('Error updating exercise:', err);
    }
  };

  const handleGenerateDescription = async (index) => {
    const exercise = exercises[index];
    if (!exercise.name && !exercise.aiPrompt) {
      alert('Inserisci almeno il nome dell\'esercizio o uno script/prompt');
      return;
    }

    // Pulisci eventuali interval precedenti
    if (countdownIntervals.current[index]) {
      clearInterval(countdownIntervals.current[index]);
      delete countdownIntervals.current[index];
    }

    // MOSTRA IL MODAL SUBITO
    setShowGeneratingModal(true);
    setGeneratingDescription(prev => ({ ...prev, [index]: true }));
    setGeneratingStatus({ message: 'Generazione descrizione in corso...', elapsed: 0 });

    // Avvia un countdown visivo che mostra il tempo trascorso
    let elapsed = 0;
    const countdownInterval = setInterval(() => {
      elapsed++;
      setGeneratingStatus(prev => ({ 
        message: prev.message || 'Generazione descrizione in corso...',
        elapsed: elapsed
      }));
    }, 1000);
    countdownIntervals.current[index] = countdownInterval;

    try {
      const exerciseId = exercise.id;
      let res;
      
      if (exerciseId) {
        // Esercizio già salvato - usa endpoint con ID
        res = await api.post(`/exercises/${exerciseId}/generate-description`, {
          prompt: exercise.aiPrompt || exercise.name,
          muscleGroup: exercise.muscleGroup,
        }, {
          timeout: 90000
        });
      } else {
        // Esercizio non salvato - usa endpoint generico
        res = await api.post('/exercises/generate-description', {
          name: exercise.name || exercise.aiPrompt,
          prompt: exercise.aiPrompt || exercise.name,
          muscleGroup: exercise.muscleGroup,
        }, {
          timeout: 90000
        });
      }
      
      // Pulisci interval
      if (countdownIntervals.current[index]) {
        clearInterval(countdownIntervals.current[index]);
        delete countdownIntervals.current[index];
      }
      
      if (res.data.success) {
        updateExercise(index, 'description', res.data.description);
        setGeneratingStatus({ message: '✅ Descrizione generata con successo!', elapsed: elapsed });
        setTimeout(() => {
          setShowGeneratingModal(false);
          setGeneratingStatus({ message: '', elapsed: 0 });
          setGeneratingDescription(prev => ({ ...prev, [index]: false }));
        }, 1500);
      }
    } catch (err) {
      // Pulisci interval
      if (countdownIntervals.current[index]) {
        clearInterval(countdownIntervals.current[index]);
        delete countdownIntervals.current[index];
      }
      
      const errorMsg = err.response?.data?.message || 'Errore nella generazione descrizione';
      setGeneratingStatus({ message: `❌ Errore: ${errorMsg}`, elapsed: elapsed });
      
      // Mostra l'errore nel modal per 5 secondi prima di chiudere
      setTimeout(() => {
        setShowGeneratingModal(false);
        setGeneratingStatus({ message: '', elapsed: 0 });
        setGeneratingDescription(prev => ({ ...prev, [index]: false }));
        setRetryCountdown(prev => ({ ...prev, [index]: null }));
      }, 5000);
    }
  };


  const handleGenerateGif = async (index) => {
    const exercise = exercises[index];
    if (!exercise.description && !exercise.name) {
      alert('Inserisci almeno una descrizione o il nome dell\'esercizio');
      return;
    }

    try {
      const exerciseId = exercise.id;
      if (!exerciseId) {
        alert('Salva prima l\'esercizio per generare la GIF');
        return;
      }

      const res = await api.post(`/exercises/${exerciseId}/generate-gif`);
      if (res.data.success) {
        updateExercise(index, 'mediaUrl', res.data.mediaUrl);
        updateExercise(index, 'mediaType', 'GIF');
      }
    } catch (err) {
      alert(err.response?.data?.message || 'Errore nella generazione GIF');
    }
  };

  const handleFileUpload = (index, file) => {
    // Per ora, solo preview locale. In produzione, upload su server
    if (file) {
      const reader = new FileReader();
      reader.onload = (e) => {
        updateExercise(index, 'mediaUrl', e.target.result);
        updateExercise(index, 'mediaType', file.type.startsWith('video/') ? 'VIDEO' : 'GIF');
      };
      reader.readAsDataURL(file);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');

    try {
      const payload = {
        ...setData,
        exercises: exercises.map(ex => ({
          id: ex.id || undefined,
          name: ex.name,
          description: ex.description,
          aiPrompt: ex.aiPrompt,
          mediaUrl: ex.mediaUrl,
          mediaType: ex.mediaType,
          muscleGroup: ex.muscleGroup,
        })),
      };

      let res;
      if (isEdit && actualId) {
        res = await api.put(`/exercise-sets/${actualId}`, payload);
      } else {
        res = await api.post('/exercise-sets', payload);
      }

      if (res.data.success) {
        const wasNew = !isEdit;
        if (wasNew) {
          // Se è una creazione, rimani sulla pagina in modalità edit per aggiungere esercizi
          const setId = res.data.data.id;
          setCreatedSetId(setId);
          // Aggiorna l'URL senza ricaricare
          window.history.replaceState({}, '', `/admin/exercise-sets/${setId}/edit`);
          // Ricarica i dati del set appena creato
          try {
            const loadRes = await api.get(`/exercise-sets/${setId}`);
            if (loadRes.data.success) {
              const data = loadRes.data.data;
              setSetData({
                name: data.name || '',
                gymType: data.gymType || 'ISOTONICA',
                description: data.description || '',
              });
              setExercises(data.exercises || []);
              setError('');
            }
          } catch (loadErr) {
            console.error('Errore nel caricamento set:', loadErr);
            setError('Set creato ma errore nel caricamento dati');
          }
        } else {
          // Se è una modifica, ricarica i dati
          await loadSet();
          setError('');
        }
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Errore nel salvataggio');
    } finally {
      setLoading(false);
    }
  };

  if (loading && isEdit && !createdSetId && urlId && urlId !== 'new') {
    return (
      <div className="exercise-set-form">
        <T2FHeader />
        <div className="exercise-set-form-loading">Caricamento...</div>
      </div>
    );
  }

  return (
    <div className="exercise-set-form">
      <T2FHeader />
      
      {/* Modal per generazione descrizione */}
      {showGeneratingModal && (
        <div 
          style={{
            position: 'fixed',
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
            backgroundColor: 'rgba(0, 0, 0, 0.9)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            zIndex: 99999
          }}
          onClick={(e) => {
            // Non chiudere cliccando fuori durante la generazione
            if (generatingStatus.message.includes('✅') || generatingStatus.message.includes('❌')) {
              setShowGeneratingModal(false);
            }
          }}
        >
          <div 
            style={{
              backgroundColor: '#1a1a1a',
              border: '3px solid #00ff00',
              borderRadius: '12px',
              padding: '3rem',
              minWidth: '450px',
              maxWidth: '600px',
              textAlign: 'center',
              boxShadow: '0 0 30px rgba(0, 255, 0, 0.5)'
            }}
            onClick={(e) => e.stopPropagation()}
          >
            <h2 style={{ 
              color: '#00ff00', 
              marginBottom: '1.5rem', 
              fontFamily: 'Lato, sans-serif',
              fontSize: '1.8rem'
            }}>
              ⏳ Generazione Descrizione
            </h2>
            <p style={{ 
              color: '#fff', 
              fontSize: '1.2rem', 
              marginBottom: '2rem', 
              fontFamily: 'Lato, sans-serif',
              minHeight: '30px'
            }}>
              {generatingStatus.message || 'Generazione descrizione in corso...'}
            </p>
            <div style={{ 
              fontSize: '4rem', 
              color: '#f9cc49', 
              fontWeight: 'bold',
              marginBottom: '2rem',
              fontFamily: 'Lato, sans-serif',
              textShadow: '0 0 10px rgba(249, 204, 73, 0.5)'
            }}>
              {generatingStatus.elapsed}s
            </div>
            {!generatingStatus.message.includes('✅') && !generatingStatus.message.includes('❌') && (
              <p style={{ 
                color: '#f9cc49', 
                fontSize: '0.95rem', 
                fontStyle: 'italic',
                marginTop: '1rem',
                fontFamily: 'Lato, sans-serif',
                lineHeight: '1.6'
              }}>
                In caso di rate limit, il sistema riproverà automaticamente<br />
                (fino a 3 tentativi con attese di 2s, 4s, 8s)...
              </p>
            )}
          </div>
        </div>
      )}

      <div className="exercise-set-form-container">
        <h1>{isEdit ? (createdSetId ? 'Set creato - Aggiungi esercizi' : 'Modifica Set di Esercizi') : 'Nuovo Set di Esercizi'}</h1>

        {error && <div className="exercise-set-form-error">{error}</div>}

        <form onSubmit={handleSubmit}>
          {/* Blocco 1: Dati Set (2 colonne) */}
          <T2FCard className="exercise-set-form-block">
            <h2>Dati Set</h2>
            <div className="exercise-set-form-row">
              <div className="exercise-set-form-col">
                <T2FInput
                  label="Nome set *"
                  value={setData.name}
                  onChange={(e) => handleSetChange('name', e.target.value)}
                  required
                />
                <div className="t2f-input-wrapper">
                  <label className="t2f-input-label">Tipologia palestra *</label>
                  <select
                    className="t2f-input"
                    value={setData.gymType}
                    onChange={(e) => handleSetChange('gymType', e.target.value)}
                    required
                  >
                    <option value="ISOTONICA">Isotonica</option>
                    <option value="FUNZIONALE">Funzionale</option>
                  </select>
                </div>
              </div>
              <div className="exercise-set-form-col">
                <T2FTextarea
                  label="Descrizione set"
                  value={setData.description}
                  onChange={(e) => handleSetChange('description', e.target.value)}
                  rows={4}
                />
              </div>
            </div>
          </T2FCard>

          {/* Blocco 2: Esercizi del Set */}
          <T2FCard className="exercise-set-form-block">
            <div className="exercise-set-form-exercises-header">
              <h2>Esercizi del set</h2>
              <T2FButton type="button" onClick={addExercise}>
                + Aggiungi esercizio
              </T2FButton>
            </div>

            {exercises.length === 0 && (
              <p className="exercise-set-form-empty">Nessun esercizio. Clicca su "Aggiungi esercizio" per iniziare.</p>
            )}

            {exercises.map((exercise, index) => (
              <T2FCard key={index} className="exercise-set-form-exercise-card">
                <div className="exercise-set-form-exercise-header">
                  <h3>Esercizio {index + 1}</h3>
                  <T2FButton
                    type="button"
                    onClick={() => removeExercise(index)}
                    style={{ backgroundColor: 'var(--t2f-accent-red)', fontSize: '0.875rem', padding: '0.5rem 1rem' }}
                  >
                    Elimina
                  </T2FButton>
                </div>

                <div className="exercise-set-form-row">
                  {/* Colonna sinistra: campi base */}
                  <div className="exercise-set-form-col">
                    <T2FInput
                      label="Nome esercizio *"
                      value={exercise.name}
                      onChange={(e) => updateExercise(index, 'name', e.target.value)}
                      required
                    />
                    <div className="exercise-set-form-params">
                      <T2FInput
                        label="Serie"
                        type="number"
                        value={exercise.sets || ''}
                        onChange={(e) => updateExercise(index, 'sets', e.target.value)}
                      />
                      <T2FInput
                        label="Ripetizioni"
                        type="number"
                        value={exercise.reps || ''}
                        onChange={(e) => updateExercise(index, 'reps', e.target.value)}
                      />
                      <T2FInput
                        label="Tempo (sec)"
                        type="number"
                        value={exercise.time || ''}
                        onChange={(e) => updateExercise(index, 'time', e.target.value)}
                      />
                    </div>
                    <T2FInput
                      label="Note brevi"
                      value={exercise.notes || ''}
                      onChange={(e) => updateExercise(index, 'notes', e.target.value)}
                    />
                  </div>

                  {/* Colonna destra: descrizione + media */}
                  <div className="exercise-set-form-col">
                    <T2FInput
                      label="Script/Prompt"
                      value={exercise.aiPrompt || ''}
                      onChange={(e) => updateExercise(index, 'aiPrompt', e.target.value)}
                      placeholder="Es: 'Panca piana con bilanciere'"
                    />
                    <T2FTextarea
                      label="Descrizione esercizio"
                      value={exercise.description || ''}
                      onChange={(e) => updateExercise(index, 'description', e.target.value)}
                      rows={4}
                    />
                    <div style={{ marginBottom: '1rem' }}>
                      <T2FButton
                        type="button"
                        onClick={() => {
                          // Previeni doppi click
                          if (generatingDescription[index] || showGeneratingModal) {
                            return;
                          }
                          handleGenerateDescription(index);
                        }}
                        disabled={generatingDescription[index] || showGeneratingModal}
                        style={{ 
                          fontSize: '0.875rem', 
                          padding: '0.5rem 1rem',
                          opacity: generatingDescription[index] ? 0.6 : 1,
                          cursor: generatingDescription[index] ? 'not-allowed' : 'pointer'
                        }}
                      >
                        {generatingDescription[index] ? (
                          <>⏳ Generazione in corso...</>
                        ) : (
                          <>🤖 Genera descrizione</>
                        )}
                      </T2FButton>
                    </div>

                    {/* Sezione Media */}
                    <div className="exercise-set-form-media">
                      <h4>Media</h4>
                      {exercise.mediaUrl && (
                        <div className="exercise-set-form-media-preview">
                          {exercise.mediaType === 'GIF' || exercise.mediaUrl.endsWith('.gif') ? (
                            <img src={exercise.mediaUrl} alt="Preview" />
                          ) : (
                            <video src={exercise.mediaUrl} controls />
                          )}
                        </div>
                      )}
                      <div className="exercise-set-form-media-actions">
                        <input
                          type="file"
                          accept="video/*,image/gif"
                          onChange={(e) => handleFileUpload(index, e.target.files[0])}
                          style={{ display: 'none' }}
                          id={`file-upload-${index}`}
                        />
                        <label htmlFor={`file-upload-${index}`} style={{ cursor: 'pointer' }}>
                          <T2FButton type="button" style={{ fontSize: '0.875rem', padding: '0.5rem 1rem', cursor: 'pointer' }}>
                            📁 Upload video/GIF
                          </T2FButton>
                        </label>
                        <T2FButton
                          type="button"
                          onClick={() => handleGenerateGif(index)}
                          disabled={!exercise.id}
                          style={{ fontSize: '0.875rem', padding: '0.5rem 1rem', opacity: !exercise.id ? 0.5 : 1 }}
                        >
                          🎬 Genera GIF esercizio
                        </T2FButton>
                      </div>
                    </div>
                  </div>
                </div>
              </T2FCard>
            ))}
          </T2FCard>

          <div className="exercise-set-form-actions">
            <T2FButton type="submit" disabled={loading}>
              {loading ? 'Salvataggio...' : (isEdit ? 'Aggiorna Set' : 'Crea Set')}
            </T2FButton>
            {isEdit && (
              <T2FButton type="button" onClick={() => navigate('/admin/dashboard')} style={{ backgroundColor: '#666' }}>
                Torna alla lista
              </T2FButton>
            )}
            {!isEdit && (
              <T2FButton type="button" onClick={() => navigate('/admin/dashboard')} style={{ backgroundColor: '#666' }}>
                Annulla
              </T2FButton>
            )}
          </div>
        </form>
      </div>
    </div>
  );
};

export default ExerciseSetForm;

