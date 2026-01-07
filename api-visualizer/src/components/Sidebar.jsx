import { useEffect, useMemo, useState, useRef } from 'react'
import { 
  Upload, 
  Link, 
  AlertCircle, 
  Search,
  ChevronDown,
  ChevronRight,
  Loader2
} from 'lucide-react'
import { extractEndpoints, groupEndpointsByTag, groupEndpointsByBasePath, searchEndpoints } from '../utils/openApiParser'

function Sidebar({ 
  openApiSpec, 
  onUrlLoad, 
  onFileLoad, 
  selectedEndpoint,
  onEndpointSelect,
  error,
  isLoading,
  wpBaseUrl,
  groupMode,
  onGroupModeChange
}) {
  const [url, setUrl] = useState('')
  const [searchTerm, setSearchTerm] = useState('')
  const [expandedGroups, setExpandedGroups] = useState({})
  const [isDragging, setIsDragging] = useState(false)
  const fileInputRef = useRef(null)

  const endpoints = useMemo(() => (openApiSpec ? extractEndpoints(openApiSpec) : []), [openApiSpec])

  const filteredEndpoints = useMemo(() => {
    return searchTerm ? searchEndpoints(endpoints, searchTerm) : endpoints
  }, [endpoints, searchTerm])

  const groupFn = groupMode === 'basePath' ? groupEndpointsByBasePath : groupEndpointsByTag

  const filteredGroups = useMemo(() => {
    return groupFn(filteredEndpoints)
  }, [filteredEndpoints, groupFn])

  const handleUrlSubmit = (e) => {
    e.preventDefault()
    if (url.trim()) {
      onUrlLoad(url.trim())
    }
  }

  const handleFileChange = (e) => {
    const file = e.target.files?.[0]
    if (file) {
      onFileLoad(file)
    }
  }

  const handleDragOver = (e) => {
    e.preventDefault()
    setIsDragging(true)
  }

  const handleDragLeave = () => {
    setIsDragging(false)
  }

  const handleDrop = (e) => {
    e.preventDefault()
    setIsDragging(false)
    
    const file = e.dataTransfer.files?.[0]
    if (file) {
      onFileLoad(file)
    }
  }

  const toggleGroup = (key) => {
    setExpandedGroups(prev => ({
      ...prev,
      [key]: !prev[key]
    }))
  }

  const allExpanded = Object.keys(filteredGroups).every(key => expandedGroups[key])

  const toggleAllGroups = () => {
    const newState = !allExpanded
    const newExpanded = {}
    Object.keys(filteredGroups).forEach(key => {
      newExpanded[key] = newState
    })
    setExpandedGroups(newExpanded)
  }

  // Quando troca o modo de agrupamento, recolhe tudo para evitar lista gigantesca aberta
  useEffect(() => {
    setExpandedGroups({})
  }, [groupMode])

  return (
    <aside className="sidebar">
      {/* Seção de Input */}
      <div className="sidebar-section">
        <h2 className="section-title">
          <Link size={14} />
          Carregar Especificação
        </h2>
        
        <div className="swagger-input-container">
          {/* Input de URL */}
          <form onSubmit={handleUrlSubmit} className="input-group">
            <label className="input-label">URL do OpenAPI/Swagger</label>
            <div className="url-input-wrapper">
              <input
                type="text"
                className="url-input"
                placeholder="https://api.exemplo.com/openapi.json (ou /wp-json/...)"
                value={url}
                onChange={(e) => setUrl(e.target.value)}
                disabled={isLoading}
              />
              <button 
                type="submit" 
                className="btn btn-primary"
                disabled={isLoading || !url.trim()}
              >
                {isLoading ? <Loader2 size={16} className="animate-spin" /> : 'Carregar'}
              </button>
            </div>
          </form>

          {wpBaseUrl && (
            <div style={{ fontSize: '0.75rem', color: 'var(--color-text-muted)' }}>
              Base do WordPress detectada: <span style={{ fontFamily: 'var(--font-mono)' }}>{wpBaseUrl}</span>
              <div style={{ marginTop: '4px' }}>
                Dica: você pode colar uma URL relativa (ex.: <span style={{ fontFamily: 'var(--font-mono)' }}>/wp-json/...</span>) que o app resolve automaticamente.
              </div>
            </div>
          )}

          <div className="divider">ou</div>

          {/* Upload de arquivo */}
          <div 
            className={`file-upload-zone ${isDragging ? 'drag-over' : ''}`}
            onDragOver={handleDragOver}
            onDragLeave={handleDragLeave}
            onDrop={handleDrop}
          >
            <input
              ref={fileInputRef}
              type="file"
              accept=".json,.yaml,.yml"
              onChange={handleFileChange}
              disabled={isLoading}
            />
            <Upload className="upload-icon" />
            <p className="upload-text">
              <strong>Clique para fazer upload</strong> ou arraste o arquivo
            </p>
            <p className="upload-hint">JSON ou YAML</p>
          </div>
        </div>

        {/* Mensagem de erro */}
        {error && (
          <div className="error-message" style={{
            marginTop: '12px',
            padding: '10px 12px',
            background: 'rgba(244, 63, 94, 0.1)',
            border: '1px solid rgba(244, 63, 94, 0.3)',
            borderRadius: '8px',
            display: 'flex',
            alignItems: 'flex-start',
            gap: '8px',
            fontSize: '0.8rem',
            color: '#f43f5e'
          }}>
            <AlertCircle size={16} style={{ flexShrink: 0, marginTop: '1px' }} />
            <span>{error}</span>
          </div>
        )}
      </div>

      {/* Seção de Endpoints */}
      <div className="sidebar-section">
        <div style={{ 
          display: 'flex', 
          alignItems: 'center', 
          justifyContent: 'space-between',
          marginBottom: '12px'
        }}>
          <h2 className="section-title" style={{ marginBottom: 0 }}>
            Endpoints ({endpoints.length})
          </h2>
          <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
            <div className="view-tabs" style={{ padding: '2px', borderRadius: '8px' }}>
              <button
                className={`view-tab ${groupMode !== 'basePath' ? 'active' : ''}`}
                onClick={() => onGroupModeChange?.('tag')}
                type="button"
                style={{ padding: '6px 10px', fontSize: '0.75rem' }}
                title="Agrupar por Tags"
              >
                Tags
              </button>
              <button
                className={`view-tab ${groupMode === 'basePath' ? 'active' : ''}`}
                onClick={() => onGroupModeChange?.('basePath')}
                type="button"
                style={{ padding: '6px 10px', fontSize: '0.75rem' }}
                title="Agrupar por Base path"
              >
                Base path
              </button>
            </div>

            {Object.keys(filteredGroups).length > 0 && (
            <button
              onClick={toggleAllGroups}
              className="btn btn-secondary"
              style={{ padding: '4px 8px', fontSize: '0.7rem' }}
            >
              {allExpanded ? 'Recolher' : 'Expandir'}
            </button>
          )}
          </div>
        </div>

        {/* Busca */}
        <div className="input-group" style={{ marginBottom: '12px' }}>
          <div style={{ position: 'relative' }}>
            <Search 
              size={16} 
              style={{ 
                position: 'absolute', 
                left: '12px', 
                top: '50%', 
                transform: 'translateY(-50%)',
                color: 'var(--color-text-muted)'
              }} 
            />
            <input
              type="text"
              className="url-input"
              placeholder="Buscar endpoints..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              style={{ paddingLeft: '36px' }}
            />
          </div>
        </div>

        {/* Lista de endpoints agrupados */}
        <div className="endpoints-list">
          {Object.entries(filteredGroups).map(([groupKey, groupEndpoints]) => (
            <div key={groupKey} className="endpoint-group">
              <button
                className="endpoint-group-header"
                onClick={() => toggleGroup(groupKey)}
                style={{
                  width: '100%',
                  display: 'flex',
                  alignItems: 'center',
                  gap: '8px',
                  padding: '8px 12px',
                  background: 'var(--color-bg-tertiary)',
                  border: '1px solid var(--color-border)',
                  borderRadius: '8px',
                  cursor: 'pointer',
                  marginBottom: '4px',
                  color: 'var(--color-text-primary)',
                  fontSize: '0.8rem',
                  fontWeight: '600'
                }}
              >
                {expandedGroups[groupKey] ? <ChevronDown size={14} /> : <ChevronRight size={14} />}
                <span style={{ flex: 1, textAlign: 'left' }}>
                  {groupMode === 'basePath' ? `/${groupKey}` : groupKey}
                </span>
                <span style={{ 
                  fontSize: '0.7rem', 
                  color: 'var(--color-text-muted)',
                  background: 'var(--color-bg-hover)',
                  padding: '2px 6px',
                  borderRadius: '4px'
                }}>
                  {groupEndpoints.length}
                </span>
              </button>

              {expandedGroups[groupKey] && (
                <div style={{ paddingLeft: '12px', marginBottom: '8px' }}>
                  {groupEndpoints.map((endpoint) => (
                    <div
                      key={endpoint.id}
                      className={`endpoint-item ${selectedEndpoint?.id === endpoint.id ? 'active' : ''}`}
                      onClick={() => onEndpointSelect(endpoint)}
                    >
                      <span className={`method-badge ${endpoint.method.toLowerCase()}`}>
                        {endpoint.method}
                      </span>
                      <span className="endpoint-path" title={endpoint.path}>
                        {endpoint.path}
                      </span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          ))}

          {Object.keys(filteredGroups).length === 0 && (
            <div style={{
              textAlign: 'center',
              padding: '24px',
              color: 'var(--color-text-muted)',
              fontSize: '0.8rem'
            }}>
              {searchTerm ? 'Nenhum endpoint encontrado' : 'Carregue uma especificação para ver os endpoints'}
            </div>
          )}
        </div>
      </div>

      {/* API Info */}
      {openApiSpec?.info && (
        <div className="sidebar-section" style={{ 
          marginTop: 'auto',
          background: 'var(--color-bg-tertiary)',
          borderTop: '1px solid var(--color-border)'
        }}>
          <div style={{ fontSize: '0.75rem', color: 'var(--color-text-muted)' }}>
            <div style={{ fontWeight: '600', color: 'var(--color-text-primary)', marginBottom: '4px' }}>
              {openApiSpec.info.title}
            </div>
            <div>Versão: {openApiSpec.info.version}</div>
            {openApiSpec.openapi && <div>OpenAPI: {openApiSpec.openapi}</div>}
            {openApiSpec.swagger && <div>Swagger: {openApiSpec.swagger}</div>}
          </div>
        </div>
      )}
    </aside>
  )
}

export default Sidebar
