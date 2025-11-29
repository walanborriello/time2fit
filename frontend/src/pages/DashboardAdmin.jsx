import React, { useState, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import T2FHeader from '../components/T2FHeader';
import T2FNavbar from '../components/T2FNavbar';
import T2FCard from '../components/T2FCard';
import T2FButton from '../components/T2FButton';
import api from '../api/http';
import './DashboardAdmin.css';

const DashboardAdmin = () => {
  const navigate = useNavigate();
  const [activeTab, setActiveTab] = useState('sets');
  const [exerciseSets, setExerciseSets] = useState([]);
  const [clients, setClients] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const loadDataRef = useRef(false);

  useEffect(() => {
    if (loadDataRef.current) {
      loadData();
    } else {
      loadDataRef.current = true;
      loadData();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeTab]);

  const loadData = async () => {
    setLoading(true);
    setError('');
    try {
      if (activeTab === 'sets') {
        const res = await api.get('/exercise-sets');
        setExerciseSets(res.data.data || []);
      } else if (activeTab === 'clients') {
        const res = await api.get('/clients');
        setClients(res.data.data || []);
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Errore nel caricamento dati');
    } finally {
      setLoading(false);
    }
  };


  const handleDeleteSet = async (id) => {
    if (!window.confirm('Sei sicuro di voler eliminare questo set?')) return;
    try {
      await api.delete(`/exercise-sets/${id}`);
      loadData();
    } catch (err) {
      setError(err.response?.data?.message || 'Errore nell\'eliminazione');
    }
  };


  const getGymTypeLabel = (gymType) => {
    return gymType === 'ISOTONICA' ? 'Isotonica' : 'Funzionale';
  };


  return (
    <div className="dashboard-base dashboard-admin">
      <T2FHeader title="Dashboard Admin" />
      <main className="dashboard-content">
        <div className="tabs">
          <button
            className={`tab ${activeTab === 'sets' ? 'active' : ''}`}
            onClick={() => setActiveTab('sets')}
          >
            Set Esercizi
          </button>
          <button
            className={`tab ${activeTab === 'clients' ? 'active' : ''}`}
            onClick={() => setActiveTab('clients')}
          >
            Clienti
          </button>
          <button
            className={`tab ${activeTab === 'instructors' ? 'active' : ''}`}
            onClick={() => setActiveTab('instructors')}
          >
            Istruttori
          </button>
        </div>

        {error && (
          <T2FCard className="error-card">
            <p style={{ color: '#ed3833' }}>{error}</p>
          </T2FCard>
        )}

        {loading && (
          <T2FCard>
            <p>Caricamento...</p>
          </T2FCard>
        )}

        {activeTab === 'sets' && (
          <div className="admin-section">
            <div className="section-header">
              <h2>Gestione Set Esercizi</h2>
              <T2FButton onClick={() => navigate('/admin/exercise-sets/new')}>
                + Nuovo Set
              </T2FButton>
            </div>

            {exerciseSets.length === 0 ? (
              <T2FCard>
                <p>Nessun set presente. Crea il primo set!</p>
              </T2FCard>
            ) : (
              <div className="list-base">
                {exerciseSets.map((set) => (
                  <T2FCard key={set.id} style={{ marginBottom: '1.5rem' }}>
                    <div className="list-item" style={{ borderBottom: '1px solid #333', paddingBottom: '1rem', marginBottom: '1rem' }}>
                      <div className="list-item-content">
                        <h3>{set.name}</h3>
                        <p className="meta-text">
                          <span className={`gym-type-badge ${set.gymType === 'ISOTONICA' ? 'gym-type-isotonica' : 'gym-type-funzionale'}`}>
                            {getGymTypeLabel(set.gymType)}
                          </span>
                        </p>
                        {set.description && (
                          <p className="meta-text" style={{ marginTop: '0.5rem' }}>{set.description}</p>
                        )}
                      </div>
                      <div className="list-item-actions">
                        <T2FButton size="small" onClick={() => navigate(`/admin/exercise-sets/${set.id}/edit`)}>
                          Modifica
                        </T2FButton>
                        <T2FButton
                          size="small"
                          style={{ backgroundColor: '#ed3833' }}
                          onClick={() => handleDeleteSet(set.id)}
                        >
                          Elimina
                        </T2FButton>
                      </div>
                    </div>
                    
                    {/* Esercizi del set */}
                    {set.exercises && set.exercises.length > 0 ? (
                      <div>
                        <h4 style={{ color: 'var(--t2f-title)', marginBottom: '0.75rem', fontSize: '1rem' }}>
                          Esercizi ({set.exercises.length})
                        </h4>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
                          {set.exercises.map((exercise) => (
                            <div key={exercise.id} style={{ 
                              padding: '0.75rem', 
                              background: '#1a1a1a', 
                              borderRadius: '8px',
                              border: '1px solid #333'
                            }}>
                              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'start', gap: '1rem' }}>
                                <div style={{ flex: 1 }}>
                                  <strong style={{ color: 'var(--t2f-text)' }}>{exercise.name}</strong>
                                  {exercise.muscleGroup && (
                                    <p className="meta-text" style={{ marginTop: '0.25rem' }}>
                                      Muscoli: {exercise.muscleGroup}
                                    </p>
                                  )}
                                  {exercise.description && (
                                    <p className="meta-text" style={{ marginTop: '0.25rem', fontSize: '0.85rem' }}>
                                      {exercise.description.substring(0, 100)}{exercise.description.length > 100 ? '...' : ''}
                                    </p>
                                  )}
                                </div>
                                {exercise.mediaUrl && (
                                  <span className="meta-text" style={{ fontSize: '0.75rem' }}>📹</span>
                                )}
                              </div>
                            </div>
                          ))}
                        </div>
                      </div>
                    ) : (
                      <p className="meta-text" style={{ fontStyle: 'italic', padding: '0.5rem 0' }}>
                        Nessun esercizio in questo set
                      </p>
                    )}
                  </T2FCard>
                ))}
              </div>
            )}
          </div>
        )}

        {activeTab === 'clients' && (
          <div className="admin-section">
            <div className="section-header">
              <h2>Gestione Clienti</h2>
            </div>
            {clients.length === 0 ? (
              <T2FCard>
                <p>Nessun cliente presente.</p>
              </T2FCard>
            ) : (
              <div className="list-base">
                {clients.map((client) => (
                  <T2FCard key={client.id} className="list-item">
                    <div className="list-item-content">
                      <h3>{client.fullName || `${client.firstName} ${client.lastName}`}</h3>
                      <p className="meta-text">Email: {client.email}</p>
                      {client.hasUser && <p className="meta-text">✓ Account registrato</p>}
                    </div>
                  </T2FCard>
                ))}
              </div>
            )}
          </div>
        )}

        {activeTab === 'instructors' && (
          <div className="admin-section">
            <div className="section-header">
              <h2>Gestione Istruttori</h2>
            </div>
            <T2FCard>
              <p>Funzionalità in sviluppo. Qui verranno mostrati tutti gli istruttori.</p>
            </T2FCard>
          </div>
        )}
      </main>
      <T2FNavbar />
    </div>
  );
};

export default DashboardAdmin;
