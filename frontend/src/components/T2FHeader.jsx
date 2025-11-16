import React from 'react';
import { useAuth } from '../contexts/AuthContext';
import './T2FHeader.css';

const T2FHeader = ({ title }) => {
  const { user, logout } = useAuth();

  return (
    <header className="t2f-header">
      <div className="t2f-header-content">
        <div className="t2f-header-logo">
          <img src="/logo-time2fit.png" alt="Time2Fit" className="t2f-logo" />
        </div>
        {title && <h1 className="t2f-header-title">{title}</h1>}
        {user && (
          <div className="t2f-header-user">
            <span>{user.firstName || user.email}</span>
            <button onClick={logout} className="t2f-header-logout">Logout</button>
          </div>
        )}
      </div>
    </header>
  );
};

export default T2FHeader;

