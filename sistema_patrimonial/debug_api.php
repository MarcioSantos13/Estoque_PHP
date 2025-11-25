<?php
// debug_api.php - Teste direto da API
echo "<h3>🐛 Debug da API de Importação</h3>";

// Simular um upload de arquivo de teste
$_FILES = [
    'arquivo_excel' => [
        'name' => 'teste.xlsx',
        'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'tmp_name' => __DIR__ . '/teste_pequeno.xlsx', // Vamos criar este arquivo
        'error' => 0,
        'size' => 1024
    ]
];

$_POST = ['nome_aba' => 'Plan1'];

// Capturar a saída da API
ob_start();
include 'api/importar.php';
$output = ob_get_clean();

echo "<h4>📤 Saída da API:</h4>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";

echo "<h4>🔍 Análise:</h4>";
if (empty($output)) {
    echo "❌ A API não retornou nenhuma saída<br>";
} elseif (strpos($output, '{') === 0) {
    echo "✅ A API retornou JSON válido<br>";
    
    $json = json_decode($output, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "✅ JSON parseado com sucesso<br>";
        echo "<pre>" . print_r($json, true) . "</pre>";
    } else {
        echo "❌ Erro no JSON: " . json_last_error_msg() . "<br>";
    }
} else {
    echo "❌ A API não retornou JSON (começa com: " . substr($output, 0, 50) . "...)<br>";
}
?>