# Frete na Página de Produto — Implementação no Tema

## Contexto rápido (LLMs/novos devs)
- Documento do frete na **página de produto**.
- Tema chama endpoint AJAX do plugin `gstore_calculate_shipping`.

Este documento descreve o que foi feito no tema para o cálculo e exibição de frete na **página de produto**.

## Objetivo

Integrar o tema ao endpoint AJAX do plugin para calcular frete e renderizar a resposta na UI do produto.

## Endpoint utilizado (plugin)

**POST** `/wp-admin/admin-ajax.php`

Parâmetros obrigatórios:
- `action`: `gstore_calculate_shipping`
- `nonce`: gerado com `gstore_shipping_calculator`
- `postcode`: CEP (com ou sem hífen)
- `product_id`: ID do produto
- `quantity`: quantidade

Resposta (sucesso):
```json
{
  "success": true,
  "data": {
    "rates": [
      { "label": "Frete Terrestre", "cost_formatted": "R$ 45,00", "mode": "ground" },
      { "label": "Frete Aéreo", "cost_formatted": "R$ 80,00", "mode": "air" }
    ],
    "destination": { "city": "Rio de Janeiro", "state": "RJ" }
  }
}
```

Resposta (erro):
```json
{
  "success": false,
  "data": { "message": "Não foi possível calcular o frete para este destino." }
}
```

## Onde está no tema

Arquivos relevantes:
- `assets/js/shipping-calculator.js`
- `functions.php`
- `assets/css/shipping-calculator.css` (estilos do bloco)
- `woocommerce/content-single-product.php` (HTML do componente na página)

## O que foi implementado

### 1) Nonce e dados iniciais do produto

O tema passa o nonce e o `product_id` para o JS com `wp_localize_script`:

- `nonce`: `wp_create_nonce('gstore_shipping_calculator')`
- `productId`: ID do produto atual
- `quantity`: quantidade inicial (fallback em 1)

Arquivo: `functions.php` (localização do script do calculador).

### 2) Chamada AJAX do endpoint do plugin

No JS do calculador (`assets/js/shipping-calculator.js`):

- Monta o payload com `action`, `nonce`, `postcode`, `product_id` e `quantity`.
- Sanitiza o CEP removendo caracteres não numéricos.
- Resolve a URL do AJAX via `admin-ajax.php` (ou `wc_checkout_params` quando existir).

### 3) Renderização de múltiplas modalidades

O componente **não usa mais apenas `rates[0]`**. Ele:

- Itera `data.rates` e renderiza **uma linha por modalidade**.
- Identifica o modo por `rate.mode` quando disponível.
- Usa o `label` do rate para exibir o nome (por ex. “Frete Terrestre” e “Frete Aéreo”).
- Exibe o destino com `data.destination.city` e `data.destination.state`.

Exemplo visual:
```
Frete Terrestre: R$ 45,00
Frete Aéreo: R$ 80,00
Destino: Rio de Janeiro/RJ
```

### 4) Tratamento de erro

Se `success=false`, exibe `data.message`.
Se o cálculo não retornar rates, mostra mensagem padrão de erro.

## Checklist rápido

- O nonce está sendo gerado pelo tema.
- O endpoint AJAX do plugin é chamado com os parâmetros corretos.
- A lista de `rates` é renderizada completa (terrestre + aéreo).
- O destino (cidade/UF) é exibido quando disponível.
- Erros do backend aparecem na UI.
