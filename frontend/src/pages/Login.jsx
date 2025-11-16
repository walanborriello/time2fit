import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import T2FButton from '../components/T2FButton';
import T2FInput from '../components/T2FInput';
import T2FCard from '../components/T2FCard';
import './Login.css';

const Login = () => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const { login } = useAuth();
  const navigate = useNavigate();

  const { user: authUser } = useAuth();

  useEffect(() => {
    if (authUser) {
      // Redirect based on role
      if (authUser.roles?.includes('ROLE_CLIENT')) {
        navigate('/client/dashboard');
      } else if (authUser.roles?.includes('ROLE_INSTRUCTOR') || authUser.roles?.includes('ROLE_INSTRUCTOR_PT')) {
        navigate('/instructor/dashboard');
      } else if (authUser.roles?.includes('ROLE_ADMIN')) {
        navigate('/admin/dashboard');
      } else {
        navigate('/client/dashboard');
      }
    }
  }, [authUser, navigate]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setLoading(true);

    const result = await login(email, password);
    
    if (!result.success) {
      setError(result.message || 'Credenziali non valide');
      setLoading(false);
    }
    // Redirect handled by useEffect when user is set
  };

  return (
    <div className="login-page">
      <div className="login-container">
        <img src="/logo-time2fit.png" alt="Time2Fit" className="login-logo" />
        <T2FCard className="login-card">
          <h1>Accedi</h1>
          <form onSubmit={handleSubmit}>
            <T2FInput
              type="email"
              label="Email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
            />
            <T2FInput
              type="password"
              label="Password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
            {error && <div className="login-error">{error}</div>}
            <T2FButton type="submit" size="large" style={{ width: '100%', marginTop: '1rem' }} disabled={loading}>
              {loading ? 'Accesso...' : 'Accedi'}
            </T2FButton>
          </form>
          <p style={{ marginTop: '1rem', textAlign: 'center' }}>
            Non hai un account? <a href="/register">Registrati</a>
          </p>
        </T2FCard>
      </div>
    </div>
  );
};

export default Login;

