import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import api from '../api/http';
import T2FHeader from '../components/T2FHeader';
import T2FNavbar from '../components/T2FNavbar';
import T2FCard from '../components/T2FCard';
import T2FButton from '../components/T2FButton';
import './DashboardClient.css';

const DashboardClient = () => {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [activePlan, setActivePlan] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadActivePlan();
  }, []);

  const loadActivePlan = async () => {
    try {
      if (user?.clientId) {
        const response = await api.get(`/clients/${user.clientId}/plans/active`);
        if (response.data.success) {
          setActivePlan(response.data.data);
        }
      }
    } catch (error) {
      console.error('Error loading plan:', error);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="dashboard-client">
        <T2FHeader />
        <div style={{ padding: '2rem', textAlign: 'center' }}>Caricamento...</div>
        <T2FNavbar />
      </div>
    );
  }

  return (
    <div className="dashboard-client">
      <T2FHeader />
      <main className="dashboard-client-content">
        <div className="dashboard-client-greeting">
          <h1>Ciao, {user?.firstName || user?.email} 👋</h1>
          <p>Ecco la tua scheda attiva di oggi</p>
        </div>

        {activePlan ? (
          <>
            <T2FCard className="dashboard-client-plan-card">
              <div className="plan-card-header">
                <h2>{activePlan.name}</h2>
                <span className="plan-badge">ATTIVA</span>
              </div>
              <p className="plan-info">
                Scade il {new Date(activePlan.expiresAt).toLocaleDateString('it-IT')} · {activePlan.exercises?.length || 0} esercizi
              </p>
              <T2FButton onClick={() => navigate(`/client/plan/${activePlan.id}`)} style={{ width: '100%', marginTop: '1rem' }}>
                Vedi Scheda Completa
              </T2FButton>
            </T2FCard>

            {activePlan.exercises?.slice(0, 3).map((exercise) => (
              <T2FCard key={exercise.id} className="exercise-preview-card">
                <div className="exercise-preview-content">
                  {exercise.exerciseMediaUrl && (
                    <img src={exercise.exerciseMediaUrl} alt={exercise.exerciseName} className="exercise-preview-image" />
                  )}
                  <div className="exercise-preview-info">
                    <h3>{exercise.exerciseName}</h3>
                    <p>{exercise.sets} x {exercise.reps} · {exercise.weight ? `${exercise.weight} kg` : 'Peso libero'}</p>
                  </div>
                </div>
              </T2FCard>
            ))}
          </>
        ) : (
          <T2FCard>
            <p>Nessuna scheda attiva al momento.</p>
          </T2FCard>
        )}
      </main>
      <T2FNavbar />
    </div>
  );
};

export default DashboardClient;

