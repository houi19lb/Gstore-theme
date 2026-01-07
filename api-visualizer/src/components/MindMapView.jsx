import { useEffect, useMemo, useRef, useState, useCallback } from 'react'
import mermaid from 'mermaid'
import { 
  ZoomIn, 
  ZoomOut, 
  Maximize2, 
  RotateCcw,
  Network,
  Download,
  ArrowLeft
} from 'lucide-react'
import Tooltip from './Tooltip'
import { generateMermaidGroupsOverview, generateMermaidGroupFlowchart } from '../utils/openApiParser'

// Configuração do Mermaid
mermaid.initialize({
  startOnLoad: false,
  theme: 'dark',
  themeVariables: {
    primaryColor: '#6366f1',
    primaryTextColor: '#f1f5f9',
    primaryBorderColor: '#818cf8',
    lineColor: '#64748b',
    secondaryColor: '#1a1a24',
    tertiaryColor: '#16161f',
    background: '#0a0a0f',
    mainBkg: '#16161f',
    nodeBorder: '#6366f1',
    clusterBkg: '#1a1a24',
    clusterBorder: '#22d3ee',
    titleColor: '#f1f5f9',
    edgeLabelBackground: '#1a1a24'
  },
  flowchart: {
    htmlLabels: true,
    curve: 'basis',
    padding: 20,
    nodeSpacing: 50,
    rankSpacing: 80
  },
  securityLevel: 'loose'
})

