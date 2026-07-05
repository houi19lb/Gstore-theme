# GitHub Actions deploy - GStore Theme

Este repo publica o tema `Gstore-theme` em lojas WordPress via GitHub Actions, sem guardar token GitHub dentro do `wp-config.php` do cliente.

## Workflows

- `.github/workflows/deploy-wordpress-theme.yml`

O workflow e manual (`workflow_dispatch`) e aceita:

- `target`: `big_boar`, `arma_store`, `cac_armas` ou `all`.
- `dry_run`: sobe e valida o pacote sem substituir arquivos.
- `activate_after_deploy`: ativa o tema via WP-CLI quando disponivel.

## Environments no GitHub

Crie estes environments no repo:

- `big_boar`
- `arma_store`
- `cac_armas`

Em cada environment, cadastre os mesmos secrets:

- `SSH_HOST`: host/IP da hospedagem.
- `SSH_PORT`: porta SSH, normalmente `22`.
- `SSH_USER`: usuario SSH/SFTP do site.
- `SSH_PRIVATE_KEY`: chave privada SSH exclusiva desse deploy.
- `SSH_KNOWN_HOSTS`: linha de `known_hosts` do host. Opcional, mas recomendado.
- `WP_ROOT`: raiz WordPress do site, exemplo `/home/kivo/htdocs/bigboar.com.br`.

## Modelo operacional recomendado

1. Gere uma chave SSH exclusiva para cada loja/deploy.
2. Adicione a chave publica em `~/.ssh/authorized_keys` do usuario da hospedagem.
3. Salve a chave privada somente no GitHub Environment correspondente.
4. Rode o workflow primeiro com `dry_run=true`.
5. Rode de novo com `dry_run=false` quando a validacao passar.

## O que o workflow faz

1. Empacota o tema sem `.git`, `.github`, `node_modules`, snapshots visuais e arquivos temporarios.
2. Envia o pacote `.tgz` por SSH/SCP.
3. Extrai em pasta temporaria no servidor.
4. Valida `functions.php` com `php -l`.
5. Cria backup da pasta antiga em `.gstore-backups`.
6. Substitui `wp-content/themes/Gstore-theme`.
7. Ativa o tema via WP-CLI, quando configurado.
8. Limpa cache via WP-CLI, quando disponivel.

## Observacoes

- O site cliente nao precisa de `GSTORE_GITHUB_TOKEN`.
- Se uma chave vazar, revogue apenas o environment daquela loja.
- O usuario SSH deve ter permissao somente no site que sera atualizado.
- Para deploy em todas as lojas, use `target=all`.
