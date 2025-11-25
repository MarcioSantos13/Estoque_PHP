<?php
// teste_sistema.php
require_once 'includes/config.php';

echo "<h3>🔍 Teste do Sistema</h3>";

// Testar sessão
echo "Sessão ID: " . session_id() . "<br>";
echo "Status da sessão: " . session_status() . "<br>";

// Testar banco
try {
    $database = new Database();
    $db = $database->getConnection();
    echo "✅ Banco de dados: CONECTADO<br>";
} catch(Exception $e) {
    echo "❌ Banco de dados: ERRO - " . $e->getMessage() . "<br>";
}

// Testar autenticação
if(Auth::isLogged()) {
    echo "✅ Autenticação: USUÁRIO LOGADO<br>";
    echo "👤 Nome: " . $_SESSION['usuario_nome'] . "<br>";
    echo "📧 E-mail: " . $_SESSION['usuario_email'] . "<br>";
} else {
    echo "🔐 Autenticação: USUÁRIO NÃO LOGADO<br>";
}

echo "<hr>";
echo "<a href='index.php' class='btn btn-primary'>Ir para Sistema</a> ";
echo "<a href='includes/auth.php?logout=true' class='btn btn-danger'>Testar Logout</a>";
?>