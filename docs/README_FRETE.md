# README — Configuração de Frete (GStore)

Documento técnico da página **Loja → Frete**. Descreve UI, fluxos, persistência e pontos críticos de lógica.

## Onde a tela aparece
- **Menu**: Loja → Frete (submenu do WooCommerce, abaixo de “Ordem Home”).
- **Slug**: `gstore-frete`.
- **Root React**: `#gstore-react-frete`.
- **Entry build**: `src/loja/frete/index.jsx` → `build/frete.js`.

## Arquivos principais
- UI: `src/loja/frete/FreightConfigPage.jsx`
- Entry: `src/loja/frete/index.jsx`
- Enqueue/admin page: `includes/Pages/Freight/class-freight-page.php`
- API REST: `includes/APIs/class-freight-api.php`
- Persistência/sanitização: `includes/Services/class-freight-service.php`
- Menu (admin): `includes/Admin/Menu/class-menu-manager.php`

## Estrutura da UI (React)
Componentes e seções (ordem na tela):
- **VariationList**: lista e seleção de variações.
- **VariationEditor**:
  - Nome, modo de cobrança, slugs.
  - Preços terrestre/aéreo.
  - Permissões (allowLand/allowAir).
  - Tipo (arma/munição/acessório).
  - Ao marcar **munição**, o aéreo é **desativado automaticamente**.
  - **Excluir variação** (botão vermelho com modal).
- **GeneralRules**: regras gerais (munição por faixa, armas acima do limite).
- **AllowedLocations**: lista global de cidades/UF para terrestre e aéreo.
  - Importação por URL (JSON) para atualizar a lista terrestre.
  - Busca/autocomplete para inserir cidades válidas.
  - Validação das entradas terrestres com base na lista importada.
- **RegionsPricing**: regras por região (lista de cidades/UF + preços). Fica abaixo de “Regras gerais”.
- **SimulatorMini**: simulação com valores fixos de produto e frete real das variações.

## Estado inicial (front-end)
`getDefaultConfig()` define o fallback local:
- `variations`: base (arma de fogo, arma curta, arma longa, munição, acessório).
- `rules`:
  - `separateGunAmmo`: true
  - `baseAmmoLimit`: 7000
  - `gunSurchargeEnabled`: true
  - `gunSurchargeEnabledLand`: true
  - `gunSurchargeEnabledAir`: true
  - `gunSurchargeThreshold`: 10000
  - `gunSurchargePercent`: 10
- `locs`: `{ land: "", air: "" }`
- `regions`: `[]`

Se existir `window.gstoreFreightDefaults`, esse valor sobrescreve o fallback.

## Endpoints REST
**Base**: `gstore/v1`
- `GET /freight-config`
- `POST /freight-config`
- `GET /freight-cities`
- `POST /freight-cities/import`

## Integração por CEP (ViaCEP)
O cálculo de frete no front utiliza CEP e resolve **cidade/UF** via ViaCEP no backend, com cache de 90 dias e LRU de 200k entradas.

### Autoload ViaCEP
Para garantir que o autoloader PSR-4 encontre o serviço do ViaCEP, existe um arquivo ponte:
- `includes/Services/class-via-cep-service.php` → carrega `class-viacep-service.php`.

### Fluxo (checkout e AJAX)
1. Cliente informa CEP (8 dígitos).
2. Backend consulta ViaCEP e atualiza `state` e `city` (cache LRU).
3. A validação de `locs` usa **cidade/UF** resolvidas.
4. Se ViaCEP falhar, aplica fallback por faixa de CEP (somente UF).

### AJAX de frete (tema)
O endpoint AJAX do plugin retorna também `destination` com `city` e `state`:
```json
{
  "rates": [{ "id": "...", "label": "...", "cost": 10.5, "cost_formatted": "R$ 10,50" }],
  "destination": { "city": "São Paulo", "state": "SP" }
}
```

Em caso de erro, o endpoint também retorna `destination` para debug:
```json
{
  "message": "Não foi possível calcular o frete para este destino.",
  "destination": { "city": "", "state": "SP" }
}
```

**Observação:** o tema está em outro repositório. A integração do componente de CEP deve usar esse endpoint
e considerar `destination` retornado para mensagens/validações no front.

Permissões:
- Quando há permissão de menu configurada, exige `gstore-frete`.
- Fallback: `manage_woocommerce`.

Também utiliza:
- `GET /product-categories` para o picker de categorias.

## Persistência (option)
Option: `gstore_freight_config`
Option (cidades): `gstore_freight_cities`

