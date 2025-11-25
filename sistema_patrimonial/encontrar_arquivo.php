<?php
// encontrar_arquivo.php - Procurar arquivo Excel na pasta uploads
echo "<h3>🔍 Procurando Arquivo Excel na Pasta Uploads</h3>";

// Procurar especificamente na pasta uploads
$pasta_uploads = __DIR__ . '\\uploads\\';
echo "<p><strong>📍 Procurando em:</strong> <code>$pasta_uploads</code></p>";

// Verificar se a pasta uploads existe
if (!is_dir($pasta_uploads)) {
    echo "<div class='alert alert-warning'>";
    echo "❌ A pasta <code>uploads</code> não existe.";
    echo "</div>";
    
    // Tentar criar a pasta
    if (mkdir($pasta_uploads, 0777, true)) {
        echo "<div class='alert alert-success'>";
        echo "✅ Pasta <code>uploads</code> criada com sucesso!";
        echo "</div>";
    }
} else {
    echo "<div class='alert alert-success'>";
    echo "✅ Pasta <code>uploads</code> encontrada!";
    echo "</div>";
}

// Procurar arquivos Excel na pasta uploads
$arquivos_encontrados = [];
$padroes = ['*.xlsx', '*.xls'];

foreach ($padroes as $padrao) {
    $caminho_completo = $pasta_uploads . $padrao;
    $resultados = glob($caminho_completo);
    
    if (is_array($resultados)) {
        foreach ($resultados as $arquivo) {
            $tamanho = filesize($arquivo);
            $tamanho_mb = round($tamanho / 1024 / 1024, 2);
            $nome_arquivo = basename($arquivo);
            
            echo "<div class='alert alert-info'>";
            echo "✅ <strong>Arquivo encontrado:</strong> $nome_arquivo<br>";
            echo "📏 Tamanho: $tamanho_mb MB<br>";
            echo "📍 Local: $arquivo<br>";
            
            // Botões de ação
            echo "<div class='mt-2'>";
            echo "<a href='usar_arquivo.php?arquivo=" . urlencode($nome_arquivo) . "' class='btn btn-success btn-sm'>";
            echo "<i class='bi bi-play-circle me-1'></i>Usar para Importação";
            echo "</a>";
            
            echo "<a href='mover_arquivo.php?arquivo=" . urlencode($nome_arquivo) . "' class='btn btn-outline-primary btn-sm ms-1'>";
            echo "<i class='bi bi-arrow-right me-1'></i>Mover para Pasta Principal";
            echo "</a>";
            echo "</div>";
            
            echo "</div>";
            
            $arquivos_encontrados[] = $arquivo;
        }
    }
}

// Se não encontrou arquivos
if (count($arquivos_encontrados) === 0) {
    echo "<div class='alert alert-warning'>";
    echo "❌ Nenhum arquivo Excel encontrado na pasta <code>uploads</code>.";
    echo "</div>";
    
    echo "<h5>💡 O que fazer:</h5>";
    echo "<ol>";
    echo "<li>Coloque seu arquivo Excel na pasta: <code>C:\\xampp\\htdocs\\sistema_patrimonial\\uploads\\</code></li>";
    echo "<li>Atualize esta página</li>";
    echo "<li>Ou use o formulário de upload abaixo</li>";
    echo "</ol>";
}

echo "<hr>";
echo "<a href='importar.php' class='btn btn-primary'>Voltar para Importação</a>";
?>