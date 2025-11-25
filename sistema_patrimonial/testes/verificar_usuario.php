<?php
// verificar_usuario.php
$host = "localhost";
$dbname = "sistema_patrimonial";
$username = "root";
$password = "";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = "SELECT id, nome, email, tipo FROM usuarios";
    $stmt = $conn->query($sql);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>👥 Usuários Cadastrados no Sistema</h3>";
    
    if(count($usuarios) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Nome</th><th>E-mail</th><th>Tipo</th></tr>";
        foreach($usuarios as $usuario) {
            echo "<tr>";
            echo "<td>{$usuario['id']}</td>";
            echo "<td>{$usuario['nome']}</td>";
            echo "<td>{$usuario['email']}</td>";
            echo "<td>{$usuario['tipo']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<br><strong>✅ O usuário admin JÁ EXISTE no sistema!</strong>";
        echo "<br>📧 E-mail: admin@cead.com";
        echo "<br>🔑 Tente fazer login com a senha que foi definida anteriormente";
    } else {
        echo "❌ Nenhum usuário encontrado no sistema";
    }
    
} catch(PDOException $e) {
    echo "❌ Erro: " . $e->getMessage();
}
?>