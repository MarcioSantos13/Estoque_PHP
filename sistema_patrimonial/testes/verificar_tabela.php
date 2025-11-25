<?php
// verificar_tabela.php - Verificar estrutura da tabela bens
require_once 'includes/init.php';

echo "<h1>Estrutura da Tabela Bens</h1>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Verificar estrutura da tabela
    $stmt = $db->query("DESCRIBE bens");
    $campos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Campos da Tabela:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($campos as $campo) {
        echo "<tr>";
        echo "<td>{$campo['Field']}</td>";
        echo "<td>{$campo['Type']}</td>";
        echo "<td>{$campo['Null']}</td>";
        echo "<td>{$campo['Key']}</td>";
        echo "<td>{$campo['Default']}</td>";
        echo "<td>{$campo['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Verificar alguns registros de exemplo
    echo "<h3>Registros de Exemplo (primeiros 5):</h3>";
    $stmt = $db->query("SELECT * FROM bens LIMIT 5");
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($registros)) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr>";
        foreach (array_keys($registros[0]) as $campo) {
            echo "<th>$campo</th>";
        }
        echo "</tr>";
        foreach ($registros as $registro) {
            echo "<tr>";
            foreach ($registro as $valor) {
                echo "<td>" . htmlspecialchars($valor ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Nenhum registro encontrado na tabela.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Erro: " . $e->getMessage() . "</p>";
}
?>