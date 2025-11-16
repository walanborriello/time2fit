import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import './T2FNavbar.css';

const T2FNavbar = () => {
  const location = useLocation();
  const { user, hasRole } = useAuth();

  if (!user) return null;

  const isActive = (path) => location.pathname.startsWith(path);

  const navItems = [];

  if (hasRole('ROLE_CLIENT')) {
    navItems.push(
      { path: '/client/dashboard', label: 'Home', icon: '🏠' },
      { path: '/client/plan', label: 'Scheda', icon: '📋' }
    );
  }

  if (hasRole('ROLE_INSTRUCTOR') || hasRole('ROLE_INSTRUCTOR_PT')) {
    navItems.push(
      { path: '/instructor/dashboard', label: 'Dashboard', icon: '📊' },
      { path: '/instructor/agenda', label: 'Agenda', icon: '📅' }
    );
  }

  if (hasRole('ROLE_ADMIN')) {
    navItems.push(
      { path: '/admin/dashboard', label: 'Admin', icon: '⚙️' }
    );
  }

  return (
    <nav className="t2f-navbar">
      {navItems.map((item) => (
        <Link
          key={item.path}
          to={item.path}
          className={`t2f-navbar-item ${isActive(item.path) ? 't2f-navbar-item--active' : ''}`}
        >
          <span className="t2f-navbar-icon">{item.icon}</span>
          <span className="t2f-navbar-label">{item.label}</span>
        </Link>
      ))}
    </nav>
  );
};

export default T2FNavbar;

