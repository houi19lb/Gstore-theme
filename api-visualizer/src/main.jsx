import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App.jsx'
import './styles/index.css'

const mountEl =
  document.getElementById('api-visualizer-root') ||
  document.getElementById('root')

if (!mountEl) {
  throw new Error('API Visualizer: elemento de montagem não encontrado.')
}

// Garante que os estilos fiquem escopados tanto no WP quanto no modo standalone
mountEl.classList.add('gstore-api-visualizer')

ReactDOM.createRoot(mountEl).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
)
