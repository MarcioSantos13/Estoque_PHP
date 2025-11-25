<?php
// criar_excel_teste.php - Gera um arquivo Excel de teste
require_once 'includes/init.php';
Auth::protegerPagina();

// Verificar se PHP Spreadsheet está disponível
if (!file_exists('vendor/autoload.php')) {
    die('❌ PHP Spreadsheet não instalado. Execute: composer require phpoffice/phpspreadsheet');
}

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Criar nova planilha
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Cabeçalhos
$cabecalhos = [
    'Unidade Gestora',
    'Localidade', 
    'Responsável Localidade',
    'Patrimonio',
    'Descrição',
    'Observação',
    'Status',
    'Responsável pelo bem',
    'Auditor',
    'data da ultima vistoria',
    'vistoria atual',
    'Imagem do bem'
];

// Adicionar cabeçalhos
$sheet->fromArray($cabecalhos, NULL, 'A1');

// Dados de exemplo
$dados = [
    ['CEAD', 'Sala 101', 'João Silva', 'PAT-001', 'Computador Dell i5', 'Em uso', 'Localizado', 'Maria Santos', 'Carlos Audit', '2023-10-15', '2023-11-20', 'computador.jpg'],
    ['CEAD', 'Sala 102', 'João Silva', 'PAT-002', 'Monitor LG 24"', 'Novo', 'Localizado', 'Pedro Costa', 'Carlos Audit', '2023-10-10', '2023-11-20', 'monitor.jpg'],
    ['CEAD', 'Almoxarifado', 'Ana Oliveira', 'PAT-003', 'Mesa Escritório', 'Precisa reparo', 'Pendente', 'José Lima', 'Carlos Audit', '2023-09-01', '2023-11-20', 'mesa.jpg'],
    ['CEAD', 'Sala 201', 'Maria Santos', 'PAT-004', 'Cadeira Giratória', 'Confortável', 'Localizado', 'Ana Silva', 'Carlos Audit', '2023-11-05', '2023-11-20', 'cadeira.jpg'],
    ['CEAD', 'Laboratório', 'Carlos Edu', 'PAT-005', 'Projetor Epson', 'Alta definição', 'Localizado', 'Roberto Alves', 'Carlos Audit', '2023-08-20', '2023-11-20', 'projetor.jpg']
];

// Adicionar dados
$sheet->fromArray($dados, NULL, 'A2');

// Auto-dimensionar colunas
foreach (range('A', 'L') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Estilizar cabeçalho
$sheet->getStyle('A1:L1')->getFont()->setBold(true);
$sheet->getStyle('A1:L1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFE6E6FA');

// Salvar arquivo
$filename = 'patrimonio_teste_' . date('Y-m-d') . '.xlsx';
$writer = new Xlsx($spreadsheet);

// Forçar download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit;
?>