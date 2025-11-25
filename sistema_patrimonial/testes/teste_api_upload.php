<?php
// teste_api_uploads.php - Teste direto da API de uploads
echo "<h3>🧪 Teste Direto da API importar_uploads.php</h3>";

// Simular os dados que seriam enviados
$_POST = [
    'arquivo_uploads' => 'patrimonio.xlsx',
    'nome_aba' => 'Plan1'
];

// Executar a API e capturar a saída
ob_start();
include 'api/importar_uploads.php';
$output = ob_get_clean();

echo "<h4>📤 Saída da API:</h4>";
echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #ddd;'>" . htmlspecialchars($output) . "</pre>";

// Verificar se é JSON válido
echo "<h4>🔍 Análise do JSON:</h4>";

if (empty($output)) {
    echo "<div class='alert alert-danger'>❌ A API não retornou nenhuma saída</div>";
} else {
    // Verificar se começa com {
    if (strpos(trim($output), '{') === 0) {
        echo "<div class='alert alert-success'>✅ A API retornou JSON (começa com {)</div>";
        
        // Tentar parsear
        $json_data = json_decode($output, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "<div class='alert alert-success'>✅ JSON VÁLIDO - Parseado com sucesso!</div>";
            echo "<pre>" . print_r($json_data, true) . "</pre>";
        } else {
            echo "<div class='alert alert-danger'>❌ JSON INVÁLIDO: " . json_last_error_msg() . "</div>";
            echo "<p>Possível conteúdo inválido antes do JSON.</p>";
        }
    } else {
        echo "<div class='alert alert-danger'>❌ A API NÃO retornou JSON (não começa com {)</div>";
        echo "<p>Primeiros 200 caracteres: <code>" . htmlspecialchars(substr($output, 0, 200)) . "</code></p>";
    }
}

// Verificar logs de erro
echo "<h4>📋 Logs de Erro do PHP:</h4>";
$error_log = ini_get('error_log');
if (file_exists($error_log)) {
    $logs = shell_exec('tail -n 10 "' . $error_log . '" 2>&1');
    echo "<pre style='background: #fff3cd; padding: 10px; border-radius: 5px;'>" . htmlspecialchars($logs) . "</pre>";
} else {
    echo "<p>Arquivo de log não encontrado: $error_log</p>";
}
?>