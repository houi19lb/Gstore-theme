import { useEffect, useRef, useState } from 'react'
import { 
  ArrowRight, 
  ArrowLeft, 
  Edit3, 
  Wrench, 
  Trash2,
  AlertTriangle,
  Tag,
  Hash
} from 'lucide-react'

/**
 * Componente de Tooltip/Infobox para exibir informações
 * ao passar o mouse sobre os nós do mind map
 */
function Tooltip({ x, y, endpoint, tag }) {
  const tooltipRef = useRef(null)
  const [position, setPosition] = useState({ x, y })

  // Ajusta posição para não sair da tela
  useEffect(() => {
    if (!tooltipRef.current) return

    const tooltip = tooltipRef.current
    const rect = tooltip.getBoundingClientRect()
    const padding = 16

    let newX = x
    let newY = y

    // Ajusta horizontal
    if (rect.right > window.innerWidth - padding) {
      newX = x - rect.width - 20
    }

    // Ajusta vertical
    if (rect.bottom > window.innerHeight - padding) {
      newY = window.innerHeight - rect.height - padding
    }

    if (newY < padding) {
      newY = padding
    }

    setPosition({ x: newX, y: newY })
  }, [x, y])

  // Ícone baseado no método HTTP
  const getMethodIcon = (method) => {
    const icons = {
      GET: <ArrowLeft size={14} style={{ color: '#22d3ee' }} />,
      POST: <ArrowRight size={14} style={{ color: '#10b981' }} />,
      PUT: <Edit3 size={14} style={{ color: '#f59e0b' }} />,
      PATCH: <Wrench size={14} style={{ color: '#a78bfa' }} />,
      DELETE: <Trash2 size={14} style={{ color: '#f43f5e' }} />
    }
    return icons[method] || null
  }

  // Renderiza tooltip para endpoint
  if (endpoint) {
    return (
      <div 
        ref={tooltipRef}
        className="tooltip-container"
        style={{ left: position.x, top: position.y }}
      >
        <div className="tooltip">
          {/* Header */}
          <div className="tooltip-header">
            <span className={`method-badge ${endpoint.method.toLowerCase()}`}>
              {endpoint.method}
            </span>
            <span className="tooltip-title" style={{ fontFamily: 'var(--font-mono)' }}>
              {endpoint.path}
            </span>
          </div>

          {/* Summary */}
          {endpoint.summary && (
            <div style={{ 
              fontWeight: '600', 
              fontSize: '0.85rem',
              color: 'var(--color-text-primary)',
              marginBottom: '8px'
            }}>
              {endpoint.summary}
            </div>
          )}

          {/* Description */}
          {endpoint.description && (
            <p className="tooltip-description">
              {endpoint.description}
            </p>
          )}

          {/* Deprecated warning */}
          {endpoint.deprecated && (
            <div style={{
              display: 'flex',
              alignItems: 'center',
              gap: '6px',
              padding: '6px 10px',
              background: 'rgba(245, 158, 11, 0.15)',
              border: '1px solid rgba(245, 158, 11, 0.3)',
              borderRadius: '6px',
              marginBottom: '8px',
              fontSize: '0.75rem',
              color: '#f59e0b'
            }}>
              <AlertTriangle size={14} />
              <span>Este endpoint está deprecado</span>
            </div>
          )}

          {/* Tags */}
          <div className="tooltip-meta">
            {endpoint.tags.map((tag, index) => (
              <span key={index} className="tooltip-tag">
                <Tag size={10} style={{ marginRight: '4px', verticalAlign: 'middle' }} />
                {tag}
              </span>
            ))}
            
            {endpoint.operationId && (
              <span className="tooltip-tag">
                <Hash size={10} style={{ marginRight: '4px', verticalAlign: 'middle' }} />
                {endpoint.operationId}
              </span>
            )}
          </div>

          {/* Responses */}
          {endpoint.responses && Object.keys(endpoint.responses).length > 0 && (
            <div style={{ marginTop: '10px', paddingTop: '10px', borderTop: '1px solid var(--color-border)' }}>
              <div style={{ 
                fontSize: '0.7rem', 
                color: 'var(--color-text-muted)',
                marginBottom: '6px',
                textTransform: 'uppercase',
                letterSpacing: '0.05em'
              }}>
                Respostas
              </div>
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: '4px' }}>
                {Object.keys(endpoint.responses).slice(0, 5).map(code => (
                  <span 
                    key={code}
                    style={{
                      fontSize: '0.7rem',
                      fontFamily: 'var(--font-mono)',
                      padding: '2px 6px',
                      borderRadius: '4px',
                      background: getStatusColor(code),
                      color: 'white'
                    }}
                  >
                    {code}
                  </span>
                ))}
              </div>
            </div>
          )}

          {/* Parameters count */}
          {endpoint.parameters && endpoint.parameters.length > 0 && (
            <div style={{ 
              marginTop: '8px',
              fontSize: '0.7rem',
              color: 'var(--color-text-muted)'
            }}>
              {endpoint.parameters.length} parâmetro(s)
            </div>
          )}
        </div>
      </div>
    )
  }

  // Renderiza tooltip para tag/grupo
  if (tag) {
    return (
      <div 
        ref={tooltipRef}
        className="tooltip-container"
        style={{ left: position.x, top: position.y }}
      >
        <div className="tooltip">
          <div className="tooltip-header">
            <Tag size={16} style={{ color: 'var(--color-accent-cyan)' }} />
            <span className="tooltip-title">{tag.name}</span>
          </div>
          <p className="tooltip-description">
            Este grupo contém {tag.count} endpoint{tag.count !== 1 ? 's' : ''}
          </p>
        </div>
      </div>
    )
  }

  return null
}

/**
 * Retorna a cor baseada no código de status HTTP
 */
function getStatusColor(code) {
  const codeNum = parseInt(code)
  
  if (codeNum >= 200 && codeNum < 300) return '#10b981' // Success - verde
  if (codeNum >= 300 && codeNum < 400) return '#22d3ee' // Redirect - cyan
  if (codeNum >= 400 && codeNum < 500) return '#f59e0b' // Client error - amarelo
  if (codeNum >= 500) return '#f43f5e' // Server error - vermelho
  
  return '#64748b' // Default - cinza
}

export default Tooltip
