# Links internos 3XX globais

## Regra

Links globais de header, menu, footer, mobile drawer, cart e mini-cart devem apontar direto para a URL final indexavel ou operacional. Redirects continuam como fallback, mas nao devem ser emitidos no HTML publico.

Use estes destinos:

- Minha conta: `/minha-conta/`
- Atendimento: `/atendimento/`
- Catalogo: `/catalogo/`
- Carrinho: `/carrinho/`
- Programas: `/categoria-produto/programas/`
- Pro Training: `/categoria-produto/pro-training/`
- Clube de Tiro: `/categoria-produto/clube-de-tiro/`

## Implementacao

- Links fixos devem usar o helper `gstore_get_public_canonical_url()` quando estiverem em PHP.
- Templates HTML devem usar a URL final com barra final.
- Menus vindos do admin passam pela allowlist `gstore_get_internal_link_alias_map()`.
- O normalizador cobre apenas aliases conhecidos. Nao normalizar qualquer link interno automaticamente.
- O checkout real nao deve ser trocado globalmente. O mini-cart vazio pode apontar para carrinho para nao expor `/finalizar-compra/`, que redireciona quando nao ha itens.

## Validacao pre-deploy

Rode a checagem depois de subir em staging ou producao e limpar cache:

```powershell
node scripts/check-global-internal-links-3xx.mjs
```

Tambem e possivel passar URLs especificas:

```powershell
node scripts/check-global-internal-links-3xx.mjs https://armastore.com.br/ https://armastore.com.br/produto/exemplo/
```

O check falha se encontrar aliases conhecidos no HTML renderizado ou se um link global canonico responder com primeiro hop 3xx/4xx.
