<?php
// teste_arquivo_grande.php - Testar upload de arquivo grande
echo "<h3>📁 Teste de Arquivo Grande (17MB)</h3>";

// Verificar se o arquivo existe
$arquivo_teste = 'teste_17mb.xlsx'; // Substitua pelo seu arquivo real
if (!file_exists($arquivo_teste)) {
    echo "❌ Arquivo de teste não encontrado<br>";
    echo "💡 Coloque seu arquivo de 17MB na pasta do sistema como 'teste_17mb.xlsx'";
    exit;
}

$tamanho = filesize($arquivo_teste);
echo "📏 Tamanho do arquivo: " . $this->formatarBytes($tamanho) . "<br>";

// Verificar limites
$upload_max = ini_get('upload_max_filesize');
$post_max = ini_get('post_max_size');

echo "📋 Limite de upload: $upload_max<br>";
echo "📋 Limite do POST: $post_max<br>";

if ($tamanho <= $this->converterParaBytes($upload_max) && $tamanho <= $this->converterParaBytes($post_max)) {
    echo "✅ <strong>O arquivo pode ser enviado!</strong><br>";
    echo "<a href='importar.php' class='btn btn-success mt-3'>Tentar Importação</a>";
} else {
    echo "❌ <strong>Arquivo muito grande para os limites atuais</strong><br>";
    echo "💡 Ajuste o php.ini conforme instruções acima";
}

// Funções auxiliares (as mesmas do exemplo anterior)
function formatarBytes($bytes, $decimals = 2) {
    $size = ['B', 'KB', 'MB', 'GB', 'TB'];
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . $size[$factor];
}

function converterParaBytes($valor) {
    $unidades = ['K' => 1024, 'M' => 1024 * 1024, 'G' => 1024 * 1024 * 1024];
    $valor = trim($valor);
    $unidade = strtoupper(substr($valor, -1));
    $numero = (float) substr($valor, 0, -1);
    return isset($unidades[$unidade]) ? $numero * $unidades[$unidade] : (int) $valor;
}
?>