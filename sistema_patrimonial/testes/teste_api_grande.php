<?php
// teste_api_grande.php - Teste direto da API com arquivo grande
echo "<h3>🧪 Teste da API com Arquivo de 17MB</h3>";

// Verificar se o arquivo existe
$arquivo_17mb = 'patrimonio.xlsx'; // Substitua pelo nome real do seu arquivo
if (!file_exists($arquivo_17mb)) {
    echo "<div class='alert alert-warning'>";
    echo "📁 Arquivo não encontrado: <code>$arquivo_17mb</code><br>";
    echo "💡 Coloque seu arquivo de 17MB na pasta do sistema";
    echo "</div>";
    
    // Listar arquivos disponíveis
    echo "<h5>📂 Arquivos na pasta:</h5>";
    $arquivos = glob("*.xlsx");
    if (count($arquivos) > 0) {
        foreach ($arquivos as $arquivo) {
            $tamanho = filesize($arquivo);
            echo "- $arquivo (" . formatarBytes($tamanho) . ")<br>";
        }
    } else {
        echo "Nenhum arquivo .xlsx encontrado";
    }
    exit;
}

$tamanho = filesize($arquivo_17mb);
echo "<div class='alert alert-info'>";
echo "📁 Arquivo: <strong>$arquivo_17mb</strong><br>";
echo "📏 Tamanho: " . formatarBytes($tamanho) . "<br>";
echo "✅ Dentro dos limites do PHP";
echo "</div>";

// Testar a API
echo "<h4>🚀 Testando API...</h4>";

// Simular upload via cURL
$url = 'http://localhost/sistema_patrimonial/api/importar.php';
$post_data = [
    'nome_aba' => 'Plan1'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: multipart/form-data'
]);

// Adicionar arquivo
$file_data = [
    'arquivo_excel' => new CURLFile($arquivo_17mb, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'patrimonio.xlsx')
];
curl_setopt($ch, CURLOPT_POSTFIELDS, array_merge($post_data, $file_data));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<h5>📊 Resposta da API:</h5>";
echo "<p>Status HTTP: <strong>$http_code</strong></p>";

if ($error) {
    echo "<div class='alert alert-danger'>";
    echo "❌ Erro cURL: $error";
    echo "</div>";
} else {
    echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>" . htmlspecialchars($response) . "</pre>";
    
    // Verificar se é JSON válido
    $json_data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<div class='alert alert-success'>✅ JSON VÁLIDO</div>";
        echo "<pre>" . print_r($json_data, true) . "</pre>";
    } else {
        echo "<div class='alert alert-danger'>❌ JSON INVÁLIDO: " . json_last_error_msg() . "</div>";
    }
}

// Função auxiliar
function formatarBytes($bytes, $decimals = 2) {
    $size = ['B', 'KB', 'MB', 'GB', 'TB'];
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . $size[$factor];
}
?>