import React from 'react';
import T2FHeader from '../components/T2FHeader';
import T2FNavbar from '../components/T2FNavbar';
import T2FCard from '../components/T2FCard';
import './DashboardAdmin.css';

const DashboardAdmin = () => {
  return (
    <div className="dashboard-admin">
      <T2FHeader title="Dashboard Admin" />
      <main className="dashboard-admin-content">
        <T2FCard>
          <h2>Pannello Amministrazione</h2>
          <p>Gestisci esercizi, set, istruttori e configurazioni.</p>
        </T2FCard>
      </main>
      <T2FNavbar />
    </div>
  );
};

export default DashboardAdmin;

