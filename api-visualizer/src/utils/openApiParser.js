/**
 * Parser de especificação OpenAPI para formato de Mind Map
 */

/**
 * Escapa texto para uso seguro em labels Mermaid.
 * (Mermaid é sensível a aspas e quebras de linha em labels.)
 */
function safeLabel(text) {
  if (text === null || text === undefined) return ''
  return String(text)
    .replace(/[\r\n]+/g, ' ')
    .replace(/"/g, "'")
    .trim()
}

/**
 * Extrai endpoints da especificação OpenAPI
 * @param {Object} spec - Especificação OpenAPI
 * @returns {Array} Lista de endpoints formatados
 */
export function extractEndpoints(spec) {
  const endpoints = []
  
  if (!spec.paths) return endpoints
  
  for (const [path, methods] of Object.entries(spec.paths)) {
    for (const [method, details] of Object.entries(methods)) {
      // Ignora propriedades especiais como 'parameters'
      if (['parameters', 'servers', 'summary', 'description'].includes(method)) {
        continue
      }
      
      endpoints.push({
        id: `${method}-${path}`.replace(/[^a-zA-Z0-9]/g, '_'),
        path,
        method: method.toUpperCase(),
        summary: details.summary || '',
        description: details.description || '',
        tags: details.tags || ['Sem tag'],
        operationId: details.operationId || '',
        parameters: details.parameters || [],
        requestBody: details.requestBody || null,
        responses: details.responses || {},
        deprecated: details.deprecated || false
      })
    }
  }
  
  return endpoints
}

/**
 * Agrupa endpoints por tags
 * @param {Array} endpoints - Lista de endpoints
 * @returns {Object} Endpoints agrupados por tag
 */
export function groupEndpointsByTag(endpoints) {
  const groups = {}
  
  for (const endpoint of endpoints) {
    for (const tag of endpoint.tags) {
      if (!groups[tag]) {
        groups[tag] = []
      }
      groups[tag].push(endpoint)
    }
  }
  
  return groups
}

/**
 * Agrupa endpoints por caminho base (primeiro segmento)
 * @param {Array} endpoints - Lista de endpoints
 * @returns {Object} Endpoints agrupados por caminho base
 */
export function groupEndpointsByBasePath(endpoints) {
  const groups = {}
  
  for (const endpoint of endpoints) {
    const segments = endpoint.path.split('/').filter(Boolean)
    const basePath = segments[0] || 'root'
    
    if (!groups[basePath]) {
      groups[basePath] = []
    }
    groups[basePath].push(endpoint)
  }
  
  return groups
}

/**
 * Gera um diagrama Mermaid apenas com grupos (overview).
 * Evita explodir o tamanho do diagrama em specs grandes.
 *
 * @param {Object} params
 * @param {string} params.title
 * @param {Object} params.groups - { [groupName]: Endpoint[] }
 * @returns {string}
 */
export function generateMermaidGroupsOverview({ title, groups }) {
  let mermaid = 'flowchart TB\n'

  const apiTitle = safeLabel(title || 'API')
  mermaid += `    API["API: ${apiTitle}"]\n`
  mermaid += `    style API fill:#6366f1,stroke:#818cf8,color:#fff\n\n`

  const entries = Object.entries(groups || {}).sort((a, b) => {
    const ca = Array.isArray(a[1]) ? a[1].length : 0
    const cb = Array.isArray(b[1]) ? b[1].length : 0
    return cb - ca
  })

  entries.forEach(([groupName, groupEndpoints], idx) => {
    const groupId = `grp_${idx}`
    const count = Array.isArray(groupEndpoints) ? groupEndpoints.length : 0
    const label = safeLabel(groupName)
    mermaid += `    ${groupId}["Grupo: ${label} (${count})"]\n`
    mermaid += `    API --> ${groupId}\n`
    mermaid += `    style ${groupId} fill:#1a1a24,stroke:#22d3ee,color:#f1f5f9\n`
  })

  return mermaid
}

/**
 * Gera um diagrama Mermaid para um único grupo (drill-down).
 * Limita a quantidade de endpoints para não estourar o Mermaid.
 *
 * @param {Object} params
 * @param {string} params.title
 * @param {string} params.groupName
 * @param {Array} params.endpoints
 * @param {number} [params.maxEndpoints=200]
 * @returns {string}
 */
export function generateMermaidGroupFlowchart({ title, groupName, endpoints, maxEndpoints = 200 }) {
  let mermaid = 'flowchart TB\n'

  const apiTitle = safeLabel(title || 'API')
  const gName = safeLabel(groupName || 'Grupo')

  mermaid += `    API["API: ${apiTitle}"]\n`
  mermaid += `    style API fill:#6366f1,stroke:#818cf8,color:#fff\n\n`

  mermaid += `    GRP["Grupo: ${gName}"]\n`
  mermaid += `    API --> GRP\n`
  mermaid += `    style GRP fill:#1a1a24,stroke:#22d3ee,color:#f1f5f9\n\n`

  const list = Array.isArray(endpoints) ? endpoints : []
  const limited = list.slice(0, maxEndpoints)

  limited.forEach((endpoint, i) => {
    const nodeId = `ep_${i}`
    const shortPath = safeLabel(truncatePath(endpoint.path, 28))
    const label = `${endpoint.method} ${shortPath}`
    mermaid += `    ${nodeId}["${label}"]\n`
    mermaid += `    GRP --> ${nodeId}\n`
    mermaid += `    style ${nodeId} fill:#16161f,stroke:${getMethodColor(endpoint.method)},color:#f1f5f9\n`
  })

  const remaining = list.length - limited.length
  if (remaining > 0) {
    mermaid += `\n    MORE["... +${remaining} endpoints (filtre pela lista à esquerda)"]\n`
    mermaid += `    style MORE fill:#16161f,stroke:#64748b,color:#94a3b8\n`
    mermaid += `    GRP --> MORE\n`
  }

  return mermaid
}

/**
 * Converte especificação OpenAPI para dados de Mind Map estruturados
 * @param {Object} spec - Especificação OpenAPI
 * @returns {Object} Dados estruturados para o mind map
 */
export function parseOpenApiToMindMap(spec) {
  const endpoints = extractEndpoints(spec)
  const groupedByTag = groupEndpointsByTag(endpoints)
  const groupedByPath = groupEndpointsByBasePath(endpoints)
  
  return {
    title: spec.info?.title || 'API',
    version: spec.info?.version || '1.0.0',
    description: spec.info?.description || '',
    endpoints,
    groupedByTag,
    groupedByPath,
    stats: {
      totalEndpoints: endpoints.length,
      totalTags: Object.keys(groupedByTag).length,
      methods: countMethods(endpoints)
    }
  }
}

/**
 * Conta endpoints por método HTTP
 * @param {Array} endpoints - Lista de endpoints
 * @returns {Object} Contagem por método
 */
function countMethods(endpoints) {
  const counts = { GET: 0, POST: 0, PUT: 0, PATCH: 0, DELETE: 0 }
  
  for (const endpoint of endpoints) {
    if (counts[endpoint.method] !== undefined) {
      counts[endpoint.method]++
    }
  }
  
  return counts
}

/**
 * Retorna emoji correspondente ao método HTTP
 * @param {string} method - Método HTTP
 * @returns {string} Emoji
 */
/**
 * Retorna cor correspondente ao método HTTP
 * @param {string} method - Método HTTP
 * @returns {string} Cor hex
 */
function getMethodColor(method) {
  const colors = {
    GET: '#22d3ee',
    POST: '#10b981',
    PUT: '#f59e0b',
    PATCH: '#a78bfa',
    DELETE: '#f43f5e'
  }
  return colors[method] || '#6366f1'
}

/**
 * Trunca um caminho para exibição
 * @param {string} path - Caminho completo
 * @param {number} maxLength - Comprimento máximo
 * @returns {string} Caminho truncado
 */
function truncatePath(path, maxLength) {
  if (path.length <= maxLength) return path
  return path.substring(0, maxLength - 3) + '...'
}

/**
 * Encontra endpoint por ID
 * @param {Array} endpoints - Lista de endpoints
 * @param {string} id - ID do endpoint
 * @returns {Object|null} Endpoint encontrado
 */
export function findEndpointById(endpoints, id) {
  return endpoints.find(e => e.id === id) || null
}

/**
 * Busca endpoints por termo
 * @param {Array} endpoints - Lista de endpoints
 * @param {string} term - Termo de busca
 * @returns {Array} Endpoints filtrados
 */
export function searchEndpoints(endpoints, term) {
  const lowerTerm = term.toLowerCase()
  
  return endpoints.filter(endpoint => 
    endpoint.path.toLowerCase().includes(lowerTerm) ||
    endpoint.summary.toLowerCase().includes(lowerTerm) ||
    endpoint.description.toLowerCase().includes(lowerTerm) ||
    endpoint.method.toLowerCase().includes(lowerTerm) ||
    endpoint.tags.some(tag => tag.toLowerCase().includes(lowerTerm))
  )
}
