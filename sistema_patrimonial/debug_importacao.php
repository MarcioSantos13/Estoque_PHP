<?php
// debug_importacao.php - Diagnóstico completo
echo "<h3>🐛 Debug da Importação</h3>";

// Testar includes
echo "<h4>1. Includes</h4>";
$includes = [
    'includes/init.php' => file_exists('includes/init.php'),
    'vendor/autoload.php' => file_exists('vendor/autoload.php')
];

foreach ($includes as $arquivo => $existe) {
    echo $existe ? "✅ " : "❌ ";
    echo "$arquivo<br>";
}

// Testar sessão
echo "<h4>2. Sessão</h4>";
session_start();
echo "Sessão ID: " . session_id() . "<br>";
echo "Logado: " . (isset($_SESSION['usuario_id']) ? '✅ SIM' : '❌ NÃO') . "<br>";

if (isset($_SESSION['usuario_id'])) {
    echo "Usuário: {$_SESSION['usuario_nome']} ({$_SESSION['usuario_email']})<br>";
    echo "Tipo: {$_SESSION['usuario_tipo']}<br>";
}

// Testar banco
echo "<h4>3. Banco de Dados</h4>";
try {
    require_once 'includes/database.php';
    $database = new Database();
    $db = $database->getConnection();
    echo "✅ Conexão com banco: OK<br>";
    
    // Verificar tabela bens
    $result = $db->query("SHOW TABLES LIKE 'bens'");
    echo "Tabela 'bens': " . ($result->rowCount() > 0 ? '✅ EXISTE' : '❌ FALTANDO') . "<br>";
    
} catch (Exception $e) {
    echo "❌ Banco: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<a href='importar.php' class='btn btn-success'>Testar Importação Agora</a>";
?>