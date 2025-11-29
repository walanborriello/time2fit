import React from 'react';

class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false, error: null };
  }

  static getDerivedStateFromError(error) {
    return { hasError: true, error };
  }

  componentDidCatch(error, errorInfo) {
    console.error('ErrorBoundary caught an error:', error, errorInfo);
  }

  render() {
    if (this.state.hasError) {
      return (
        <div style={{ 
          padding: '2rem', 
          color: '#fff', 
          background: '#000',
          minHeight: '100vh',
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
          justifyContent: 'center'
        }}>
          <h1 style={{ color: '#ed3833', marginBottom: '1rem' }}>Errore</h1>
          <p style={{ marginBottom: '1rem' }}>Si è verificato un errore nell'applicazione.</p>
          <pre style={{ 
            background: '#1a1a1a', 
            padding: '1rem', 
            borderRadius: '8px',
            overflow: 'auto',
            maxWidth: '800px',
            fontSize: '0.875rem'
          }}>
            {this.state.error?.toString()}
          </pre>
          <button 
            onClick={() => window.location.reload()}
            style={{
              marginTop: '1rem',
              padding: '0.75rem 1.5rem',
              background: '#f9cc49',
              color: '#000',
              border: 'none',
              borderRadius: '999px',
              cursor: 'pointer',
              fontWeight: '600'
            }}
          >
            Ricarica pagina
          </button>
        </div>
      );
    }

    return this.props.children;
  }
}

export default ErrorBoundary;


