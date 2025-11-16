import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../api/http';
import T2FButton from '../components/T2FButton';
import T2FInput from '../components/T2FInput';
import T2FCard from '../components/T2FCard';
import './Register.css';

const Register = () => {
  const [formData, setFormData] = useState({
    email: '',
    password: '',
    firstName: '',
    lastName: '',
  });
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      const response = await api.post('/register', formData);
      if (response.data.success) {
        navigate('/login');
      } else {
        setError(response.data.message || 'Errore durante la registrazione');
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Errore durante la registrazione');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="register-page">
      <div className="register-container">
        <img src="/logo-time2fit.png" alt="Time2Fit" className="register-logo" />
        <T2FCard className="register-card">
          <h1>Registrati</h1>
          <form onSubmit={handleSubmit}>
            <T2FInput
              type="email"
              label="Email"
              value={formData.email}
              onChange={(e) => setFormData({ ...formData, email: e.target.value })}
              required
            />
            <T2FInput
              type="password"
              label="Password"
              value={formData.password}
              onChange={(e) => setFormData({ ...formData, password: e.target.value })}
              required
            />
            <T2FInput
              type="text"
              label="Nome"
              value={formData.firstName}
              onChange={(e) => setFormData({ ...formData, firstName: e.target.value })}
            />
            <T2FInput
              type="text"
              label="Cognome"
              value={formData.lastName}
              onChange={(e) => setFormData({ ...formData, lastName: e.target.value })}
            />
            {error && <div className="register-error">{error}</div>}
            <T2FButton type="submit" size="large" style={{ width: '100%', marginTop: '1rem' }} disabled={loading}>
              {loading ? 'Registrazione...' : 'Registrati'}
            </T2FButton>
          </form>
          <p style={{ marginTop: '1rem', textAlign: 'center' }}>
            Hai già un account? <a href="/login">Accedi</a>
          </p>
        </T2FCard>
      </div>
    </div>
  );
};

export default Register;

