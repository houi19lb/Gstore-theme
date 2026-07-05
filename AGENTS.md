# AGENTS.md

Instrucoes para agentes trabalhando no GStore Theme.

## Antes de mexer

1. Leia `docs/agent-ops.md`.
2. Se existir `package.json`, leia os scripts e dependencias declarados nele.
3. Se existir `ORBIT.md`, trate como contexto operacional local desta maquina.
4. Se a tarefa envolver frete, deploy, seguranca ou QA visual, leia os documentos relevantes em `docs/`.

## Checagem obrigatoria de alinhamento

Antes de propor, planejar ou implementar qualquer mudanca, confirme o checkout
real em uso e o alvo correto da tarefa.

1. Rode `git status -sb`.
2. Rode `git branch --show-current` e confira se a worktree corresponde ao nome
   esperado da branch.
3. Quando houver rede/remoto disponivel, rode `git fetch --all --prune` antes de
   comparar com upstream.
4. Compare ahead/behind com `git rev-list --left-right --count HEAD...@{u}`
   quando a branch tiver upstream.
5. Se a worktree estiver com nome de estabilidade incorreto, branch divergida,
   detached HEAD, ou sem upstream claro, avise antes de alterar arquivos.

Regra de direcionamento:

- Mudanca nova, experimento, refatoracao ou fluxo ainda incerto deve nascer em
  `alpha` ou em branch temporaria criada a partir de `alpha`.
- Se a conversa comecar em `beta`, `stable`, `main`, `preprod` ou
  `live-stores`, e a mudanca deveria nascer primeiro em `alpha`, pare e diga
  isso antes de editar.
- Nao trate `preprod`/`live-stores` como substituto de `alpha` ou `beta`; eles
  representam a familia operacional das lojas online e exigem validacao separada.

## Integracao com Kivo Orbit

Este repositorio participa da observabilidade operacional da Kivo via Orbit.

`ORBIT.md` pode existir localmente para organizar contexto, decisoes e proximos passos, mas nao e fonte obrigatoria no remoto. Ao concluir trabalho relevante, atualize `ORBIT.md` apenas quando ele existir e quando a informacao for publica-segura.

- Nao tente atualizar o Orbit em producao diretamente a partir deste repo.
- Nao registre secrets, tokens, cookies, credenciais, dados privados ou conteudo sensivel.
- A consolidacao no Orbit deve acontecer pelo repositorio interno `kivo-orbit`.

## Git e worktrees

- Antes de pull, merge, rebase, cherry-pick ou limpeza, rode `git status -sb` e entenda se a worktree esta limpa, suja ou detached.
- Nao descarte worktrees, commits locais unicos, hotfixes de producao ou arquivos ligados a seguranca sem registrar onde foram preservados.
- Prefira criar uma worktree isolada para reconciliar `main`, `preprod`/`live-stores` e correcoes de seguranca.

## Fluxo de branches e promocao

Antes de propor, planejar ou implementar qualquer mudanca, o agente deve
decidir e registrar mentalmente:

1. Em qual branch essa mudanca deve nascer?
2. O nivel de estabilidade da mudanca e compativel com essa branch?
3. Existe risco de impactar clientes estaveis?
4. A alteracao deve ficar isolada em uma branch temporaria antes da promocao?

Branches permanentes:

- `main`: versao oficial do sistema. Deve permanecer funcional e receber apenas
  mudancas consolidadas e aprovadas.
- `stable`: versao para clientes que exigem alta confiabilidade. Priorize
  estabilidade e evite mudancas frequentes.
- `beta`: ambiente de validacao em clientes com maior tolerancia a mudancas.
  Recebe funcionalidades ja utilizaveis e correcoes rapidas.
- `alpha`: laboratorio de desenvolvimento para prototipos, provas de conceito,
  refatoracoes e funcionalidades incompletas. Nao ha expectativa de estabilidade.

Regra de origem:

- Funcionalidade nova, experimento, refatoracao ou fluxo ainda incerto deve
  nascer em branch temporaria propria, como `feature/*`, `fix/*`, `hotfix/*` ou
  `experiment/*`.
- Nunca proponha nem implemente funcionalidade ainda em desenvolvimento
  diretamente em `main` ou `stable`.
- Correcoes criticas podem seguir um fluxo reduzido quando apropriado, mas o
  motivo, o risco e a validacao precisam ficar claros.
- Sempre priorize mudancas pequenas, revisaveis e promovidas gradualmente.

Fluxo preferencial de promocao:

```text
feature/*
  -> alpha
  -> beta
  -> stable
  -> main
```

Nem toda mudanca precisa passar por todas as etapas, mas qualquer atalho deve ser
intencional e compativel com o risco.

## Worktrees por estabilidade

As worktrees existem apenas no ambiente local. Cada worktree deve estar
associada a uma unica branch e nao deve ser assumida como compartilhada com
outros desenvolvedores.

Nomes esperados:

```text
gstore-alpha/
gstore-beta/
gstore-stable/
gstore-main/
gstore-feature-nome-da-mudanca/
```

Use worktree isolada para funcionalidades, reconciliacoes de branches,
promocoes entre niveis e hotfixes de producao. Branches operacionais legadas ou
de ambiente, como `preprod`/`live-stores`, so devem ser tocadas quando a tarefa
ou o contexto atual exigir; ainda assim, preserve a origem isolada da mudanca
sempre que ela ainda nao estiver validada.

## Seguranca

- Nao versionar credenciais, cookies, tokens ou dados privados de loja.
- Registre evidencias visuais apenas quando forem publicas-seguras.
- Snapshots, screenshots e artefatos gerados devem ficar locais por padrao; versione a metodologia e os scripts, nao os dados sensiveis.
