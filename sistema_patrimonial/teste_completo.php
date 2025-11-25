<?php
// teste_completo.php - Teste completo do sistema
session_start();

echo "<h1>Teste Completo do Sistema Patrimonial</h1>";

// Teste 1: Sessão
echo "<h3>1. Teste de Sessão:</h3>";
$_SESSION['teste_sessao'] = 'OK';
echo "Sessão: " . ($_SESSION['teste_sessao'] ?? 'FALHOU') . "<br>";

// Teste 2: Inclusão de arquivos
echo "<h3>2. Teste de Inclusão de Arquivos:</h3>";
try {
    require_once 'includes/database.php';
    echo "✅ database.php incluído com sucesso<br>";
    
    require_once 'includes/config.php';
    echo "✅ config.php incluído com sucesso<br>";
    
} catch (Exception $e) {
    echo "❌ Erro ao incluir arquivos: " . $e->getMessage() . "<br>";
}

// Teste 3: Conexão com banco
echo "<h3>3. Teste de Conexão com Banco:</h3>";
try {
    $database = new Database();
    $conn = $database->getConnection();
    echo "✅ Conexão com banco estabelecida<br>";
    
    // Verificar se a tabela 'bens' existe
    $stmt = $conn->query("SHOW TABLES LIKE 'bens'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Tabela 'bens' encontrada<br>";
    } else {
        echo "❌ Tabela 'bens' NÃO encontrada<br>";
        echo "<p><strong>Problema:</strong> As tabelas do sistema não foram criadas.</p>";
    }
    
} catch (Exception $e) {
    echo "❌ Erro na conexão: " . $e->getMessage() . "<br>";
    echo "<p><strong>Solução:</strong> Crie o banco 'sistema_patrimonial' no phpMyAdmin</p>";
}

// Teste 4: Auth
echo "<h3>4. Teste de Autenticação:</h3>";
try {
    require_once 'includes/auth.php';
    echo "✅ auth.php carregado<br>";
} catch (Exception $e) {
    echo "❌ Erro no auth: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3>Próximos Passos:</h3>";
echo "<p>Se o teste 3 falhar, você precisa criar o banco de dados.</p>";
echo "<p>Se o teste 3 passar mas a tabela 'bens' não existir, você precisa importar a estrutura do banco.</p>";

?>