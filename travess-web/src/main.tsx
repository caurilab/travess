import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';

import { Routeur } from './app/routeur.js';
import { AuthProvider } from './auth/AuthProvider.js';
import './styles/global.css';
import './ui/composants.css';
import './styles/app.css';

const client = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      staleTime: 30_000,
      refetchOnWindowFocus: false,
    },
  },
});

const racine = document.getElementById('racine');
if (racine === null) {
  throw new Error('Élément racine introuvable.');
}

createRoot(racine).render(
  <StrictMode>
    <QueryClientProvider client={client}>
      <BrowserRouter>
        <AuthProvider>
          <Routeur />
        </AuthProvider>
      </BrowserRouter>
    </QueryClientProvider>
  </StrictMode>,
);
