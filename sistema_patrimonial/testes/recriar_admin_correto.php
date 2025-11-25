<?php
// recriar_admin_correto.php - Recria o usuário admin com senha correta
$host = "localhost";
$dbname = "sistema_patrimonial";
$username = "root";
$password = "";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Primeiro, remover o usuário existente
    $sql_delete = "DELETE FROM usuarios WHERE email = 'admin@cead.com'";
    $conn->exec($sql_delete);
    echo "✅ Usuário antigo removido<br>";
    
    // Criar hash correto para a senha "admin123"
    $senha_hash = password_hash('admin123', PASSWORD_DEFAULT);
    
    echo "🔑 Hash gerado: " . $senha_hash . "<br>";
    echo "📏 Tamanho do hash: " . strlen($senha_hash) . " caracteres<br>";
    
    // Inserir novo usuário
    $sql_insert = "INSERT INTO usuarios (nome, email, senha, tipo) 
                   VALUES ('Administrador', 'admin@cead.com', :senha, 'admin')";
    
    $stmt = $conn->prepare($sql_insert);
    $stmt->bindParam(':senha', $senha_hash);
    
    if($stmt->execute()) {
        echo "<hr>";
        echo "✅ <strong>USUÁRIO ADMIN CRIADO COM SUCESSO!</strong><br>";
        echo "📧 E-mail: admin@cead.com<br>";
        echo "🔑 Senha: admin123<br>";
        echo "👤 Nome: Administrador<br>";
        echo "🎯 Tipo: admin<br>";
        echo "<hr>";
        echo "⚠️ <strong>AGORA O LOGIN DEVE FUNCIONAR!</strong><br>";
        echo "<a href='login.php' class='btn btn-success mt-3'>Fazer Login</a>";
    }
    
} catch(PDOException $e) {
    echo "❌ Erro: " . $e->getMessage();
}
?>