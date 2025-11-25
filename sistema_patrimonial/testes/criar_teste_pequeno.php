<?php
// criar_teste_pequeno.php - Cria arquivo de teste pequeno
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Cabeçalhos simples
$cabecalhos = ['Unidade Gestora', 'Localidade', 'Responsável', 'Patrimonio', 'Descrição'];
$sheet->fromArray($cabecalhos, NULL, 'A1');

// Apenas 2 linhas de dados
$dados = [
    ['CEAD', 'Sala 101', 'João', 'TEST-001', 'Computador'],
    ['CEAD', 'Sala 102', 'Maria', 'TEST-002', 'Monitor']
];
$sheet->fromArray($dados, NULL, 'A2');

// Salvar
$writer = new Xlsx($spreadsheet);
$filename = 'teste_pequeno.xlsx';
$writer->save($filename);

echo "✅ Arquivo de teste criado: $filename<br>";
echo "📍 Local: " . __DIR__ . "/$filename<br>";
echo "<a href='teste_api_simples.php' class='btn btn-primary'>Testar API</a>";
?>