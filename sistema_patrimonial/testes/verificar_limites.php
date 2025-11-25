<?php
// verificar_limites.php - Verificar configurações de upload (VERSÃO CORRIGIDA)
echo "<h3>📏 Verificação de Limites do PHP</h3>";

$configuracoes = [
    'upload_max_filesize' => 'Tamanho máximo de upload',
    'post_max_size' => 'Tamanho máximo do POST', 
    'memory_limit' => 'Limite de memória',
    'max_execution_time' => 'Tempo máximo de execução',
    'max_input_time' => 'Tempo máximo de input',
    'max_file_uploads' => 'Máximo de arquivos por upload'
];

echo "<table class='table table-bordered table-sm'>";
echo "<tr><th>Configuração</th><th>Valor Atual</th><th>Recomendado</th><th>Status</th></tr>";

foreach ($configuracoes as $config => $descricao) {
    $valor_atual = ini_get($config);
    $status = verificarConfiguracao($config, $valor_atual);
    
    echo "<tr>";
    echo "<td><strong>$descricao</strong><br><small>$config</small></td>";
    echo "<td><code>$valor_atual</code></td>";
    echo "<td>" . getRecomendacao($config) . "</td>";
    echo "<td>$status</td>";
    echo "</tr>";
}

echo "</table>";

// Verificar se o arquivo de 17MB seria aceito
$tamanho_arquivo = 17 * 1024 * 1024; // 17MB em bytes
$upload_max = converterParaBytes(ini_get('upload_max_filesize'));
$post_max = converterParaBytes(ini_get('post_max_size'));

echo "<h4>📁 Arquivo de 17MB:</h4>";
if ($tamanho_arquivo <= $upload_max && $tamanho_arquivo <= $post_max) {
    echo "<div class='alert alert-success'>✅ <strong>ACEITO</strong> - O arquivo de 17MB pode ser enviado</div>";
} else {
    echo "<div class='alert alert-danger'>❌ <strong>RECUSADO</strong> - O arquivo de 17MB é muito grande para as configurações atuais</div>";
    
    echo "<h5>💡 Solução:</h5>";
    echo "<p>Edite o arquivo <code>C:\\xampp\\php\\php.ini</code> e altere:</p>";
    echo "<pre>";
    echo "upload_max_filesize = 50M\n";
    echo "post_max_size = 55M\n"; 
    echo "memory_limit = 512M\n";
    echo "max_execution_time = 600\n";
    echo "max_input_time = 300\n";
    echo "</pre>";
    echo "<p><strong>Reinicie o Apache</strong> após fazer as alterações.</p>";
}

// Mostrar conversão para entender melhor
echo "<h5>🧮 Conversão para bytes:</h5>";
echo "<ul>";
echo "<li>17MB = " . number_format($tamanho_arquivo) . " bytes</li>";
echo "<li>upload_max_filesize = " . number_format($upload_max) . " bytes</li>";
echo "<li>post_max_size = " . number_format($post_max) . " bytes</li>";
echo "</ul>";

// ===== FUNÇÕES AUXILIARES =====

function verificarConfiguracao($config, $valor) {
    $valor_bytes = converterParaBytes($valor);
    
    switch($config) {
        case 'upload_max_filesize':
            return $valor_bytes >= (20 * 1024 * 1024) ? '✅ OK' : '❌ Muito baixo';
        case 'post_max_size':
            return $valor_bytes >= (25 * 1024 * 1024) ? '✅ OK' : '❌ Muito baixo';
        case 'memory_limit':
            return $valor_bytes >= (256 * 1024 * 1024) ? '✅ OK' : '⚠️ Pode ser baixo';
        case 'max_execution_time':
            return $valor >= 300 ? '✅ OK' : '⚠️ Pode ser baixo';
        case 'max_input_time':
            return $valor >= 300 ? '✅ OK' : '⚠️ Pode ser baixo';
        default:
            return 'ℹ️ Verificar';
    }
}

function getRecomendacao($config) {
    $recomendacoes = [
        'upload_max_filesize' => '20M a 50M',
        'post_max_size' => '25M a 55M', 
        'memory_limit' => '256M a 512M',
        'max_execution_time' => '300 a 600',
        'max_input_time' => '300 a 600',
        'max_file_uploads' => '20 a 50'
    ];
    return $recomendacoes[$config] ?? 'N/A';
}

function converterParaBytes($valor) {
    if (empty($valor)) return 0;
    
    $valor = trim($valor);
    $unidade = strtoupper(substr($valor, -1));
    $numero = (float) substr($valor, 0, -1);
    
    $unidades = [
        'K' => 1024,
        'M' => 1024 * 1024,
        'G' => 1024 * 1024 * 1024
    ];
    
    if (isset($unidades[$unidade])) {
        return $numero * $unidades[$unidade];
    }
    
    return (int) $valor;
}
?>

<style>
.table th {
    background-color: #f8f9fa;
}
.alert {
    padding: 10px;
    border-radius: 5px;
}
</style>