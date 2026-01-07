# API Visualizer - Mind Map

<div align="center">

![React](https://img.shields.io/badge/React-18.2-61DAFB?style=flat-square&logo=react)
![Mermaid](https://img.shields.io/badge/Mermaid-10.6-FF3670?style=flat-square)
![Vite](https://img.shields.io/badge/Vite-5.0-646CFF?style=flat-square&logo=vite)
![OpenAPI](https://img.shields.io/badge/OpenAPI-3.0-6BA539?style=flat-square&logo=openapiinitiative)

</div>

Um aplicativo React moderno para visualizar documentação OpenAPI/Swagger como diagramas de Mind Map interativos.

## ✨ Funcionalidades

- **📊 Mind Map Visual** - Visualize todos os endpoints da sua API em um diagrama interativo
- **📝 Swagger UI** - Documentação interativa completa da API
- **🔍 Busca de Endpoints** - Encontre rapidamente endpoints por path, método ou descrição
- **💡 Tooltips Informativos** - Passe o mouse sobre os nós para ver detalhes do endpoint
- **📁 Upload de Arquivos** - Carregue arquivos JSON ou YAML
- **🔗 URL Externa** - Carregue especificações diretamente de URLs
- **🎨 Tema Escuro** - Interface moderna e elegante com tema escuro
- **📤 Exportar SVG** - Exporte o diagrama como arquivo SVG
- **🔄 Zoom e Pan** - Controles de zoom para visualização detalhada

## 🚀 Início Rápido

### Pré-requisitos

- Node.js 18+ 
- npm ou yarn

## 🔌 Integração com WordPress (tema GStore)

O tema registra o shortcode **`[api_visualizer]`** e injeta automaticamente as URLs do WordPress no app via:

- `window.API_VISUALIZER_CONFIG.homeUrl`
- `window.API_VISUALIZER_CONFIG.restUrl`
- `window.API_VISUALIZER_CONFIG.siteUrl`
- `window.API_VISUALIZER_CONFIG.ajaxUrl`

### Como usar no WP

- Crie/edite uma página no WordPress e cole:
  - `[api_visualizer]`

### Produção (recomendado)

Você precisa gerar o build para criar `api-visualizer/dist/.vite/manifest.json` (o PHP usa esse arquivo para enfileirar os assets):

```bash
cd api-visualizer
npm run build
```

### Desenvolvimento (fallback)

Se **`WP_DEBUG`** estiver ligado e **não existir** `dist/.vite/manifest.json`, o tema tenta carregar o app via Vite dev server em `http://localhost:3000`.

### Instalação

```bash
# Navegue até a pasta do projeto
cd api-visualizer

# Instale as dependências
npm install

# Inicie o servidor de desenvolvimento
npm run dev
```

O aplicativo estará disponível em `http://localhost:3000`

### Build para Produção

```bash
npm run build
```

Os arquivos de build serão gerados na pasta `dist/`.

## 📖 Como Usar

### 1. Carregar uma Especificação

Você pode carregar uma especificação OpenAPI/Swagger de duas formas:

**Por URL:**
- Cole a URL da sua especificação (ex: `https://petstore.swagger.io/v2/swagger.json`)
- Clique em "Carregar"

**Por Upload:**
- Arraste e solte um arquivo `.json` ou `.yaml`
- Ou clique na área de upload para selecionar um arquivo

### 2. Navegar pelo Mind Map

- Use os controles de zoom para aproximar ou afastar
- Passe o mouse sobre os nós para ver informações detalhadas
- Clique em um endpoint para selecioná-lo na lista lateral

### 3. Alternar Visualizações

- **Mind Map**: Visualização em diagrama
- **Swagger UI**: Documentação interativa padrão

## 🛠️ Tecnologias

| Tecnologia | Descrição |
|------------|-----------|
| [React 18](https://react.dev/) | Biblioteca UI |
| [Vite](https://vitejs.dev/) | Build tool |
| [Mermaid](https://mermaid.js.org/) | Diagramas |
| [Swagger UI React](https://github.com/swagger-api/swagger-ui) | Documentação OpenAPI |
| [Lucide React](https://lucide.dev/) | Ícones |
| [js-yaml](https://github.com/nodeca/js-yaml) | Parser YAML |

## 📁 Estrutura do Projeto

```
api-visualizer/
├── src/
│   ├── components/
│   │   ├── Header.jsx       # Cabeçalho com navegação
│   │   ├── Sidebar.jsx      # Painel lateral com upload e lista
│   │   ├── MindMapView.jsx  # Visualização do mind map
│   │   ├── SwaggerView.jsx  # Visualização Swagger UI
│   │   └── Tooltip.jsx      # Tooltips informativos
│   ├── utils/
│   │   └── openApiParser.js # Parser de especificações
│   ├── styles/
│   │   └── index.css        # Estilos globais
│   ├── App.jsx              # Componente principal
│   └── main.jsx             # Ponto de entrada
├── public/                  # Assets estáticos
├── index.html               # HTML template
├── package.json             # Dependências
├── vite.config.js           # Configuração Vite
└── README.md                # Documentação
```

## 🎨 Customização

### Cores do Tema

As cores podem ser customizadas no arquivo `src/styles/index.css`:

```css
:root {
  --color-accent-primary: #6366f1;  /* Cor principal */
  --color-get: #22d3ee;             /* Método GET */
  --color-post: #10b981;            /* Método POST */
  --color-put: #f59e0b;             /* Método PUT */
  --color-patch: #a78bfa;           /* Método PATCH */
  --color-delete: #f43f5e;          /* Método DELETE */
}
```

### Fontes

O projeto usa:
- **Outfit** - Títulos e texto geral
- **JetBrains Mono** - Código e paths

## 📄 Formatos Suportados

- OpenAPI 3.0.x (JSON/YAML)
- OpenAPI 3.1.x (JSON/YAML)  
- Swagger 2.0 (JSON/YAML)

## 🔗 Exemplos de APIs para Teste

- [Petstore](https://petstore.swagger.io/v2/swagger.json)
- [JSONPlaceholder](https://jsonplaceholder.typicode.com/)
- [GitHub API](https://raw.githubusercontent.com/github/rest-api-description/main/descriptions/api.github.com/api.github.com.json)

## 📝 Licença

Este projeto é parte do tema GStore e segue as mesmas licenças do projeto principal.

---

<div align="center">
Desenvolvido com ❤️ para visualização de APIs
</div>
