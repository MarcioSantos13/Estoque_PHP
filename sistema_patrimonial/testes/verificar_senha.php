<?php
// verificar_senha.php - Verifica a senha do usuário admin
$host = "localhost";
$dbname = "sistema_patrimonial";
$username = "root";
$password = "";

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Buscar o usuário admin
    $sql = "SELECT id, nome, email, senha, tipo FROM usuarios WHERE email = 'admin@cead.com'";
    $stmt = $conn->query($sql);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($usuario) {
        echo "<h3>🔐 Informações do Usuário Admin</h3>";
        echo "ID: " . $usuario['id'] . "<br>";
        echo "Nome: " . $usuario['nome'] . "<br>";
        echo "E-mail: " . $usuario['email'] . "<br>";
        echo "Tipo: " . $usuario['tipo'] . "<br>";
        echo "Senha (hash): " . $usuario['senha'] . "<br>";
        echo "Tamanho do hash: " . strlen($usuario['senha']) . " caracteres<br>";
        
        echo "<hr><h4>🧪 Testando Senhas:</h4>";
        
        $senhas_teste = [
            'admin123',
            'admin',
            '123456',
            'password',
            'admin@123',
            'Admin123',
            'cead123',
            'patrimonio',
            '1234',
            'senha'
        ];
        
        foreach($senhas_teste as $senha) {
            $resultado = password_verify($senha, $usuario['senha']) ? "✅ CORRETA" : "❌ incorreta";
            echo "Senha '$senha': $resultado<br>";
        }
        
    } else {
        echo "❌ Usuário admin@cead.com não encontrado!";
    }
    
} catch(PDOException $e) {
    echo "❌ Erro: " . $e->getMessage();
}
?>