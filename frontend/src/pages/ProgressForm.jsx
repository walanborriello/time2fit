import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import api from '../api/http';
import T2FHeader from '../components/T2FHeader';
import T2FNavbar from '../components/T2FNavbar';
import T2FCard from '../components/T2FCard';
import T2FInput from '../components/T2FInput';
import T2FButton from '../components/T2FButton';
import './ProgressForm.css';

const ProgressForm = () => {
  const { tpeId } = useParams();
  const navigate = useNavigate();
  const [formData, setFormData] = useState({
    sets: '',
    reps: '',
    weight: '',
    notes: '',
  });
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);

    try {
      const response = await api.post(`/exercises/${tpeId}/progress`, {
        sets: parseInt(formData.sets),
        reps: parseInt(formData.reps),
        weight: formData.weight ? parseFloat(formData.weight) : null,
        notes: formData.notes || null,
      });

      if (response.data.success) {
        navigate(-1);
      }
    } catch (error) {
      console.error('Error saving progress:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="progress-form">
      <T2FHeader title="Registra Progresso" />
      <main className="progress-form-content">
        <T2FCard>
          <h2>Nuovo Progresso</h2>
          <form onSubmit={handleSubmit}>
            <T2FInput
              type="number"
              label="Serie"
              value={formData.sets}
              onChange={(e) => setFormData({ ...formData, sets: e.target.value })}
              required
            />
            <T2FInput
              type="number"
              label="Ripetizioni"
              value={formData.reps}
              onChange={(e) => setFormData({ ...formData, reps: e.target.value })}
              required
            />
            <T2FInput
              type="number"
              label="Peso (kg)"
              value={formData.weight}
              onChange={(e) => setFormData({ ...formData, weight: e.target.value })}
              step="0.5"
            />
            <T2FInput
              type="text"
              label="Note"
              value={formData.notes}
              onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
            />
            <T2FButton type="submit" size="large" style={{ width: '100%', marginTop: '1rem' }} disabled={loading}>
              {loading ? 'Salvataggio...' : 'Salva Progresso'}
            </T2FButton>
          </form>
        </T2FCard>
      </main>
      <T2FNavbar />
    </div>
  );
};

export default ProgressForm;

