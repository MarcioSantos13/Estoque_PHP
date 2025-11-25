<?php
// teste_phpspreadsheet.php
echo "<h3>🧪 Teste PHP Spreadsheet</h3>";

$vendor_path = __DIR__ . '/vendor/autoload.php';

if (!file_exists($vendor_path)) {
    echo "❌ vendor/autoload.php não encontrado!<br>";
    echo "📍 Caminho: $vendor_path<br>";
    echo "💡 Solução: Execute no terminal:<br>";
    echo "<code>cd C:\\xampp\\htdocs\\sistema_patrimonial<br>";
    echo "composer require phpoffice/phpspreadsheet</code>";
} else {
    echo "✅ vendor/autoload.php encontrado<br>";
    
    try {
        require_once $vendor_path;
        
        if (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            echo "✅ Classe Spreadsheet carregada<br>";
            
            // Teste simples
            $spreadsheet = new PhpOffice\PhpSpreadsheet\Spreadsheet();
            echo "✅ Objeto Spreadsheet criado<br>";
            
            echo "<div class='alert alert-success mt-3'>";
            echo "🎉 PHP Spreadsheet está funcionando perfeitamente!";
            echo "</div>";
            
        } else {
            echo "❌ Classe Spreadsheet não encontrada";
        }
        
    } catch (Exception $e) {
        echo "❌ Erro: " . $e->getMessage();
    }
}

echo "<hr>";
echo "<a href='importar.php' class='btn btn-primary'>Testar Importação</a>";
?>