function MindMapView({ mindMapData, openApiSpec, selectedEndpoint, onEndpointSelect, isLoading, groupMode, onGroupModeChange }) {
  const containerRef = useRef(null)
  const mermaidRef = useRef(null)
  const [zoom, setZoom] = useState(100)
  const [pan, setPan] = useState({ x: 0, y: 0 })
  const [isPanning, setIsPanning] = useState(false)
  const panStateRef = useRef({ pointerId: null, startX: 0, startY: 0, startPanX: 0, startPanY: 0 })
  const [tooltip, setTooltip] = useState(null)
  const [renderKey, setRenderKey] = useState(0)
  const [activeGroup, setActiveGroup] = useState(null)

  const groups = useMemo(() => {
    return groupMode === 'tag' ? mindMapData?.groupedByTag : mindMapData?.groupedByPath
  }, [groupMode, mindMapData])
  const totalGroups = useMemo(() => (groups ? Object.keys(groups).length : 0), [groups])
  const totalEndpoints = mindMapData?.stats?.totalEndpoints || 0

  // Heurística para specs grandes: evita renderizar todos endpoints.
  const isHugeSpec = totalEndpoints > 300 || totalGroups > 120

  const diagramCode = (() => {
    if (!mindMapData) return null

    const title = mindMapData.title

    if (activeGroup && groups?.[activeGroup]) {
      return generateMermaidGroupFlowchart({
        title,
        groupName: activeGroup,
        endpoints: groups[activeGroup],
        maxEndpoints: 200
      })
    }

    // Overview: apenas grupos
    return generateMermaidGroupsOverview({
      title,
      groups: groups || {}
    })
  })()

  // Quando muda o diagrama (modo/grupo/spec), reseta pan/zoom para evitar "sumir"
  useEffect(() => {
    setZoom(100)
    setPan({ x: 0, y: 0 })
  }, [diagramCode])

  // Renderiza o diagrama Mermaid
  const renderMermaid = useCallback(async () => {
    if (!mermaidRef.current || !diagramCode) return

    try {
      mermaidRef.current.innerHTML = ''
      
      const { svg } = await mermaid.render(
        `mermaid-${Date.now()}`, 
        diagramCode
      )
      
      mermaidRef.current.innerHTML = svg

      // Adiciona event listeners para hover nos nós
      const nodes = mermaidRef.current.querySelectorAll('.node')
      nodes.forEach((node) => {
        node.style.cursor = 'pointer'
        
        node.addEventListener('mouseenter', (e) => {
          handleNodeHover(e, node)
        })
        
        node.addEventListener('mouseleave', () => {
          setTooltip(null)
        })

        node.addEventListener('click', () => {
          handleNodeClick(node)
        })
      })
    } catch (error) {
      console.error('Erro ao renderizar Mermaid:', error)
      if (mermaidRef.current) {
        mermaidRef.current.innerHTML = `
          <div style="
            padding: 24px;
            text-align: center;
            color: #f43f5e;
            font-size: 14px;
          ">
            Erro ao renderizar o diagrama. Dica: use o modo de agrupamento e clique em um grupo para ver apenas parte dos endpoints.
          </div>
        `
      }
    }
  }, [diagramCode])

  useEffect(() => {
    renderMermaid()
  }, [renderMermaid, renderKey])

  // Handler para hover nos nós
  const handleNodeHover = (event, node) => {
    const textElement = node.querySelector('.nodeLabel') || node.querySelector('text')
    if (!textElement) return

    const text = textElement.textContent || ''
    
    // Tenta identificar o endpoint pelo texto do nó
    const endpoint = findEndpointFromNodeText(text)
    
    if (endpoint) {
      const rect = node.getBoundingClientRect()
      setTooltip({
        x: rect.right + 10,
        y: rect.top,
        endpoint
      })
    } else if (text.startsWith('Grupo:')) {
      // É um nó de grupo
      const groupName = parseGroupNameFromLabel(text)
      const groupEndpoints = groups?.[groupName] || []
      
      const rect = node.getBoundingClientRect()
      setTooltip({
        x: rect.right + 10,
        y: rect.top,
        tag: {
          name: groupName,
          count: groupEndpoints.length
        }
      })
    }
  }

  // Handler para clique nos nós
  const handleNodeClick = (node) => {
    const textElement = node.querySelector('.nodeLabel') || node.querySelector('text')
    if (!textElement) return

    const text = textElement.textContent || ''
    const endpoint = findEndpointFromNodeText(text)
    
    if (endpoint && onEndpointSelect) {
      onEndpointSelect(endpoint)
      return
    }

    if (text.startsWith('Grupo:')) {
      const groupName = parseGroupNameFromLabel(text)
      if (groupName && groups?.[groupName]) {
        setActiveGroup(groupName)
        setRenderKey(k => k + 1)
      }
    }
  }

  // Encontra endpoint pelo texto do nó
  const findEndpointFromNodeText = (text) => {
    if (!mindMapData?.endpoints) return null

    // Extrai método e path do texto
    const methodMatch = text.match(/(GET|POST|PUT|PATCH|DELETE)/i)
    if (!methodMatch) return null

    const method = methodMatch[1].toUpperCase()
    
    // Encontra o endpoint que corresponde
    return mindMapData.endpoints.find(ep => {
      if (ep.method !== method) return false
      // Verifica se o path está contido no texto
      return text.includes(ep.path) || text.includes(ep.path.substring(0, 15))
    })
  }

  const parseGroupNameFromLabel = (label) => {
    // "Grupo: Nome (123)" -> "Nome"
    const raw = label.replace(/^Grupo:\s*/i, '').trim()
    const idx = raw.lastIndexOf('(')
    if (idx > 0) return raw.substring(0, idx).trim()
    return raw
  }

  // Controles de zoom
  const handleZoomIn = () => {
    setZoom(prev => Math.min(prev + 20, 200))
  }

  const handleZoomOut = () => {
    setZoom(prev => Math.max(prev - 20, 40))
  }

  const handleResetZoom = () => {
    setZoom(100)
    setPan({ x: 0, y: 0 })
  }

  const handleFitToScreen = () => {
    if (!containerRef.current || !mermaidRef.current) return
    
    const container = containerRef.current
    const content = mermaidRef.current
    const svg = content.querySelector('svg')
    
    if (!svg) return

    const containerWidth = container.clientWidth - 48
    const containerHeight = container.clientHeight - 48

    // Usa viewBox (mais estável que getBoundingClientRect em SVG)
    const vb = svg.viewBox?.baseVal
    const svgWidth = vb?.width || svg.getBoundingClientRect().width
    const svgHeight = vb?.height || svg.getBoundingClientRect().height

    const scaleX = containerWidth / svgWidth
    const scaleY = containerHeight / svgHeight
    const scale = Math.min(scaleX, scaleY, 1) * 100

    setZoom(Math.round(scale))
    // Centraliza
    const s = Math.round(scale) / 100
    const panX = (containerWidth - svgWidth * s) / 2
    const panY = (containerHeight - svgHeight * s) / 2
    setPan({ x: Math.round(panX), y: Math.round(panY) })
  }

  // Exportar como SVG
  const handleExportSvg = () => {
    if (!mermaidRef.current) return
    
    const svg = mermaidRef.current.querySelector('svg')
    if (!svg) return

    const svgData = new XMLSerializer().serializeToString(svg)
    const blob = new Blob([svgData], { type: 'image/svg+xml' })
    const url = URL.createObjectURL(blob)
    
    const link = document.createElement('a')
    link.href = url
    link.download = `api-mindmap-${Date.now()}.svg`
    link.click()
    
    URL.revokeObjectURL(url)
  }

  // Ctrl + scroll => zoom (mantém o ponto sob o cursor)
  const handleWheel = (e) => {
    if (!e.ctrlKey) return
    e.preventDefault()
    if (!containerRef.current) return

    const rect = containerRef.current.getBoundingClientRect()
    const cursorX = e.clientX - rect.left
    const cursorY = e.clientY - rect.top

    const oldScale = zoom / 100
    const delta = -e.deltaY
    const factor = 1 + Math.min(0.25, Math.max(-0.25, delta * 0.0015))
    const newScale = Math.min(2.5, Math.max(0.4, oldScale * factor))
    const newZoom = Math.round(newScale * 100)

    // world = (screen - pan) / scale; mantém world constante
    const worldX = (cursorX - pan.x) / oldScale
    const worldY = (cursorY - pan.y) / oldScale
    const newPanX = cursorX - worldX * newScale
    const newPanY = cursorY - worldY * newScale

    setZoom(newZoom)
    setPan({ x: Math.round(newPanX), y: Math.round(newPanY) })
  }

  // Pan: clique no background e arraste
  const handlePointerDown = (e) => {
    if (e.button !== 0) return
    // Não inicia pan quando clicar em nós/labels
    if (e.target?.closest?.('.node')) return
    if (!containerRef.current) return

    setIsPanning(true)
    panStateRef.current = {
      pointerId: e.pointerId,
      startX: e.clientX,
      startY: e.clientY,
      startPanX: pan.x,
      startPanY: pan.y
    }
    containerRef.current.setPointerCapture?.(e.pointerId)
  }

  const handlePointerMove = (e) => {
    if (!isPanning) return
    const st = panStateRef.current
    if (st.pointerId !== e.pointerId) return
    const dx = e.clientX - st.startX
    const dy = e.clientY - st.startY
    setPan({ x: Math.round(st.startPanX + dx), y: Math.round(st.startPanY + dy) })
  }

  const endPan = () => {
    if (!isPanning) return
    setIsPanning(false)
    const st = panStateRef.current
    if (containerRef.current && st.pointerId !== null) {
      try {
        containerRef.current.releasePointerCapture?.(st.pointerId)
      } catch {
        // noop
      }
    }
    panStateRef.current.pointerId = null
  }

  // Estado vazio
  if (!openApiSpec) {
    return (
      <div className="mindmap-area">
        <div className="empty-state">
          <Network size={80} className="empty-state-icon" />
          <h3 className="empty-state-title">Nenhuma API carregada</h3>
          <p className="empty-state-description">
            Carregue uma especificação OpenAPI/Swagger usando a barra lateral 
            para visualizar o diagrama de endpoints.
          </p>
        </div>
      </div>
    )
  }

  return (
    <div className="mindmap-area">
      {/* Toolbar */}
      <div className="mindmap-toolbar">
        <div className="toolbar-group">
          <span style={{ 
            fontSize: '0.8rem', 
            color: 'var(--color-text-secondary)',
            marginRight: '8px'
          }}>
            {mindMapData?.stats?.totalEndpoints || 0} endpoints em{' '}
            {groupMode === 'tag' ? (mindMapData?.stats?.totalTags || 0) : totalGroups} grupos
          </span>

          <div style={{ display: 'flex', gap: '6px' }}>
            <button
              className={`toolbar-btn ${groupMode === 'tag' ? 'active' : ''}`}
              onClick={() => { onGroupModeChange?.('tag'); setActiveGroup(null); setRenderKey(k => k + 1) }}
              title="Agrupar por Tags"
            >
              Tags
            </button>
            <button
              className={`toolbar-btn ${groupMode === 'basePath' ? 'active' : ''}`}
              onClick={() => { onGroupModeChange?.('basePath'); setActiveGroup(null); setRenderKey(k => k + 1) }}
              title="Agrupar por Base Path"
            >
              Base path
            </button>
            {activeGroup && (
              <button
                className="toolbar-btn"
                onClick={() => { setActiveGroup(null); setRenderKey(k => k + 1) }}
                title="Voltar para overview"
              >
                <ArrowLeft size={16} />
              </button>
            )}
          </div>
        </div>

        <div className="toolbar-group">
          {/* Zoom controls */}
          <div className="zoom-controls">
            <button 
              className="zoom-btn" 
              onClick={handleZoomOut}
              title="Diminuir zoom"
            >
              <ZoomOut size={16} />
            </button>
            <span className="zoom-level">{zoom}%</span>
            <button 
              className="zoom-btn" 
              onClick={handleZoomIn}
              title="Aumentar zoom"
            >
              <ZoomIn size={16} />
            </button>
          </div>

          <button 
            className="toolbar-btn" 
            onClick={handleFitToScreen}
            title="Ajustar à tela"
          >
            <Maximize2 size={16} />
          </button>

          <button 
            className="toolbar-btn" 
            onClick={handleResetZoom}
            title="Resetar zoom"
          >
            <RotateCcw size={16} />
          </button>

          <button 
            className="toolbar-btn" 
            onClick={handleExportSvg}
            title="Exportar SVG"
          >
            <Download size={16} />
          </button>
        </div>
      </div>

      {/* Container do Mind Map */}
      <div 
        ref={containerRef}
        className="mindmap-container"
        style={{
          overflow: 'hidden',
          touchAction: 'none',
          cursor: isPanning ? 'grabbing' : 'grab'
        }}
        onWheel={handleWheel}
        onPointerDown={handlePointerDown}
        onPointerMove={handlePointerMove}
        onPointerUp={endPan}
        onPointerCancel={endPan}
        onPointerLeave={endPan}
      >
        {isLoading ? (
          <div className="loading-overlay">
            <div className="loading-spinner" />
          </div>
        ) : (
          isHugeSpec && !activeGroup ? (
            <div style={{
              position: 'absolute',
              top: '80px',
              left: '24px',
              right: '24px',
              padding: '10px 12px',
              borderRadius: '10px',
              border: '1px solid var(--color-border)',
              background: 'rgba(22, 22, 31, 0.9)',
              backdropFilter: 'blur(8px)',
              color: 'var(--color-text-secondary)',
              fontSize: '0.85rem',
              zIndex: 10
            }}>
              Especificação grande detectada. Mostrando apenas os grupos para evitar limites do Mermaid.
              Clique em um grupo no diagrama para ver os endpoints daquele grupo.
            </div>
          ) : null,
          <div 
            ref={mermaidRef}
            className="mermaid-wrapper"
            style={{
              transform: `translate(${pan.x}px, ${pan.y}px) scale(${zoom / 100})`,
              transformOrigin: '0 0',
              transition: isPanning ? 'none' : 'transform 0.12s ease'
            }}
          />
        )}
      </div>

      {/* Tooltip */}
      {tooltip && (
        <Tooltip 
          x={tooltip.x} 
          y={tooltip.y}
          endpoint={tooltip.endpoint}
          tag={tooltip.tag}
        />
      )}

      {/* Estatísticas de métodos */}
      {mindMapData?.stats?.methods && (
        <div style={{
          position: 'absolute',
          bottom: '16px',
          left: '16px',
          display: 'flex',
          gap: '8px',
          padding: '8px 12px',
          background: 'rgba(22, 22, 31, 0.9)',
          backdropFilter: 'blur(8px)',
          borderRadius: '8px',
          border: '1px solid var(--color-border)'
        }}>
          {Object.entries(mindMapData.stats.methods).map(([method, count]) => (
            count > 0 && (
              <div 
                key={method}
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: '4px',
                  fontSize: '0.7rem'
                }}
              >
                <span className={`method-badge ${method.toLowerCase()}`}>
                  {method}
                </span>
                <span style={{ color: 'var(--color-text-muted)' }}>{count}</span>
              </div>
            )
          ))}
        </div>
      )}
    </div>
  )
}

export default MindMapView
