import { useEffect, useMemo, useRef, useState, useCallback } from 'react'
import Header from './components/Header'
import Sidebar from './components/Sidebar'
import MindMapView from './components/MindMapView'
import SwaggerView from './components/SwaggerView'
import { parseOpenApiToMindMap } from './utils/openApiParser'

// Documentação de exemplo para demonstração
const exampleSpec = {
  openapi: '3.0.0',
  info: {
    title: 'API de Exemplo',
    version: '1.0.0',
    description: 'Uma API de demonstração para o visualizador'
  },
  paths: {
    '/users': {
      get: {
        summary: 'Listar usuários',
        description: 'Retorna uma lista de todos os usuários cadastrados',
        tags: ['Usuários'],
        responses: {
          '200': { description: 'Lista de usuários' }
        }
      },
      post: {
        summary: 'Criar usuário',
        description: 'Cria um novo usuário no sistema',
        tags: ['Usuários'],
        responses: {
          '201': { description: 'Usuário criado' }
        }
      }
    },
    '/users/{id}': {
      get: {
        summary: 'Buscar usuário',
        description: 'Retorna os dados de um usuário específico',
        tags: ['Usuários'],
        responses: {
          '200': { description: 'Dados do usuário' }
        }
      },
      put: {
        summary: 'Atualizar usuário',
        description: 'Atualiza os dados de um usuário',
        tags: ['Usuários'],
        responses: {
          '200': { description: 'Usuário atualizado' }
        }
      },
      delete: {
        summary: 'Remover usuário',
        description: 'Remove um usuário do sistema',
        tags: ['Usuários'],
        responses: {
          '204': { description: 'Usuário removido' }
        }
      }
    },
    '/products': {
      get: {
        summary: 'Listar produtos',
        description: 'Retorna todos os produtos disponíveis',
        tags: ['Produtos'],
        responses: {
          '200': { description: 'Lista de produtos' }
        }
      },
      post: {
        summary: 'Criar produto',
        description: 'Adiciona um novo produto ao catálogo',
        tags: ['Produtos'],
        responses: {
          '201': { description: 'Produto criado' }
        }
      }
    },
    '/orders': {
      get: {
        summary: 'Listar pedidos',
        description: 'Retorna todos os pedidos realizados',
        tags: ['Pedidos'],
        responses: {
          '200': { description: 'Lista de pedidos' }
        }
      },
      post: {
        summary: 'Criar pedido',
        description: 'Cria um novo pedido de compra',
        tags: ['Pedidos'],
        responses: {
          '201': { description: 'Pedido criado' }
        }
      }
    }
  }
}

function App() {
  const wpConfig = useMemo(() => {
    return typeof window !== 'undefined' && window.API_VISUALIZER_CONFIG
      ? window.API_VISUALIZER_CONFIG
      : null
  }, [])

  const wpBaseUrl = useMemo(() => {
    const homeUrl = wpConfig?.homeUrl
    if (homeUrl && typeof homeUrl === 'string') return homeUrl
    return `${window.location.origin}/`
  }, [wpConfig])

  const [openApiSpec, setOpenApiSpec] = useState(exampleSpec)
  const [activeView, setActiveView] = useState('mindmap') // 'mindmap' | 'swagger'
  const [selectedEndpoint, setSelectedEndpoint] = useState(null)
  const [groupMode, setGroupMode] = useState('tag') // 'tag' | 'basePath'
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState(null)
  const didAutoLoadRef = useRef(false)

  const handleSpecLoad = useCallback(async (spec) => {
    setIsLoading(true)
    setError(null)
    
    try {
      // Valida se é um spec OpenAPI válido
      if (!spec.openapi && !spec.swagger) {
        throw new Error('Documento não parece ser uma especificação OpenAPI válida')
      }
      
      setOpenApiSpec(spec)
      setSelectedEndpoint(null)
    } catch (err) {
      setError(err.message)
    } finally {
      setIsLoading(false)
    }
  }, [])

  const handleUrlLoad = useCallback(async (url) => {
    setIsLoading(true)
    setError(null)
    
    try {
      const resolvedUrl = new URL(url, wpBaseUrl).toString()
      const response = await fetch(resolvedUrl)
      if (!response.ok) {
        throw new Error(`Falha ao carregar: ${response.status} ${response.statusText}`)
      }
      
      const contentType = response.headers.get('content-type')
      let spec
      
      if (contentType?.includes('yaml') || resolvedUrl.endsWith('.yaml') || resolvedUrl.endsWith('.yml')) {
        const yaml = await import('js-yaml')
        const text = await response.text()
        spec = yaml.default.load(text)
      } else {
        spec = await response.json()
      }
      
      await handleSpecLoad(spec)
    } catch (err) {
      setError(err.message)
      setIsLoading(false)
    }
  }, [handleSpecLoad, wpBaseUrl])

  // Auto-carrega um spec padrão vindo do WordPress (se houver)
  useEffect(() => {
    const defaultSpecUrl = wpConfig?.defaultSpecUrl
    if (!defaultSpecUrl || didAutoLoadRef.current) return
    didAutoLoadRef.current = true
    handleUrlLoad(defaultSpecUrl)
  }, [handleUrlLoad, wpConfig])

  const handleFileLoad = useCallback(async (file) => {
    setIsLoading(true)
    setError(null)
    
    try {
      const text = await file.text()
      let spec
      
      if (file.name.endsWith('.yaml') || file.name.endsWith('.yml')) {
        const yaml = await import('js-yaml')
        spec = yaml.default.load(text)
      } else {
        spec = JSON.parse(text)
      }
      
      await handleSpecLoad(spec)
    } catch (err) {
      setError(err.message)
      setIsLoading(false)
    }
  }, [handleSpecLoad])

  const mindMapData = openApiSpec ? parseOpenApiToMindMap(openApiSpec) : null

  return (
    <div className="app-container">
      <Header 
        activeView={activeView}
        onViewChange={setActiveView}
      />
      
      <main className="main-content">
        <Sidebar
          openApiSpec={openApiSpec}
          onUrlLoad={handleUrlLoad}
          onFileLoad={handleFileLoad}
          selectedEndpoint={selectedEndpoint}
          onEndpointSelect={setSelectedEndpoint}
          error={error}
          isLoading={isLoading}
          wpBaseUrl={wpBaseUrl}
          groupMode={groupMode}
          onGroupModeChange={setGroupMode}
        />
        
        {activeView === 'mindmap' ? (
          <MindMapView
            mindMapData={mindMapData}
            openApiSpec={openApiSpec}
            selectedEndpoint={selectedEndpoint}
            onEndpointSelect={setSelectedEndpoint}
            isLoading={isLoading}
            groupMode={groupMode}
            onGroupModeChange={setGroupMode}
          />
        ) : (
          <SwaggerView
            spec={openApiSpec}
            isLoading={isLoading}
          />
        )}
      </main>
    </div>
  )
}

export default App
