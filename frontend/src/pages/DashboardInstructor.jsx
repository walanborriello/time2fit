import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import T2FHeader from '../components/T2FHeader';
import T2FNavbar from '../components/T2FNavbar';
import T2FCard from '../components/T2FCard';
import T2FButton from '../components/T2FButton';
import api from '../api/http';
import './DashboardInstructor.css';

const DashboardInstructor = () => {
  const navigate = useNavigate();
  const [clients, setClients] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [showCreateClient, setShowCreateClient] = useState(false);
  const [newClient, setNewClient] = useState({ email: '', firstName: '', lastName: '', phone: '' });

  useEffect(() => {
    loadClients();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const loadClients = async () => {
    setLoading(true);
    setError('');
    try {
      const res = await api.get('/clients');
      if (res.data.success) {
        setClients(res.data.data || []);
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Errore nel caricamento clienti');
    } finally {
      setLoading(false);
    }
  };

  const handleCreateClient = async (e) => {
    e.preventDefault();
    try {
      const res = await api.post('/clients', newClient);
      if (res.data.success) {
        setShowCreateClient(false);
        setNewClient({ email: '', firstName: '', lastName: '', phone: '' });
        loadClients();
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Errore nella creazione cliente');
    }
  };

  const handleCreatePlan = (clientId) => {
    navigate(`/instructor/plan/create?clientId=${clientId}`);
  };

  const handleViewPlans = (clientId) => {
    navigate(`/instructor/clients/${clientId}/plans`);
  };

  if (loading) {
    return (
      <div className="dashboard-instructor">
        <T2FHeader title="Dashboard Istruttore" />
        <div style={{ padding: '2rem', textAlign: 'center' }}>Caricamento...</div>
        <T2FNavbar />
      </div>
    );
  }

  return (
    <div className="dashboard-instructor">
      <T2FHeader title="Dashboard Istruttore" />
      <main className="dashboard-instructor-content">
        <div className="instructor-header">
          <h1>I Miei Clienti</h1>
          <T2FButton onClick={() => setShowCreateClient(!showCreateClient)}>
            {showCreateClient ? 'Annulla' : '+ Nuovo Cliente'}
          </T2FButton>
        </div>

        {error && (
          <T2FCard className="error-card">
            <p style={{ color: '#ed3833' }}>{error}</p>
          </T2FCard>
        )}

        {showCreateClient && (
          <T2FCard className="create-client-card">
            <h2>Nuovo Cliente</h2>
            <form onSubmit={handleCreateClient}>
              <div className="form-group">
                <label>Email *</label>
                <input
                  type="email"
                  value={newClient.email}
                  onChange={(e) => setNewClient({ ...newClient, email: e.target.value })}
                  required
                  className="form-input"
                />
              </div>
              <div className="form-group">
                <label>Nome</label>
                <input
                  type="text"
                  value={newClient.firstName}
                  onChange={(e) => setNewClient({ ...newClient, firstName: e.target.value })}
                  className="form-input"
                />
              </div>
              <div className="form-group">
                <label>Cognome</label>
                <input
                  type="text"
                  value={newClient.lastName}
                  onChange={(e) => setNewClient({ ...newClient, lastName: e.target.value })}
                  className="form-input"
                />
              </div>
              <div className="form-group">
                <label>Telefono</label>
                <input
                  type="tel"
                  value={newClient.phone}
                  onChange={(e) => setNewClient({ ...newClient, phone: e.target.value })}
                  className="form-input"
                />
              </div>
              <div className="form-actions">
                <T2FButton type="submit">Crea Cliente</T2FButton>
                <T2FButton type="button" onClick={() => setShowCreateClient(false)} style={{ backgroundColor: '#666' }}>
                  Annulla
                </T2FButton>
              </div>
            </form>
          </T2FCard>
        )}

        {clients.length === 0 ? (
          <T2FCard>
            <p>Nessun cliente presente. Crea il primo cliente!</p>
          </T2FCard>
        ) : (
          <div className="clients-list">
            {clients.map((client) => (
              <T2FCard key={client.id} className="client-card">
                <div className="client-card-header">
                  <div>
                    <h3>{client.fullName || `${client.firstName || ''} ${client.lastName || ''}`.trim() || 'Cliente'}</h3>
                    <p className="client-email">{client.email}</p>
                    {client.hasUser && <span className="client-badge">✓ Registrato</span>}
                  </div>
                </div>
                <div className="client-card-actions">
                  <T2FButton size="small" onClick={() => handleViewPlans(client.id)}>
                    📋 Schede
                  </T2FButton>
                  <T2FButton size="small" onClick={() => handleCreatePlan(client.id)}>
                    ➕ Nuova Scheda
                  </T2FButton>
                </div>
              </T2FCard>
            ))}
          </div>
        )}
      </main>
      <T2FNavbar />
    </div>
  );
};

export default DashboardInstructor;
