import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import api from '../api/http';
import T2FHeader from '../components/T2FHeader';
import T2FNavbar from '../components/T2FNavbar';
import T2FCard from '../components/T2FCard';
import T2FButton from '../components/T2FButton';
import './PlanView.css';

const PlanView = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const [plan, setPlan] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (id) {
      loadPlan();
    }
  }, [id]);

  const loadPlan = async () => {
    try {
      const response = await api.get(`/plans/${id}`);
      if (response.data.success) {
        setPlan(response.data.data);
      }
    } catch (error) {
      console.error('Error loading plan:', error);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="plan-view">
        <T2FHeader title="Scheda" />
        <div style={{ padding: '2rem', textAlign: 'center' }}>Caricamento...</div>
        <T2FNavbar />
      </div>
    );
  }

  if (!plan) {
    return (
      <div className="plan-view">
        <T2FHeader title="Scheda" />
        <div style={{ padding: '2rem', textAlign: 'center' }}>Scheda non trovata</div>
        <T2FNavbar />
      </div>
    );
  }

  return (
    <div className="plan-view">
      <T2FHeader title="Scheda Attiva" />
      <main className="plan-view-content">
        <T2FCard className="plan-summary-card">
          <h2>{plan.name}</h2>
          {plan.isExpired && (
            <p style={{ color: 'var(--t2f-accent-red)' }}>
              Scheda scaduta il {new Date(plan.expiresAt).toLocaleDateString('it-IT')}
            </p>
          )}
          <p>Istruttore: {plan.instructorName}</p>
        </T2FCard>

        {plan.exercises?.map((exercise, index) => (
          <T2FCard key={exercise.id} className="exercise-card">
            <div className="exercise-card-content">
              {exercise.exerciseMediaUrl && (
                <img src={exercise.exerciseMediaUrl} alt={exercise.exerciseName} className="exercise-image" />
              )}
              <div className="exercise-info">
                <h3>{exercise.exerciseName}</h3>
                <p>{exercise.sets} x {exercise.reps} · {exercise.weight ? `${exercise.weight} kg` : 'Peso libero'}</p>
                {exercise.restSeconds && <p className="exercise-rest">Riposo: {exercise.restSeconds}s</p>}
              </div>
            </div>
            <T2FButton
              onClick={() => navigate(`/client/progress/${exercise.id}`)}
              size="small"
              style={{ marginTop: '0.5rem' }}
            >
              Registra Progresso
            </T2FButton>
          </T2FCard>
        ))}
      </main>
      <T2FNavbar />
    </div>
  );
};

export default PlanView;

