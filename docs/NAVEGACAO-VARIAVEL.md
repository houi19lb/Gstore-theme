# Navegação Variável (Desktop/Mobile) - Gstore

Este documento explica como a navegação desktop/mobile funciona hoje, onde ela é configurada
e quais são os principais pontos de atenção para manutenção e debug.

## Visão geral do fluxo

1. **Admin (plugin)** define quais menus usar:
   - `gstore_desktop`
   - `gstore_mobile`
2. **Tema** intercepta o bloco `core/navigation` e substitui por `wp_nav_menu`.
3. **Header JS** controla o drawer mobile e (se necessário) cria um drawer fallback.

## Onde configurar

Admin:
- **Loja → Navegação** (plugin GStore White Label)

## Arquivos-chave (tema)

- `inc/class-gstore-nav-menu.php`
  - Substitui o bloco `core/navigation` com `wp_nav_menu`.
  - Decide se é desktop/mobile via classes do bloco.
- `parts/header.html` e `templates/parts/header.html`
  - Podem conter blocos `wp:navigation` diferentes.
- `assets/js/header.js`
  - Controla o drawer mobile.
  - Fallback: cria o drawer se não existir no template.
- `functions.php`
  - Injeta um menu mobile oculto no footer como fallback (`gstore_render_mobile_menu_fallback`).

## Regras de decisão (tema)

1. Se o bloco tem classe `Gstore-nav--mobile`, usa `gstore_mobile`.
2. Caso contrário, se o bloco tem classe `Gstore-nav`/`Gstore-nav__menu`, usa `gstore_desktop`.
3. Se `gstore_mobile` não existir, faz fallback para `gstore_desktop`.

## Fallback do drawer mobile

Quando o template ativo não renderiza o drawer:
- O JS cria o drawer dinamicamente.
- Para evitar clonar o **menu desktop**, o tema injeta um menu mobile oculto no footer
  e o JS prioriza esse conteúdo.

Elementos relevantes:
- Fallback do footer: `#gstore-mobile-menu-fallback`
- Drawer: `.Gstore-mobile-drawer`
- Nav mobile: `.Gstore-nav--mobile`

## Erros comuns e como identificar

### 1) Menu mobile mostra o menu desktop
**Causa provável:** drawer criado via JS clonando o menu desktop.
**Correção:** garantir que o fallback do footer exista e o JS o priorize.

### 2) Menu não aparece em nenhum dispositivo
**Causa provável:** não há menus atribuídos em Loja → Navegação.
**Correção:** definir `gstore_desktop` e/ou `gstore_mobile`.

### 3) Menu aparece no desktop, mas não no mobile
**Causa provável:** template sem drawer + sem fallback.
**Correção:** usar `gstore_render_mobile_menu_fallback` + JS atualizado.

## Testes recomendados

- Admin: salvar e recarregar os selects de desktop/mobile.
- Frontend desktop: validar conteúdo do menu desktop.
- Frontend mobile: validar conteúdo do menu mobile e o fallback quando mobile = 0.

## Observações para LLMS/automação

- Sempre verificar **templates ativos** (podem ser `parts/` ou `templates/parts/`).
- O drawer pode existir ou não no HTML; o JS cria quando necessário.
- Se o menu mobile não aparece, revisar `gstore_render_mobile_menu_fallback` e `header.js`.
