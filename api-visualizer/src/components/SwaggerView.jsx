import { useEffect, useRef } from 'react'
import SwaggerUI from 'swagger-ui-react'
import 'swagger-ui-react/swagger-ui.css'
import { FileJson } from 'lucide-react'

function SwaggerView({ spec, isLoading }) {
  const containerRef = useRef(null)

  // Injeta estilos customizados para o Swagger UI
  useEffect(() => {
    const style = document.createElement('style')
    style.textContent = `
      .swagger-ui {
        font-family: 'Outfit', sans-serif !important;
      }
      
      .swagger-ui .topbar {
        display: none !important;
      }
      
      .swagger-ui .info .title {
        font-family: 'Outfit', sans-serif !important;
        color: #f1f5f9 !important;
      }
      
      .swagger-ui .info {
        margin: 20px 0 !important;
      }
      
      .swagger-ui .info .description p,
      .swagger-ui .info .description {
        color: #94a3b8 !important;
        font-family: 'Outfit', sans-serif !important;
      }
      
      .swagger-ui .scheme-container {
        background: #16161f !important;
        box-shadow: none !important;
        padding: 16px !important;
      }
      
      .swagger-ui .opblock-tag {
        color: #f1f5f9 !important;
        font-family: 'Outfit', sans-serif !important;
        border-bottom: 1px solid rgba(255,255,255,0.08) !important;
      }
      
      .swagger-ui .opblock-tag:hover {
        background: rgba(99, 102, 241, 0.1) !important;
      }
      
      .swagger-ui .opblock {
        background: #16161f !important;
        border-radius: 10px !important;
        margin-bottom: 8px !important;
        border: 1px solid rgba(255,255,255,0.08) !important;
      }
      
      .swagger-ui .opblock .opblock-summary {
        border: none !important;
      }
      
      .swagger-ui .opblock .opblock-summary-method {
        font-family: 'JetBrains Mono', monospace !important;
        border-radius: 6px !important;
        font-size: 12px !important;
        min-width: 70px !important;
      }
      
      .swagger-ui .opblock .opblock-summary-path {
        font-family: 'JetBrains Mono', monospace !important;
        color: #f1f5f9 !important;
      }
      
      .swagger-ui .opblock .opblock-summary-path-description-wrapper,
      .swagger-ui .opblock .opblock-summary-description {
        color: #94a3b8 !important;
      }
      
      .swagger-ui .opblock.opblock-get {
        border-color: #22d3ee !important;
        background: rgba(34, 211, 238, 0.05) !important;
      }
      
      .swagger-ui .opblock.opblock-get .opblock-summary-method {
        background: #22d3ee !important;
      }
      
      .swagger-ui .opblock.opblock-post {
        border-color: #10b981 !important;
        background: rgba(16, 185, 129, 0.05) !important;
      }
      
      .swagger-ui .opblock.opblock-post .opblock-summary-method {
        background: #10b981 !important;
      }
      
      .swagger-ui .opblock.opblock-put {
        border-color: #f59e0b !important;
        background: rgba(245, 158, 11, 0.05) !important;
      }
      
      .swagger-ui .opblock.opblock-put .opblock-summary-method {
        background: #f59e0b !important;
      }
      
      .swagger-ui .opblock.opblock-delete {
        border-color: #f43f5e !important;
        background: rgba(244, 63, 94, 0.05) !important;
      }
      
      .swagger-ui .opblock.opblock-delete .opblock-summary-method {
        background: #f43f5e !important;
      }
      
      .swagger-ui .opblock.opblock-patch {
        border-color: #a78bfa !important;
        background: rgba(167, 139, 250, 0.05) !important;
      }
      
      .swagger-ui .opblock.opblock-patch .opblock-summary-method {
        background: #a78bfa !important;
      }
      
      .swagger-ui .opblock-body {
        background: #12121a !important;
      }
      
      .swagger-ui .opblock-section-header {
        background: #1a1a24 !important;
        box-shadow: none !important;
      }
      
      .swagger-ui .opblock-section-header h4,
      .swagger-ui .opblock-section-header label {
        color: #f1f5f9 !important;
      }
      
      .swagger-ui table thead tr th,
      .swagger-ui table thead tr td {
        color: #94a3b8 !important;
        border-color: rgba(255,255,255,0.08) !important;
      }
      
      .swagger-ui table tbody tr td {
        color: #f1f5f9 !important;
        border-color: rgba(255,255,255,0.08) !important;
      }
      
      .swagger-ui .parameter__name,
      .swagger-ui .parameter__type,
      .swagger-ui .parameter__in {
        color: #94a3b8 !important;
      }
      
      .swagger-ui .parameter__name.required span {
        color: #f43f5e !important;
      }
      
      .swagger-ui .btn {
        font-family: 'Outfit', sans-serif !important;
        border-radius: 6px !important;
      }
      
      .swagger-ui .btn.execute {
        background: #6366f1 !important;
        border-color: #6366f1 !important;
      }
      
      .swagger-ui .btn.execute:hover {
        background: #818cf8 !important;
      }
      
      .swagger-ui select {
        background: #1a1a24 !important;
        color: #f1f5f9 !important;
        border: 1px solid rgba(255,255,255,0.08) !important;
        border-radius: 6px !important;
      }
      
      .swagger-ui input[type=text],
      .swagger-ui textarea {
        background: #1a1a24 !important;
        color: #f1f5f9 !important;
        border: 1px solid rgba(255,255,255,0.08) !important;
        border-radius: 6px !important;
      }
      
      .swagger-ui input[type=text]:focus,
      .swagger-ui textarea:focus {
        border-color: #6366f1 !important;
        outline: none !important;
      }
      
      .swagger-ui .model-box {
        background: #1a1a24 !important;
      }
      
      .swagger-ui .model {
        color: #f1f5f9 !important;
      }
      
      .swagger-ui .model-title {
        color: #f1f5f9 !important;
      }
      
      .swagger-ui .prop-type {
        color: #22d3ee !important;
      }
      
      .swagger-ui .response-col_status {
        color: #f1f5f9 !important;
      }
      
      .swagger-ui .response-col_description {
        color: #94a3b8 !important;
      }
      
      .swagger-ui .responses-inner h4,
      .swagger-ui .responses-inner h5 {
        color: #f1f5f9 !important;
      }
      
      .swagger-ui .microlight,
      .swagger-ui .highlight-code {
        background: #0a0a0f !important;
        border-radius: 8px !important;
      }
      
      .swagger-ui .microlight code,
      .swagger-ui .highlight-code code {
        color: #f1f5f9 !important;
      }
      
      .swagger-ui .download-contents {
        background: #6366f1 !important;
        color: white !important;
      }
      
      .swagger-ui .model-container {
        background: #16161f !important;
      }
      
      .swagger-ui section.models {
        border: 1px solid rgba(255,255,255,0.08) !important;
        border-radius: 10px !important;
      }
      
      .swagger-ui section.models h4 {
        color: #f1f5f9 !important;
      }
      
      .swagger-ui .models-control {
        background: none !important;
      }
      
      .swagger-ui .model-box-control:focus,
      .swagger-ui .models-control:focus {
        outline: none !important;
      }
    `
    document.head.appendChild(style)

    return () => {
      document.head.removeChild(style)
    }
  }, [])

  if (!spec) {
    return (
      <div className="mindmap-area">
        <div className="empty-state">
          <FileJson size={80} className="empty-state-icon" />
          <h3 className="empty-state-title">Nenhuma API carregada</h3>
          <p className="empty-state-description">
            Carregue uma especificação OpenAPI/Swagger usando a barra lateral 
            para visualizar a documentação interativa.
          </p>
        </div>
      </div>
    )
  }

  return (
    <div className="swagger-container" ref={containerRef}>
      {isLoading ? (
        <div className="loading-overlay">
          <div className="loading-spinner" />
        </div>
      ) : (
        <SwaggerUI spec={spec} />
      )}
    </div>
  )
}

export default SwaggerView
