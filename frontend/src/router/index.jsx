import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import Login from '../pages/Login';
import Register from '../pages/Register';
import DashboardClient from '../pages/DashboardClient';
import DashboardInstructor from '../pages/DashboardInstructor';
import DashboardAdmin from '../pages/DashboardAdmin';
import PlanView from '../pages/PlanView';
import ProgressForm from '../pages/ProgressForm';
import AgendaPT from '../pages/AgendaPT';

const PrivateRoute = ({ children, requiredRole = null }) => {
  const { isAuthenticated, loading, hasRole } = useAuth();

  if (loading) {
    return <div style={{ padding: '2rem', textAlign: 'center' }}>Caricamento...</div>;
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  if (requiredRole && !hasRole(requiredRole)) {
    return <Navigate to="/login" replace />;
  }

  return children;
};

const AppRouter = () => {
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />
        
        <Route
          path="/client/dashboard"
          element={
            <PrivateRoute requiredRole="ROLE_CLIENT">
              <DashboardClient />
            </PrivateRoute>
          }
        />
        <Route
          path="/client/plan/:id?"
          element={
            <PrivateRoute requiredRole="ROLE_CLIENT">
              <PlanView />
            </PrivateRoute>
          }
        />
        <Route
          path="/client/progress/:tpeId"
          element={
            <PrivateRoute requiredRole="ROLE_CLIENT">
              <ProgressForm />
            </PrivateRoute>
          }
        />
        
        <Route
          path="/instructor/dashboard"
          element={
            <PrivateRoute requiredRole="ROLE_INSTRUCTOR">
              <DashboardInstructor />
            </PrivateRoute>
          }
        />
        <Route
          path="/instructor/agenda"
          element={
            <PrivateRoute requiredRole="ROLE_INSTRUCTOR_PT">
              <AgendaPT />
            </PrivateRoute>
          }
        />
        
        <Route
          path="/admin/dashboard"
          element={
            <PrivateRoute requiredRole="ROLE_ADMIN">
              <DashboardAdmin />
            </PrivateRoute>
          }
        />
        
        <Route path="/" element={<Navigate to="/login" replace />} />
        <Route path="*" element={<Navigate to="/login" replace />} />
      </Routes>
    </BrowserRouter>
  );
};

export default AppRouter;

