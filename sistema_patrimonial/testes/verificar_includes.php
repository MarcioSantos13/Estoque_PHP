<?php
// verificar_includes.php - Verifica arquivos da pasta includes
echo "<h3>🔍 Verificando Arquivos da Pasta Includes</h3>";

$arquivos_includes = [
    'includes/init.php',
    'includes/config.php', 
    'includes/database.php',
    'includes/auth.php'
];

echo "<table class='table table-bordered'>";
echo "<tr><th>Arquivo</th><th>Status</th><th>Ação</th></tr>";

foreach ($arquivos_includes as $arquivo) {
    $existe = file_exists($arquivo);
    $status = $existe ? "✅ EXISTE" : "❌ FALTANDO";
    
    echo "<tr>";
    echo "<td><code>$arquivo</code></td>";
    echo "<td>$status</td>";
    echo "<td>";
    if (!$existe) {
        echo "<button onclick=\"criarArquivo('$arquivo')\" class='btn btn-sm btn-success'>Criar</button>";
    }
    echo "</td>";
    echo "</tr>";
}

echo "</table>";

// Script para criar arquivos
echo "
<script>
function criarArquivo(arquivo) {
    if(confirm('Deseja criar o arquivo ' + arquivo + '?')) {
        window.location.href = 'criar_arquivo.php?arquivo=' + encodeURIComponent(arquivo);
    }
}
</script>
";

// Verificar estrutura de pastas
echo "<h4>📁 Estrutura de Pastas:</h4>";
$pastas = ['includes', 'api', 'uploads', 'uploads/imagens', 'vendor'];
foreach ($pastas as $pasta) {
    if (!is_dir($pasta)) {
        echo "❌ Pasta <code>$pasta</code> não existe<br>";
        if (mkdir($pasta, 0777, true)) {
            echo "✅ Pasta <code>$pasta</code> criada<br>";
        }
    } else {
        echo "✅ Pasta <code>$pasta</code> existe<br>";
    }
}
?>