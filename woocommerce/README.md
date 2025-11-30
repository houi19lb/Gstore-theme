# WooCommerce Templates - Gstore

## ⚠️ IMPORTANTE: Este projeto usa BLOCOS Gutenberg

**Sistema atual:** Blocos do WooCommerce (Product Collection)  
**Verificado em:** 2025-11-15  
**Versão WooCommerce:** 9.4.0+

## 📋 Status dos Templates

### ❌ Templates NÃO Utilizados

- `content-product.php` - **NÃO USADO** (existe apenas para compatibilidade futura)

**Por quê?**
- O site usa blocos Gutenberg para exibir produtos
- Páginas criadas no Editor de Blocos
- WooCommerce renderiza usando classes `.wc-block-*`
- Templates PHP clássicos são ignorados

### ✅ Como Customizar Produtos

**Use CSS**, não PHP!

**Arquivos corretos:**
- `themes/gstore/style.css` (linhas 473-671)
- `themes/gstore/functions.php` (linhas 140-224 - estilos críticos)

**Classes principais:**
```css
.wc-block-product-template  /* Grid de produtos */
.wc-block-product           /* Card individual */
.wc-block-components-product-image  /* Imagem */
.wp-block-post-title        /* Título */
.wp-block-woocommerce-product-price /* Preço */
.wp-block-button            /* Botão de compra */
```

## 🔄 Se Mudar para Loop Clássico

**Se no futuro o projeto mudar para usar loop clássico:**

1. Desabilitar página de loja criada com blocos
2. Usar shortcode `[products]` ou arquivo `archive-product.php`
3. Habilitar template `content-product.php`
4. Desabilitar estilos críticos inline em `functions.php`
5. Adicionar CSS para classes `.product`, não `.wc-block-product`

## 📝 Como Identificar o Sistema

### Blocos Gutenberg (ATUAL)
```html
<li class="wc-block-product">
<ul class="wc-block-product-template">
<div data-block-name="woocommerce/product-collection">
```

### Loop Clássico (SE MUDAR)
```html
<li class="product type-product">
<ul class="products">
<!-- Sem prefixo wc-block- -->
```

## 📚 Documentação

- **Completa:** `themes/gstore/BLOCOS-WOOCOMMERCE.md`
- **Regras:** `themes/gstore/REGRAS-MELHORIAS.md` (seção 0)
- **Demo:** `themes/gstore/demo-cards.html`

## ✅ Checklist de Verificação

Antes de modificar qualquer código relacionado a produtos:

- [ ] Confirmo que ainda estamos usando blocos? (F12 > Inspecionar)
- [ ] Li a documentação em `BLOCOS-WOOCOMMERCE.md`?
- [ ] Vou modificar CSS, não criar templates PHP?
- [ ] Testei no navegador antes de considerar completo?

## 🚫 O Que NÃO Fazer

❌ Modificar `content-product.php` (não será usado)  
❌ Criar hooks do WooCommerce para customizar loop  
❌ Usar `woocommerce_template_loop_*` filters  
❌ Sobrescrever outros templates clássicos sem verificar  

## ✅ O Que Fazer

✅ Modificar CSS em `style.css`  
✅ Usar `::before` e `::after` para adicionar conteúdo  
✅ Adicionar classes customizadas via CSS  
✅ Usar estilos inline críticos se necessário  

---

**Última atualização:** 2025-11-15  
**Mantido por:** Equipe Gstore



