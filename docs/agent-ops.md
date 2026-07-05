# Operacao local para agentes

## Objetivo

Este documento separa o que deve virar contrato compartilhado do repositorio, o
que e contexto operacional local da maquina e o que e artefato temporario. O
repo e mantido por pessoas usando agentes, entao a regra principal e: preserve
contexto util, mas nao leve ruido local ou dados sensiveis para o remoto.

## Fontes de contexto

- `AGENTS.md`: entrada curta e versionavel para qualquer agente.
- `docs/agent-ops.md`: regras compartilhadas de operacao local, worktrees,
  snapshots e descarte.
- `package.json`: quando existir, comandos locais versionaveis, principalmente
  QA visual.
- `ORBIT.md`: contexto operacional local quando existir. Pode registrar decisoes,
  proximos passos, riscos e areas afetadas, mas nao precisa ir para o remoto.
- `docs/`: metodologia, runbooks e evidencias publicas-seguras.

Nunca copie secrets, tokens, cookies, chaves, dados privados de loja, pedidos,
CPF, telefone, enderecos ou conteudo de sessao real para arquivos versionaveis.

## Git, worktrees e nomes

Um checkout "sujo" nao e lixo. Ele apenas tem mudancas locais ainda nao
organizadas em commit. Antes de limpar, mover ou descartar qualquer coisa:

1. Rode `git status -sb`.
2. Veja se ha commits locais unicos com `git branch -vv` ou `git log`.
3. Preserve hotfixes, seguranca e producao em branch ou patch revisavel.
4. So remova uma worktree depois de confirmar que ela esta limpa ou que o seu
   conteudo foi preservado em outro lugar.

Use worktrees isoladas para reconciliacoes grandes. O checkout principal pode
ficar como area de trabalho local; uma worktree limpa deve ser usada para
integracao de `main`, `preprod`/`live-stores` e patches de seguranca.

## Alinhamento inicial obrigatorio

Todo agente deve tratar nome de pasta, nome de branch e upstream como coisas
separadas. Antes de qualquer alteracao:

1. Identifique a pasta atual com `git rev-parse --show-toplevel`.
2. Identifique a branch atual com `git branch --show-current`.
3. Confira estado e upstream com `git status -sb`.
4. Atualize refs com `git fetch --all --prune` quando houver rede/remoto
   disponivel.
5. Compare com upstream usando `git rev-list --left-right --count HEAD...@{u}`
   quando houver upstream.

Se a tarefa pedir uma mudanca nova, experimental, incerta ou de refatoracao, o
alvo inicial e `alpha` ou uma branch temporaria criada a partir de `alpha`.
Quando a conversa estiver em `beta`, `stable`, `main`, `preprod` ou
`live-stores`, o agente deve avisar que a mudanca precisa nascer primeiro em
`alpha` antes de editar. So continue em outra branch quando o usuario confirmar
que o atalho e intencional.

Se uma worktree tiver nome de estabilidade mas estiver em outra branch, como
`Gstore-theme-alpha` em uma branch temporaria, trate como desalinhamento
operacional: preserve commits locais, reporte o estado e so corrija por
fast-forward, switch ou nova worktree quando isso nao descartar trabalho.

## Politica do `ORBIT.md`

`ORBIT.md` e um caderno operacional local. Ele ajuda agentes a manter memoria de
decisoes, riscos e proximos passos nesta maquina, mas nao e requisito para o
remoto.

Quando existir e a tarefa for relevante, ele pode ser atualizado com:

- data;
- resumo;
- areas afetadas;
- decisao tomada;
- proximo passo;
- bloqueios/riscos.

Mantenha o conteudo publico-seguro. Se uma informacao so faz sentido para a
maquina local ou contem contexto sensivel, deixe fora do remoto.

## O que versionar

Versione arquivos que ajudam outra pessoa ou agente a reproduzir metodo sem
carregar dados sensiveis:

- `AGENTS.md`;
- `docs/agent-ops.md`;
- `package.json` e scripts referenciados por ele, quando fizerem parte do bloco
  de trabalho que sera versionado;
- scripts de QA visual em `scripts/`;
- docs de metodo, como `docs/EVIDENCIAS-VISUAIS.md`,
  `docs/AUDITORIA-VISUAL-ALINHAMENTO.md` e `docs/visual-snapshots/README.md`;
- manifests e exemplos sem dados privados, como
  `docs/visual-snapshots.manifest.json`,
  `docs/visual-snapshots.reference-routes.json` e
  `docs/visual-snapshots.routes.example.json`.

Workflow de deploy e docs de deploy podem ser versionados somente depois de
revisar permissao, modelo de secrets e risco operacional.

## O que manter local ou ignorado

Mantenha fora do remoto por padrao:

- `ORBIT.md`;
- `.playwright-mcp/`;
- screenshots soltos;
- `docs/visual-snapshots/latest/`;
- `docs/visual-snapshots/archive/`;
- `docs/visual-snapshots/manual/`;
- qualquer snapshot com dados reais, mesmo redigido, salvo decisao explicita.

Se uma evidencia visual for importante para compartilhar, prefira documentar o
caminho, o metodo e o achado. Versione o PNG apenas depois de revisar privacidade
e peso do repositorio.

## Como decidir se algo e util

Trate como util e preserve quando o item:

- documenta uma decisao operacional;
- reproduz um comando ou teste necessario;
- valida layout, checkout, frete, seguranca ou deploy;
- contem hotfix aplicado em loja real;
- representa um commit local unico;
- evita que outro agente repita uma investigacao cara.

Trate como descartavel apenas quando o item:

- e cache, log ou screenshot bruto;
- pode ser recriado por script versionado;
- duplica uma worktree limpa ou branch remota;
- nao tem relacao com seguranca, producao, deploy, QA ou contexto de decisao.

Se houver duvida, nao delete. Registre a duvida, preserve em branch/patch ou
peca revisao humana.
