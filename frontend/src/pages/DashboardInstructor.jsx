import React from 'react';
import T2FHeader from '../components/T2FHeader';
import T2FNavbar from '../components/T2FNavbar';
import T2FCard from '../components/T2FCard';
import './DashboardInstructor.css';

const DashboardInstructor = () => {
  return (
    <div className="dashboard-instructor">
      <T2FHeader title="Dashboard Istruttore" />
      <main className="dashboard-instructor-content">
        <T2FCard>
          <h2>Benvenuto, Istruttore</h2>
          <p>Gestisci clienti, schede e appuntamenti da qui.</p>
        </T2FCard>
      </main>
      <T2FNavbar />
    </div>
  );
};

export default DashboardInstructor;

