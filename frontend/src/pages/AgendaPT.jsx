import React from 'react';
import T2FHeader from '../components/T2FHeader';
import T2FNavbar from '../components/T2FNavbar';
import T2FCard from '../components/T2FCard';
import './AgendaPT.css';

const AgendaPT = () => {
  return (
    <div className="agenda-pt">
      <T2FHeader title="Agenda PT" />
      <main className="agenda-pt-content">
        <T2FCard>
          <h2>Agenda Personal Trainer</h2>
          <p>Gestisci i tuoi appuntamenti qui.</p>
        </T2FCard>
      </main>
      <T2FNavbar />
    </div>
  );
};

export default AgendaPT;

