import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.tsx'
import { SocketProvider } from './contexts/SocketContext'
import { Toaster } from 'react-hot-toast'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <SocketProvider>
      <App />
      <Toaster 
        position="top-center"
        reverseOrder={false}
        gutter={8}
        containerClassName=""
        containerStyle={{
          top: 'max(env(safe-area-inset-top), 20px)',
          left: 'max(env(safe-area-inset-left), 16px)',
          right: 'max(env(safe-area-inset-right), 16px)',
        }}
        toastOptions={{
          // Global defaults - overridden by service
          duration: 4000,
          style: {
            borderRadius: '12px',
            fontWeight: '500',
            wordBreak: 'break-word',
          },
        }}
      />
    </SocketProvider>
  </StrictMode>,
)
