<?php
/**
 * Script temporário para corrigir sincronização Git no servidor
 * Execute este arquivo uma vez via navegador e depois delete-o
 * 
 * Acesse: https://cacarmas.kivodigital.com.br/wp-content/themes/Gstore-theme/fix-git-sync.php
 */

// Segurança básica - remova ou ajuste conforme necessário
// Descomente a linha abaixo e defina uma senha para proteger o script
// if (!isset($_GET['key']) || $_GET['key'] !== 'SUA_SENHA_AQUI') { die('Acesso negado'); }

$theme_path = __DIR__;
$output = [];
$success = false;

// Verifica se estamos no diretório correto
if (!file_exists($theme_path . '/.git')) {
    die('Erro: Diretório Git não encontrado. Certifique-se de que este arquivo está em /wp-content/themes/Gstore-theme/');
}

echo "<h1>Correção de Sincronização Git</h1>";
echo "<pre>";

// 1. Navegar para o diretório do tema
chdir($theme_path);
echo "Diretório: " . getcwd() . "\n\n";

// 2. Verificar status atual
echo "=== Status atual do Git ===\n";
exec('git status --short 2>&1', $output, $return_var);
echo implode("\n", $output) . "\n\n";
$output = [];

// 3. Fazer backup (opcional)
echo "=== Fazendo backup ===\n";
$backup_dir = dirname($theme_path) . '/Gstore-theme-backup-' . date('Ymd-His');
if (!file_exists($backup_dir)) {
    exec("cp -r '$theme_path' '$backup_dir' 2>&1", $output, $return_var);
    if ($return_var === 0) {
        echo "✅ Backup criado em: $backup_dir\n\n";
    } else {
        echo "⚠️ Aviso ao criar backup: " . implode("\n", $output) . "\n\n";
    }
} else {
    echo "⚠️ Diretório de backup já existe\n\n";
}
$output = [];

// 4. Limpar arquivos não rastreados
echo "=== Limpando arquivos não rastreados ===\n";
exec('git clean -fd 2>&1', $output, $return_var);
echo implode("\n", $output) . "\n\n";
$output = [];

// 5. Resetar para o estado do repositório remoto
echo "=== Resetando para origin/main ===\n";
exec('git fetch origin 2>&1', $output, $return_var);
echo implode("\n", $output) . "\n\n";
$output = [];

exec('git reset --hard origin/main 2>&1', $output, $return_var);
if ($return_var === 0) {
    echo "✅ Reset concluído\n\n";
    $success = true;
} else {
    echo "❌ Erro no reset: " . implode("\n", $output) . "\n\n";
}
$output = [];

// 6. Fazer pull
echo "=== Fazendo pull do repositório ===\n";
exec('git pull origin main 2>&1', $output, $return_var);
echo implode("\n", $output) . "\n\n";
if ($return_var === 0) {
    echo "✅ Pull concluído\n\n";
} else {
    echo "⚠️ Aviso no pull: " . implode("\n", $output) . "\n\n";
}
$output = [];

// 7. Verificar se inc/installments.php existe
echo "=== Verificando arquivo crítico ===\n";
if (file_exists($theme_path . '/inc/installments.php')) {
    echo "✅ Arquivo inc/installments.php encontrado!\n";
    echo "   Tamanho: " . filesize($theme_path . '/inc/installments.php') . " bytes\n\n";
} else {
    echo "❌ ERRO CRÍTICO: Arquivo inc/installments.php NÃO encontrado!\n";
    echo "   O site não funcionará sem este arquivo.\n\n";
}

// 8. Status final
echo "=== Status final do Git ===\n";
exec('git status --short 2>&1', $output, $return_var);
echo implode("\n", $output) . "\n\n";

echo "</pre>";

if ($success) {
    echo "<h2 style='color: green;'>✅ Sincronização concluída com sucesso!</h2>";
    echo "<p><strong>IMPORTANTE:</strong> Delete este arquivo (fix-git-sync.php) por segurança após usar.</p>";
} else {
    echo "<h2 style='color: orange;'>⚠️ Sincronização concluída com avisos</h2>";
    echo "<p>Verifique os logs acima para mais detalhes.</p>";
}
?>
