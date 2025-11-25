<?php
// diagnostico.php
require_once 'includes/database.php';

$database = new Database();
$db = $database->getConnection();

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO DO BANCO DE DADOS ===\n\n";

try {
    // Verificar tabelas
    $tabelas = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tabelas encontradas:\n";
    foreach ($tabelas as $tabela) {
        echo "- {$tabela}\n";
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    
    if (in_array('bens', $tabelas)) {
        echo "Estrutura da tabela 'bens':\n";
        $estrutura = $db->query("DESCRIBE bens")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($estrutura as $campo) {
            echo "Campo: {$campo['Field']} | Tipo: {$campo['Type']} | Nulo: {$campo['Null']} | Chave: {$campo['Key']}\n";
        }
        
        echo "\nPrimeiros 3 registros:\n";
        $registros = $db->query("SELECT * FROM bens LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
        print_r($registros);
    } else {
        echo "Tabela 'bens' não encontrada!\n";
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}