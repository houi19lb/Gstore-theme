# Refatoração do Sistema de Estilos - Gstore Theme

## 📋 Visão Geral

Este documento descreve a refatoração do sistema de estilos do tema Gstore, organizando o código CSS em uma estrutura modular, reutilizável e responsiva.

## 🎯 Objetivos

1. **Organização**: Separar estilos em módulos lógicos e manuteníveis
2. **Reutilização**: Criar componentes e classes utilitárias reutilizáveis
3. **Responsividade**: Implementar sistema de breakpoints consistente
4. **Manutenibilidade**: Facilitar futuras modificações e extensões

## 📁 Nova Estrutura

```
themes/gstore/
├── assets/
│   └── css/
│       ├── tokens.css              # Design tokens (variáveis CSS)
│       ├── base.css                # Reset e estilos base
│       ├── utilities.css           # Classes utilitárias
│       ├── responsive.css          # Sistema responsivo e breakpoints
│       ├── gstore-main.css         # Arquivo principal (importa todos os módulos)
│       ├── components/             # Componentes reutilizáveis
│       │   ├── buttons.css
│       │   ├── cards.css
│       │   └── product-card.css
│       ├── layouts/                # Layouts específicos
│       │   ├── header.css
│       │   └── home.css
│       ├── cart.css                # Estilos específicos (já existentes)
│       ├── checkout.css
│       └── checkout-steps.css
└── style.css                       # Estilos legados (mantido para compatibilidade)
```

## 🎨 Sistema de Design Tokens

Todas as variáveis CSS estão centralizadas em `tokens.css`:

- **Cores**: Semânticas e nomes descritivos
- **Tipografia**: Tamanhos, pesos, alturas de linha
- **Espaçamentos**: Sistema baseado em 4px
- **Sombras**: Hierarquia visual consistente
- **Breakpoints**: Valores padronizados para media queries

### Exemplo de uso:

```css
/* Antes */
.card {
  background: #f0f2f5;
  padding: 24px;
  border-radius: 4px;
}

/* Depois */
.card {
  background: var(--gstore-color-bg-muted);
  padding: var(--gstore-spacing-6);
  border-radius: var(--gstore-radius-base);
}
```

## 🧩 Componentes Reutilizáveis

### Botões

```html
<button class="Gstore-btn Gstore-btn--primary">Botão Principal</button>
<button class="Gstore-btn Gstore-btn--secondary">Botão Secundário</button>
<button class="Gstore-btn Gstore-btn--outline">Botão Outline</button>
```

### Cards

```html
<div class="Gstore-card">
  <div class="Gstore-card__image">
    <img src="..." alt="...">
  </div>
  <div class="Gstore-card__body">
    <h3 class="Gstore-card__title">Título</h3>
    <p class="Gstore-card__content">Conteúdo</p>
  </div>
</div>
```

## 🔧 Classes Utilitárias

Sistema de classes utilitárias para uso rápido:

### Espaçamentos

```html
<div class="mt-4 mb-6 px-4 py-8">
  Conteúdo com espaçamento
</div>
```

### Tipografia

```html
<h1 class="text-3xl font-bold text-primary">Título</h1>
<p class="text-sm text-muted uppercase tracking-wide">Subtítulo</p>
```

### Layout

```html
<div class="flex items-center justify-between gap-4">
  <span>Item 1</span>
  <span>Item 2</span>
</div>
```

## 📱 Sistema Responsivo

### Breakpoints

- `sm`: 640px (tablets pequenos)
- `md`: 768px (tablets)
- `lg`: 1024px (desktop pequeno)
- `xl`: 1280px (desktop)
- `2xl`: 1536px (desktop grande)

### Grids Responsivos

```html
<div class="Gstore-responsive-grid">
  <!-- Grid que se adapta de 1 a 4 colunas -->
</div>

<div class="Gstore-responsive-grid-2">
  <!-- Grid de 2 colunas responsivo -->
</div>
```

### Espaçamentos Fluidos

