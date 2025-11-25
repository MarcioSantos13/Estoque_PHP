<?php
// ver_usuarios.php - Mostra todos os usuários do sistema
$host = "localhost";
$dbname = "sistema_patrimonial";
$username = "root";
$password = "";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = "SELECT id, nome, email, senha, tipo, ativo, data_criacao FROM usuarios";
    $stmt = $conn->query($sql);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>👥 Todos os Usuários do Sistema</h3>";
    
    if(count($usuarios) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Nome</th><th>E-mail</th><th>Tipo</th><th>Ativo</th><th>Senha (hash)</th><th>Data</th></tr>";
        foreach($usuarios as $usuario) {
            echo "<tr>";
            echo "<td>{$usuario['id']}</td>";
            echo "<td>{$usuario['nome']}</td>";
            echo "<td>{$usuario['email']}</td>";
            echo "<td>{$usuario['tipo']}</td>";
            echo "<td>" . ($usuario['ativo'] ? '✅' : '❌') . "</td>";
            echo "<td style='font-size: 10px;'>" . substr($usuario['senha'], 0, 20) . "...</td>";
            echo "<td>{$usuario['data_criacao']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "❌ Nenhum usuário encontrado no sistema!";
    }
    
} catch(PDOException $e) {
    echo "❌ Erro: " . $e->getMessage();
}

echo "<hr>";
echo "<a href='recriar_admin_correto.php' class='btn btn-primary'>Recriar Usuário Admin</a> ";
echo "<a href='verificar_senha.php' class='btn btn-info'>Verificar Senha</a>";
?>