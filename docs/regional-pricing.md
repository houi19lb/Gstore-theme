# Interface de preços regionais

O módulo `inc/gstore-regional-pricing.php` depende do serviço
`GStore\Services\Regional_Pricing_Service` no plugin GSTORE. Sem o serviço ou com o
recurso desativado, não carrega os novos assets nem apresenta os controles.

O plugin é responsável pelas regras, MaxMind, credenciais, cookie, permissões e
preço final. A documentação operacional completa é `docs/maintenance/regional-pricing.md`
no repositório GSTORE. Não armazenar a base MaxMind nem sua licença no tema.

## Fluxo

- Primeira visita: estado sugerido junto ao aviso de maioridade. Alteração manual
  prevalece sobre uma resposta tardia do IP. A confirmação de idade continua separada
  da seleção de região e mantém seu prazo anterior de 30 dias.
- Idade já confirmada e região ausente: apresenta somente o diálogo de região.
- Cabeçalho: botão **Região** abre um diálogo acessível também no celular. Produtos
  exibem a UF atual ou a indicação de preço geral e permitem mudar a seleção.
- O cliente pode continuar sem informar. Falha de localização permite seleção manual;
  falha de rede não impede confirmar maioridade quando nenhuma UF foi solicitada.
- Troca de UF salva no servidor, remove fragmentos antigos de mini-carrinho e recarrega
  a página para alinhar preços, parcelas e marcação estruturada.
- Checkout informa que o destino/retirada determina o preço final.
- Autocomplete e tabela comparativa têm chaves de cache por UF e revisão de preços.

## Verificação local

`npm run build:assets`, `npm run check:assets`, `npm run check:css-scope` e
`npx jest tests/regional-pricing.test.js tests/product-card-installments.test.js tests/single-product-price-visibility.test.js --runInBand`.

Em 2026-10-06, 29 testes JS passaram, incluindo arquivos fonte e minificado. A prévia
local, com dados sintéticos e o markup real dos diálogos, foi inspecionada em
1440×1100, 1024×900 e 390×844, sem erro JS. Não equivale a homologação WordPress.

O audit de referência `visual:audit` encontrou 174 capturas ausentes nesta worktree;
nenhuma captura real da loja foi substituída ou declarada concluída. A mudança não
foi publicada. Capturas autenticadas reais, cache/CDN e pagamento permanecem critérios
de homologação antes de promoção. Assets novos de vitrine totalizam cerca de 7 KB
minificados antes de compressão HTTP e só carregam com o recurso ativo.
