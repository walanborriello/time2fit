import axios from 'axios';

const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Sistema di deduplicazione richieste
const pendingRequests = new Map();

const getRequestKey = (config) => {
  const method = config.method?.toUpperCase() || 'GET';
  const url = config.url || '';
  const params = JSON.stringify(config.params || {});
  const data = config.data ? (typeof config.data === 'string' ? config.data : JSON.stringify(config.data)) : '';
  return `${method}_${url}_${params}_${data}`;
};

// Salva il metodo request originale
const originalRequest = api.request.bind(api);

// Sostituisci request per intercettare tutte le chiamate
api.request = function(config) {
  const requestKey = getRequestKey(config);
  
  // Se c'è già una richiesta identica in corso, ritorna quella promise
  if (pendingRequests.has(requestKey)) {
    return pendingRequests.get(requestKey);
  }
  
  // Crea nuova richiesta
  const requestPromise = originalRequest(config)
    .then(response => {
      pendingRequests.delete(requestKey);
      return response;
    })
    .catch(error => {
      pendingRequests.delete(requestKey);
      throw error;
    });
  
  pendingRequests.set(requestKey, requestPromise);
  return requestPromise;
};

// Response interceptor
api.interceptors.response.use(
  (response) => {
    return response;
  },
  (error) => {
    if (error.response?.status === 401) {
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default api;

