<?php
// criar_admin.php - Script para criar usuário admin
$host = "localhost";
$dbname = "sistema_patrimonial";
$username = "root";
$password = "";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Senha: admin123
    $senha_hash = password_hash('admin123', PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO usuarios (nome, email, senha, tipo) 
            VALUES ('Administrador', 'admin@cead.com', :senha, 'admin')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':senha', $senha_hash);
    
    if($stmt->execute()) {
        echo "✅ USUÁRIO ADMIN CRIADO COM SUCESSO!<br>";
        echo "📧 E-mail: admin@cead.com<br>";
        echo "🔑 Senha: admin123<br>";
        echo "⚠️ <strong>ALTERE ESTA SENHA APÓS O PRIMEIRO LOGIN!</strong>";
    }
    
} catch(PDOException $e) {
    echo "❌ Erro: " . $e->getMessage();
}
?>