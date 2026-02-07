# Cache e Carrinho (Hostinger / LiteSpeed)

## Problema

Em ambientes com **cache de página inteira** (ex.: LiteSpeed Cache na Hostinger), a home (e às vezes outras páginas) pode ser servida em cache. Isso causa:

1. **Resposta da Store API em cache** – `GET /wp-json/wc/store/v1/cart` pode devolver carrinho vazio de outra sessão.
2. **Cookies de sessão WooCommerce** – a resposta em cache pode não enviar ou pode “limpar” cookies, fazendo o carrinho parecer vazio ao sair de uma página de produto e voltar para a home.

## Solução recomendada (servidor)

1. **Excluir a Store API do carrinho do cache**
   - No LiteSpeed Cache: **Cache → Excludes → Do Not Cache URIs**  
   - Incluir: `/wp-json/wc/store/v1/cart`
   - Assim a API do carrinho nunca será servida em cache.

2. **Não cachear a home para visitantes com carrinho** (opcional, mais robusto)
   - **Cache → Tuning → “Vary”** (ou equivalente): ativar **Vary for Cookie** e incluir o cookie de sessão WooCommerce (ex.: `woocommerce_session_*` ou o nome que o WooCommerce usar).
   - Ou, no **Do Not Cache URIs**, incluir `/` apenas se for aceitável não cachear a home.

3. **Não cachear páginas de carrinho/checkout**
   - Em **Do Not Cache URIs**: `/carrinho/`, `/checkout/`, `/minha-conta/` (e equivalentes no seu site).

## Mitigação no tema

O script `mini-cart-fix.js` inclui uma mitigação: quando a API retorna **0 itens** e o contador no DOM já mostra **mais de 0**, a resposta é tratada como possivelmente em cache. Nesse caso:

- O badge **não** é atualizado para 0.
- É feito **um novo pedido** à API após 600 ms; o resultado desse retry é o que atualiza o store e o DOM.

Isso reduz o efeito de “carrinho sumindo” em páginas em cache, mas a solução correta é configurar o cache no servidor conforme acima.