Estrutura (sanitizada em `class-freight-service.php`):
```json
{
  "variations": [
    {
      "id": "arma-curta",
      "name": "Arma curta",
      "title": "Arma curta",
      "billingMode": "per_item | per_variation",
      "mainSlugs": "arma-curta",
      "extraSlugs": "arma,pecas",
      "landPrice": 0,
      "airPrice": 0,
      "allowLand": true,
      "allowAir": true,
      "isGun": true,
      "isAmmo": false,
      "isAccessory": false
    }
  ],
  "rules": {
    "separateGunAmmo": true,
    "baseAmmoLimit": 7000,
    "gunSurchargeEnabled": true,
    "gunSurchargeEnabledLand": true,
    "gunSurchargeEnabledAir": true,
    "gunSurchargeThreshold": 10000,
    "gunSurchargePercent": 10
  },
  "locs": {
    "land": "PR\nCuritiba;PR",
    "air": "SP\nSao Paulo;SP"
  },
  "regions": [
    {
      "id": "regiao-abc123",
      "name": "Sul",
      "locations": "PR\nCuritiba;PR",
      "landPrice": 0,
      "airPrice": 0,
      "variationPrices": {
        "arma-curta": { "land": 50, "air": 80 },
        "municao": { "land": 70, "air": 0 }
      }
    }
  ]
}
```

### Notas importantes
- `variationPrices` e `landPrice`/`airPrice` da região **não sobrescrevem** os preços globais.
- A região serve **apenas** para restringir a lista de cidades/UF.
- `isAccessory` é usado para classificar produtos no simulador.
- A importação de cidades guarda **apenas** `city`, `uf` e `cep` e atualiza automaticamente `locs.land`.

## Regras de negócio (UI)

### Munição (por faixa)
Campo: **Limite base de munição (R$)**  
Regra: a cada X no valor da munição, soma 1 frete.

Exemplos com X = 7000:
- 1 → 1 frete
- 7000 → 2 fretes
- 14000 → 3 fretes
- 21000 → 4 fretes

Implementação no simulador:
```
shipments = floor(ammoValue / baseLimit) + 1
```

### Armas acima do limite
Campos:
- **Limite (R$)** (`gunSurchargeThreshold`)
- **Aumento (%)** (`gunSurchargePercent`)
- **Toggle geral** (`gunSurchargeEnabled`)
- **Toggles por modalidade** (`gunSurchargeEnabledLand`, `gunSurchargeEnabledAir`)

Regra: se o valor total de armas ultrapassar o limite, aplica um percentual extra ao frete das armas.

### Separar armas e munições
Switch `separateGunAmmo` (visualmente disponível).  
Observação: esta regra ainda não tem cálculo automático no simulador além do bloqueio de aéreo para munição.

## Simulador
O simulador tem **valores fixos de produto** (somente para teste de regras):
- Munição: R$ 500 por item
- Armas: R$ 5000 por item
- Acessórios: R$ 500 por item

O **frete** é calculado a partir dos valores configurados na variação:
- `landPrice` / `airPrice`
- `billingMode` (`per_item` ou `per_variation`)
- Respeita `allowLand` e `allowAir`

Modalidades no simulador:
- **Terrestre** = `ground`
- **Expresso** = `express` (equivale ao aéreo na UI)

Resumo do simulador:
- **Produtos**: lista e subtotal.
- **Frete**: itens + regras (munição/armas) e subtotal.
- **Total geral**: soma de produtos + frete.

## Regras regionais
Seção **Frete por região**:
- Múltiplas regiões com:
  - Nome
  - Lista de cidades/UF (uma por linha, `UF` ou `Cidade;UF`)
- Frete terrestre/aéreo e `variationPrices` são **ignorados** no cálculo (os preços vêm sempre das variações globais).

## Categoria existente
O picker de categorias:
- Busca no topo.
- Lista com scroll interno e barra visível.
- Usa `/gstore/v1/product-categories`.

## Excluir variação
No topo do editor:
- Botão vermelho “Excluir variação”.
- Modal de confirmação antes de remover.
- Após excluir, seleciona a próxima ou anterior.

## Checklist de manutenção
- Sempre ajustar `class-freight-service.php` e `FreightConfigPage.jsx` em conjunto.
- Rodar `npm run build`.
- Em produção, subir **build/** e **includes/**.
- Manter fallback de dados no front (PHP → JS).

## Troubleshooting rápido
1. **Build quebrando**  
   - Verifique nomes duplicados de variáveis (ex.: `firearmShipping`).
   - Revise dependências do `useMemo`.
2. **Dados não carregam**  
   - Verifique endpoint `/gstore/v1/freight-config`.
3. **Mudanças não aparecem em produção**  
   - Enviar `build/` e `includes/`.
   - Limpar cache da hospedagem.