```css
/* Padding responsivo com clamp */
.element {
  padding: clamp(24px, 4vw, 64px) clamp(16px, 4vw, 48px);
}
```

## 🔄 Migração Gradual

A refatoração foi feita de forma **não destrutiva**:

1. ✅ Novo sistema modular criado em `assets/css/`
2. ✅ `style.css` original mantido (compatibilidade)
3. ✅ Novo sistema carregado **antes** do CSS legado
4. ⏳ Estilos legados podem ser migrados gradualmente

### Próximos Passos

1. Migrar estilos de produtos para módulos
2. Migrar estilos de páginas específicas
3. Consolidar estilos duplicados
4. Remover código legado após migração completa

### Melhorias Futuras / TODOs

#### Botão de Checkout - Correção de Tokens
**Localização**: `assets/css/checkout.css` (linhas ~1262-1280) e `style.css` (final do arquivo)

**Problema**: O CSS do botão checkout (`#add_payment_method .wc-proceed-to-checkout a.checkout-button`, etc.) está usando `!important` e múltiplos seletores para sobrescrever estilos do WooCommerce que aplicam `font-size: 1.25em` diretamente.

**Status Atual**: ✅ Funcionando com workaround usando `!important` e alta especificidade

**Melhoria Necessária**:
- Investigar origem do CSS do WooCommerce que aplica `font-size: 1.25em` inline ou via plugin
- Encontrar forma mais elegante de sobrescrever sem usar `!important`
- Possivelmente criar um filtro WordPress para modificar o CSS do WooCommerce na origem
- Considerar criar um componente `.Gstore-checkout-button` padronizado para substituir o botão nativo do WooCommerce

**Arquivos Afetados**:
- `assets/css/checkout.css`
- `style.css`

## 📝 Convenções de Nomenclatura

### BEM (Block Element Modifier)

```css
/* Block */
.Gstore-card { }

/* Element */
.Gstore-card__title { }
.Gstore-card__body { }

/* Modifier */
.Gstore-card--elevated { }
.Gstore-btn--primary { }
```

### Prefixos

- `.Gstore-*` - Componentes e layouts do tema
- Classes utilitárias sem prefixo (ex: `.mt-4`, `.text-center`)

## 🚀 Como Usar

### Carregamento Automático

O sistema é carregado automaticamente via `functions.php`. Não é necessário alterar nada.

### Importar em CSS Custom

Se precisar importar módulos em CSS custom:

```css
@import url('../assets/css/tokens.css');
@import url('../assets/css/utilities.css');
```

## 🔍 Benefícios

1. **Organização**: Fácil encontrar e modificar estilos
2. **Consistência**: Design tokens garantem visual uniforme
3. **Manutenibilidade**: Módulos pequenos e focados
4. **Performance**: Possibilidade de carregar apenas módulos necessários
5. **Reutilização**: Componentes prontos para uso
6. **Responsividade**: Sistema padronizado para todos os breakpoints

## 📚 Documentação Adicional

- [Design Tokens](./assets/css/tokens.css) - Variáveis CSS disponíveis
- [Componentes](./assets/css/components/) - Componentes reutilizáveis
- [Utilities](./assets/css/utilities.css) - Classes utilitárias

## ⚠️ Notas Importantes

- **Compatibilidade**: Estilos legados ainda funcionam
- **Gradual**: Migração pode ser feita aos poucos
- **Testes**: Testar cada módulo após migração
- **Backup**: Sempre fazer backup antes de grandes mudanças

## 🐛 Troubleshooting

### Estilos não aparecem?

1. Verifique se o arquivo foi carregado (DevTools > Network)
2. Confirme a ordem de carregamento no `functions.php`
3. Verifique conflitos de especificidade CSS

### Conflitos com estilos legados?

- Use `!important` apenas quando necessário
- Aumente a especificidade do seletor
- Considere migrar o estilo conflitante para o novo sistema

## 📞 Suporte

Para dúvidas sobre a estrutura, consulte:
- Este documento
- Comentários nos arquivos CSS
- Código fonte dos componentes




