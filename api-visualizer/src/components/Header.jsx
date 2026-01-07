import { Network, FileJson } from 'lucide-react'

function Header({ activeView, onViewChange }) {
  return (
    <header className="app-header">
      <div className="header-content">
        <a href="/" className="logo">
          <div className="logo-icon">API</div>
          <span>API Visualizer</span>
        </a>
        
        <div className="view-tabs">
          <button
            className={`view-tab ${activeView === 'mindmap' ? 'active' : ''}`}
            onClick={() => onViewChange('mindmap')}
          >
            <Network size={16} style={{ marginRight: '6px', verticalAlign: 'middle' }} />
            Mind Map
          </button>
          <button
            className={`view-tab ${activeView === 'swagger' ? 'active' : ''}`}
            onClick={() => onViewChange('swagger')}
          >
            <FileJson size={16} style={{ marginRight: '6px', verticalAlign: 'middle' }} />
            Swagger UI
          </button>
        </div>
      </div>
    </header>
  )
}

export default Header
