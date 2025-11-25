<?php
// teste_api_simples.php - Teste direto da API simples
echo "<h3>🧪 Teste da API Simplificada</h3>";

// Simular um ambiente de teste
$_FILES = [
    'arquivo_excel' => [
        'name' => 'teste.xlsx',
        'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'tmp_name' => __DIR__ . '/teste_pequeno.xlsx',
        'error' => 0,
        'size' => 1024
    ]
];

$_POST = ['nome_aba' => 'Plan1'];

// Executar a API
ob_start();
include 'api/importar.php';
$output = ob_get_clean();

echo "<h4>📤 Saída da API:</h4>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";

// Verificar se é JSON válido
$json = json_decode($output, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "<div class='alert alert-success'>✅ JSON VÁLIDO!</div>";
    echo "<pre>" . print_r($json, true) . "</pre>";
} else {
    echo "<div class='alert alert-danger'>❌ JSON INVÁLIDO: " . json_last_error_msg() . "</div>";
}
?>