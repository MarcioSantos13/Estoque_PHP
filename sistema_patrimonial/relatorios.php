<?php
// relatorios.php - Sistema de Relatórios com Paginação
require_once 'includes/init.php';

Auth::protegerPagina();

// Verificar permissões
if ($_SESSION['usuario_tipo'] !== 'admin') {
    $_SESSION['erro'] = 'Você não tem permissão para acessar relatórios.';
    header('Location: index.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Configurações de paginação
$itens_por_pagina = $_GET['itens_por_pagina'] ?? 50;
$pagina_atual = $_GET['pagina'] ?? 1;

// Validar itens por página
$itens_por_pagina = in_array($itens_por_pagina, [50, 100, 200]) ? $itens_por_pagina : 50;
$pagina_atual = max(1, (int)$pagina_atual);

// Calcular offset
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Processar filtros
$filtro_status = $_GET['status'] ?? 'todos';
$filtro_unidade = $_GET['unidade'] ?? '';
$filtro_localidade = $_GET['localidade'] ?? '';
$filtro_data_inicio = $_GET['data_inicio'] ?? '';
$filtro_data_fim = $_GET['data_fim'] ?? '';

// Construir query base
$where_conditions = [];
$params = [];

if ($filtro_status !== 'todos') {
    $where_conditions[] = "b.status = :status";
    $params[':status'] = $filtro_status;
}

if (!empty($filtro_unidade)) {
    $where_conditions[] = "b.unidade_gestora LIKE :unidade";
    $params[':unidade'] = "%$filtro_unidade%";
}

if (!empty($filtro_localidade)) {
    $where_conditions[] = "b.localidade LIKE :localidade";
    $params[':localidade'] = "%$filtro_localidade%";
}

if (!empty($filtro_data_inicio)) {
    $where_conditions[] = "b.data_ultima_vistoria >= :data_inicio";
    $params[':data_inicio'] = $filtro_data_inicio;
}

if (!empty($filtro_data_fim)) {
    $where_conditions[] = "b.data_ultima_vistoria <= :data_fim";
    $params[':data_fim'] = $filtro_data_fim;
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Query para contar total de registros (para paginação)
$query_count = "SELECT COUNT(*) as total FROM bens b $where_sql";
$stmt_count = $db->prepare($query_count);
foreach ($params as $key => $value) {
    $stmt_count->bindValue($key, $value);
}
$stmt_count->execute();
$total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];

// Calcular total de páginas
$total_paginas = ceil($total_registros / $itens_por_pagina);

// Ajustar página atual se necessário
if ($pagina_atual > $total_paginas && $total_paginas > 0) {
    $pagina_atual = $total_paginas;
}

// Buscar dados para relatório com paginação - ATUALIZADO: usando situacao
$query = "SELECT 
            b.id,
            b.unidade_gestora,
            b.localidade,
            b.responsavel_localidade,
            b.numero_patrimonio,
            b.descricao,
            b.situacao,
            b.status,
            b.responsavel_bem,
            b.auditor,
            b.data_ultima_vistoria,
            b.data_vistoria_atual,
            b.data_criacao
          FROM bens b 
          $where_sql 
          ORDER BY b.data_criacao DESC
          LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);

// Bind dos parâmetros de filtro
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

// Bind dos parâmetros de paginação
$stmt->bindValue(':limit', $itens_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$bens = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar unidades e localidades para filtros
$unidades = $db->query("SELECT DISTINCT unidade_gestora FROM bens WHERE unidade_gestora IS NOT NULL AND unidade_gestora != '' ORDER BY unidade_gestora")->fetchAll(PDO::FETCH_COLUMN);
$localidades = $db->query("SELECT DISTINCT localidade FROM bens WHERE localidade IS NOT NULL AND localidade != '' ORDER BY localidade")->fetchAll(PDO::FETCH_COLUMN);

// Processar exportação (exporta todos os dados, não apenas a página atual)
if (isset($_GET['exportar'])) {
    $tipo_export = $_GET['exportar'];
    
    // Buscar todos os dados para exportação - ATUALIZADO: usando situacao
    $query_export = "SELECT 
                    b.id,
                    b.unidade_gestora,
                    b.localidade,
                    b.responsavel_localidade,
                    b.numero_patrimonio,
                    b.descricao,
                    b.situacao,
                    b.status,
                    b.responsavel_bem,
                    b.auditor,
                    b.data_ultima_vistoria,
                    b.data_vistoria_atual,
                    b.data_criacao
                  FROM bens b 
                  $where_sql 
                  ORDER BY b.data_criacao DESC";
    
    $stmt_export = $db->prepare($query_export);
    foreach ($params as $key => $value) {
        $stmt_export->bindValue($key, $value);
    }
    $stmt_export->execute();
    $bens_export = $stmt_export->fetchAll(PDO::FETCH_ASSOC);
    
    if ($tipo_export === 'csv') {
        exportarCSV($bens_export);
    } elseif ($tipo_export === 'pdf') {
        exportarPDF($bens_export);
    }
    exit; // Importante: sair após a exportação
}

function exportarCSV($dados) {
    // Configurar headers para CSV com UTF-8
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio_bens_' . date('Y-m-d_H-i') . '.csv"');
    
    // Criar output stream
    $output = fopen('php://output', 'w');
    
    // Adicionar BOM para UTF-8 (para Excel)
    fwrite($output, "\xEF\xBB\xBF");
    
    // Cabeçalhos atualizados
    $cabecalhos = [
        'ID',
        'Número Patrimônio',
        'Descrição',
        'Situação',
        'Unidade Gestora',
        'Localidade',
        'Responsável Local',
        'Responsável Bem',
        'Auditor',
        'Status',
        'Data Última Vistoria',
        'Data Vistoria Atual',
        'Data Criação'
    ];
    
    fputcsv($output, $cabecalhos, ';');
    
    // Dados atualizados
    foreach ($dados as $bem) {
        $linha = [
            $bem['id'] ?? '',
            $bem['numero_patrimonio'] ?? '',
            $bem['descricao'] ?? '',
            $bem['situacao'] ?? '', // ATUALIZADO: campo situacao da tabela bens
            $bem['unidade_gestora'] ?? '',
            $bem['localidade'] ?? '',
            $bem['responsavel_localidade'] ?? '',
            $bem['responsavel_bem'] ?? '',
            $bem['auditor'] ?? '',
            $bem['status'] ?? '',
            $bem['data_ultima_vistoria'] ? date('d/m/Y', strtotime($bem['data_ultima_vistoria'])) : '',
            $bem['data_vistoria_atual'] ? date('d/m/Y', strtotime($bem['data_vistoria_atual'])) : '',
            $bem['data_criacao'] ? date('d/m/Y H:i', strtotime($bem['data_criacao'])) : ''
        ];
        
        fputcsv($output, $linha, ';');
    }
    
    fclose($output);
    exit;
}

function exportarPDF($dados) {
    // Para PDF real, você precisaria de uma biblioteca como TCPDF ou DomPDF
    // Aqui vou fornecer um HTML bem formatado que pode ser impresso como PDF pelo navegador
    
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Relatório de Bens Patrimoniais</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                margin: 20px; 
                color: #333;
            }
            h1 { 
                color: #2c3e50; 
                border-bottom: 2px solid #3498db; 
                padding-bottom: 10px; 
                text-align: center;
            }
            .header-info {
                display: flex;
                justify-content: space-between;
                margin-bottom: 20px;
                padding: 10px;
                background: #f8f9fa;
                border-radius: 5px;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-top: 20px; 
                font-size: 10px;
            }
            th, td { 
                border: 1px solid #ddd; 
                padding: 6px; 
                text-align: left; 
            }
            th { 
                background-color: #2c3e50; 
                color: white;
                font-weight: bold; 
            }
            tr:nth-child(even) {
                background-color: #f8f9fa;
            }
            .badge { 
                padding: 3px 6px; 
                border-radius: 3px; 
                font-size: 9px; 
                font-weight: bold;
            }
            .badge-success { background-color: #28a745; color: white; }
            .badge-warning { background-color: #ffc107; color: black; }
            .page-break { page-break-after: always; }
            .footer {
                margin-top: 30px;
                text-align: center;
                color: #666;
                font-size: 10px;
                border-top: 1px solid #ddd;
                padding-top: 10px;
            }
            .summary {
                background: #e9ecef;
                padding: 10px;
                border-radius: 5px;
                margin-bottom: 20px;
                font-size: 12px;
            }
            @media print {
                body { margin: 0; }
                .page-break { page-break-after: always; }
            }
        </style>
    </head>
    <body>
        <h1>Relatório de Bens Patrimoniais - CEAD</h1>
        
        <div class='header-info'>
            <div>
                <strong>Data de geração:</strong> " . date('d/m/Y H:i') . "
            </div>
            <div>
                <strong>Total de registros:</strong> " . number_format(count($dados), 0, ',', '.') . "
            </div>
        </div>
        
        <div class='summary'>
            <strong>Resumo:</strong> " . count($dados) . " bens patrimoniais listados
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Patrimônio</th>
                    <th>Descrição</th>
                    <th>Unidade</th>
                    <th>Localidade</th>
                    <th>Status</th>
                    <th>Situação</th>
                    <th>Resp. Local</th>
                    <th>Última Vistoria</th>
                </tr>
            </thead>
            <tbody>";
    
    $contador = 0;
    foreach ($dados as $bem) {
        $contador++;
        // Quebra de página a cada 25 registros para melhor legibilidade
        if ($contador % 25 == 0 && $contador < count($dados)) {
            $html .= "</tbody></table><div class='page-break'></div><table><thead><tr>
                    <th>Patrimônio</th>
                    <th>Descrição</th>
                    <th>Unidade</th>
                    <th>Localidade</th>
                    <th>Status</th>
                    <th>Situação</th>
                    <th>Resp. Local</th>
                    <th>Última Vistoria</th>
                </tr></thead><tbody>";
        }
        
        $statusClass = ($bem['status'] ?? '') === 'Localizado' ? 'badge-success' : 'badge-warning';
        $descricao = strlen($bem['descricao'] ?? '') > 40 ? substr($bem['descricao'], 0, 40) . '...' : ($bem['descricao'] ?? '');
        $situacao = strlen($bem['situacao'] ?? '') > 30 ? substr($bem['situacao'], 0, 30) . '...' : ($bem['situacao'] ?? '');
        
        $html .= "<tr>
                <td><strong>" . htmlspecialchars($bem['numero_patrimonio'] ?? '') . "</strong></td>
                <td>" . htmlspecialchars($descricao) . "</td>
                <td>" . htmlspecialchars($bem['unidade_gestora'] ?? '') . "</td>
                <td>" . htmlspecialchars($bem['localidade'] ?? '') . "</td>
                <td><span class='badge $statusClass'>" . htmlspecialchars($bem['status'] ?? '') . "</span></td>
                <td>" . htmlspecialchars($situacao) . "</td>
                <td>" . htmlspecialchars($bem['responsavel_localidade'] ?? '') . "</td>
                <td>" . (($bem['data_ultima_vistoria'] ?? '') ? date('d/m/Y', strtotime($bem['data_ultima_vistoria'])) : 'N/A') . "</td>
              </tr>";
    }
    
    $html .= "</tbody>
        </table>
        
        <div class='footer'>
            <p>Sistema de Controle Patrimonial - CEAD<br>
            Relatório gerado automaticamente em " . date('d/m/Y \à\s H:i') . "</p>
        </div>
    </body>
    </html>";
    
    // Configurar headers para forçar download
    header('Content-Type: application/html');
    header('Content-Disposition: attachment; filename="relatorio_bens_' . date('Y-m-d_H-i') . '.html"');
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    
    echo $html;
    exit;
}

// Função para construir query string mantendo filtros
function buildQueryString($exclude = []) {
    $params = $_GET;
    foreach ($exclude as $key) {
        unset($params[$key]);
    }
    return $params ? '?' . http_build_query($params) : '';
}

// Função auxiliar para evitar erros com valores nulos
function safeStrlen($string) {
    return $string === null ? 0 : strlen($string);
}

function safeHtmlspecialchars($string) {
    return $string === null ? '' : htmlspecialchars($string);
}

// Função para formatar números no padrão brasileiro
function formatarNumeroBr($numero) {
    return number_format($numero, 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - Sistema Patrimonial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }
        
        .table-responsive {
            border-radius: 0.375rem;
        }
        
        .status-badge {
            font-size: 0.75em;
        }
        
        .export-btn {
            min-width: 120px;
        }
        
        .filter-section {
            background: #f8f9fa;
            border-radius: 0.375rem;
            padding: 1.5rem;
        }
        
        .stat-card {
            border-left: 4px solid;
        }
        
        .stat-card.total { border-left-color: #6c757d; }
        .stat-card.localizados { border-left-color: #198754; }
        .stat-card.pendentes { border-left-color: #ffc107; }
        
        .table th {
            border-top: none;
            font-weight: 600;
        }
        
        .pagination-container {
            background: #f8f9fa;
            border-radius: 0.375rem;
            padding: 1rem;
        }
        
        .itens-por-pagina {
            max-width: 150px;
        }
        
        .page-link {
            color: #495057;
        }
        
        .page-item.active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        
        .numero-br {
            font-family: Arial, sans-serif;
        }
        
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .loading-spinner {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Loading Overlay -->
    <div class="loading" id="loading">
        <div class="loading-spinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <div class="mt-2">Gerando relatório, aguarde...</div>
        </div>
    </div>

    <!-- Header -->
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-graph-up me-2"></i>Relatórios
            </a>
            <div class="d-flex align-items-center">
                <span class="text-light me-3"><?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></span>
                <a href="index.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Voltar
                </a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Título e Estatísticas -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h1 class="h3 mb-0">Relatórios de Bens Patrimoniais</h1>
                    <div class="d-flex gap-2">
                        <a href="javascript:void(0)" onclick="exportar('csv')" class="btn btn-success export-btn">
                            <i class="bi bi-file-earmark-spreadsheet me-1"></i>CSV
                        </a>
                        <a href="javascript:void(0)" onclick="exportar('pdf')" class="btn btn-danger export-btn">
                            <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                        </a>
                    </div>
                </div>
                
                <!-- Cards de Estatísticas -->
                <div class="row g-3">
                    <?php
                    // Calcular estatísticas totais (não apenas da página atual)
                    $query_stats = "SELECT 
                                    COUNT(*) as total,
                                    SUM(CASE WHEN status = 'Localizado' THEN 1 ELSE 0 END) as localizados,
                                    SUM(CASE WHEN status = 'Pendente' THEN 1 ELSE 0 END) as pendentes
                                  FROM bens b $where_sql";
                    
                    $stmt_stats = $db->prepare($query_stats);
                    foreach ($params as $key => $value) {
                        $stmt_stats->bindValue($key, $value);
                    }
                    $stmt_stats->execute();
                    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
                    
                    $total = $stats['total'];
                    $localizados = $stats['localizados'];
                    $pendentes = $stats['pendentes'];
                    ?>
                    <div class="col-md-4">
                        <div class="card stat-card total h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h4 class="card-title text-muted">Total</h4>
                                        <h2 class="text-dark numero-br"><?php echo formatarNumeroBr($total); ?></h2>
                                    </div>
                                    <div class="text-muted fs-1">
                                        <i class="bi bi-database"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card localizados h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h4 class="card-title text-muted">Localizados</h4>
                                        <h2 class="text-success numero-br"><?php echo formatarNumeroBr($localizados); ?></h2>
                                    </div>
                                    <div class="text-success fs-1">
                                        <i class="bi bi-check-circle"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stat-card pendentes h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h4 class="card-title text-muted">Pendentes</h4>
                                        <h2 class="text-warning numero-br"><?php echo formatarNumeroBr($pendentes); ?></h2>
                                    </div>
                                    <div class="text-warning fs-1">
                                        <i class="bi bi-clock"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-funnel me-2"></i>Filtros
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3" id="filtrosForm">
                    <input type="hidden" name="pagina" value="1">
                    
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="todos" <?php echo $filtro_status === 'todos' ? 'selected' : ''; ?>>Todos os Status</option>
                            <option value="Localizado" <?php echo $filtro_status === 'Localizado' ? 'selected' : ''; ?>>Localizados</option>
                            <option value="Pendente" <?php echo $filtro_status === 'Pendente' ? 'selected' : ''; ?>>Pendentes</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="unidade" class="form-label">Unidade Gestora</label>
                        <select name="unidade" id="unidade" class="form-select">
                            <option value="">Todas as Unidades</option>
                            <?php foreach ($unidades as $unidade): ?>
                                <option value="<?php echo htmlspecialchars($unidade); ?>" 
                                    <?php echo $filtro_unidade === $unidade ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($unidade); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="localidade" class="form-label">Localidade</label>
                        <select name="localidade" id="localidade" class="form-select">
                            <option value="">Todas as Localidades</option>
                            <?php foreach ($localidades as $localidade): ?>
                                <option value="<?php echo htmlspecialchars($localidade); ?>" 
                                    <?php echo $filtro_localidade === $localidade ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($localidade); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="itens_por_pagina" class="form-label">Itens por Página</label>
                        <select name="itens_por_pagina" id="itens_por_pagina" class="form-select">
                            <option value="50" <?php echo $itens_por_pagina == 50 ? 'selected' : ''; ?>>50 itens</option>
                            <option value="100" <?php echo $itens_por_pagina == 100 ? 'selected' : ''; ?>>100 itens</option>
                            <option value="200" <?php echo $itens_por_pagina == 200 ? 'selected' : ''; ?>>200 itens</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="data_inicio" class="form-label">Data Início Vistoria</label>
                        <input type="date" name="data_inicio" id="data_inicio" 
                               class="form-control" value="<?php echo htmlspecialchars($filtro_data_inicio); ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label for="data_fim" class="form-label">Data Fim Vistoria</label>
                        <input type="date" name="data_fim" id="data_fim" 
                               class="form-control" value="<?php echo htmlspecialchars($filtro_data_fim); ?>">
                    </div>
                    
                    <div class="col-12">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-funnel me-1"></i>Aplicar Filtros
                            </button>
                            <a href="relatorios.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise me-1"></i>Limpar Filtros
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Controles de Paginação Superior -->
        <?php if ($total_registros > 0): ?>
        <div class="card pagination-container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="d-flex align-items-center">
                        <span class="text-muted me-3">
                            Mostrando <strong class="numero-br"><?php echo formatarNumeroBr(min($itens_por_pagina, count($bens))); ?></strong> 
                            de <strong class="numero-br"><?php echo formatarNumeroBr($total_registros); ?></strong> registros
                        </span>
                        
                        <!-- Seletor de itens por página -->
                        <form method="GET" class="d-inline" id="formItensPorPagina">
                            <?php foreach ($_GET as $key => $value): ?>
                                <?php if ($key !== 'itens_por_pagina' && $key !== 'pagina'): ?>
                                    <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <select name="itens_por_pagina" class="form-select form-select-sm itens-por-pagina" onchange="this.form.submit()">
                                <option value="50" <?php echo $itens_por_pagina == 50 ? 'selected' : ''; ?>>50 por página</option>
                                <option value="100" <?php echo $itens_por_pagina == 100 ? 'selected' : ''; ?>>100 por página</option>
                                <option value="200" <?php echo $itens_por_pagina == 200 ? 'selected' : ''; ?>>200 por página</option>
                            </select>
                        </form>
                    </div>
                </div>
                <div class="col-md-6">
                    <!-- Paginação -->
                    <?php if ($total_paginas > 1): ?>
                    <nav aria-label="Paginação">
                        <ul class="pagination justify-content-end mb-0">
                            <!-- Botão Anterior -->
                            <li class="page-item <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo buildQueryString(['pagina']); ?>&pagina=<?php echo $pagina_atual - 1; ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                            
                            <!-- Páginas -->
                            <?php for ($i = max(1, $pagina_atual - 2); $i <= min($total_paginas, $pagina_atual + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $pagina_atual ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo buildQueryString(['pagina']); ?>&pagina=<?php echo $i; ?>">
                                        <span class="numero-br"><?php echo $i; ?></span>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <!-- Botão Próximo -->
                            <li class="page-item <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo buildQueryString(['pagina']); ?>&pagina=<?php echo $pagina_atual + 1; ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tabela de Resultados -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Resultados do Relatório</h5>
                <small class="text-muted">
                    Página <span class="numero-br"><?php echo $pagina_atual; ?></span> de <span class="numero-br"><?php echo $total_paginas; ?></span> 
                    (<span class="numero-br"><?php echo formatarNumeroBr($total_registros); ?></span> registros)
                </small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Patrimônio</th>
                                <th>Descrição</th>
                                <th>Unidade</th>
                                <th>Localidade</th>
                                <th>Status</th>
                                <th>Situação</th>
                                <th>Última Vistoria</th>
                                <th>Responsável Local</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($bens) > 0): ?>
                                <?php foreach ($bens as $bem): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo safeHtmlspecialchars($bem['numero_patrimonio']); ?></strong>
                                        </td>
                                        <td title="<?php echo safeHtmlspecialchars($bem['descricao']); ?>">
                                            <?php echo safeStrlen($bem['descricao']) > 50 ? 
                                                substr(safeHtmlspecialchars($bem['descricao']), 0, 50) . '...' : 
                                                safeHtmlspecialchars($bem['descricao']); ?>
                                        </td>
                                        <td><?php echo safeHtmlspecialchars($bem['unidade_gestora']); ?></td>
                                        <td><?php echo safeHtmlspecialchars($bem['localidade']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $bem['status'] === 'Localizado' ? 'bg-success' : 'bg-warning'; ?> status-badge">
                                                <?php echo safeHtmlspecialchars($bem['status']); ?>
                                            </span>
                                        </td>
                                        <td title="<?php echo safeHtmlspecialchars($bem['situacao']); ?>">
                                            <?php echo safeStrlen($bem['situacao']) > 30 ? 
                                                substr(safeHtmlspecialchars($bem['situacao']), 0, 30) . '...' : 
                                                safeHtmlspecialchars($bem['situacao']); ?>
                                        </td>
                                        <td>
                                            <?php echo $bem['data_ultima_vistoria'] ? 
                                                date('d/m/Y', strtotime($bem['data_ultima_vistoria'])) : 
                                                '<span class="text-muted">N/A</span>'; ?>
                                        </td>
                                        <td title="<?php echo safeHtmlspecialchars($bem['responsavel_localidade']); ?>">
                                            <?php echo safeStrlen($bem['responsavel_localidade']) > 30 ? 
                                                substr(safeHtmlspecialchars($bem['responsavel_localidade']), 0, 30) . '...' : 
                                                safeHtmlspecialchars($bem['responsavel_localidade']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="bi bi-inbox fs-1 text-muted d-block mb-2"></i>
                                        <span class="text-muted">Nenhum registro encontrado com os filtros aplicados.</span>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Controles de Paginação Inferior -->
        <?php if ($total_registros > 0 && $total_paginas > 1): ?>
        <div class="card pagination-container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted">
                        Página <strong class="numero-br"><?php echo $pagina_atual; ?></strong> de <strong class="numero-br"><?php echo $total_paginas; ?></strong>
                    </span>
                </div>
                <nav aria-label="Paginação inferior">
                    <ul class="pagination mb-0">
                        <!-- Botão Anterior -->
                        <li class="page-item <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo buildQueryString(['pagina']); ?>&pagina=<?php echo $pagina_atual - 1; ?>">
                                <i class="bi bi-chevron-left me-1"></i>Anterior
                            </a>
                        </li>
                        
                        <!-- Páginas -->
                        <?php for ($i = max(1, $pagina_atual - 2); $i <= min($total_paginas, $pagina_atual + 2); $i++): ?>
                            <li class="page-item <?php echo $i == $pagina_atual ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo buildQueryString(['pagina']); ?>&pagina=<?php echo $i; ?>">
                                    <span class="numero-br"><?php echo $i; ?></span>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <!-- Botão Próximo -->
                        <li class="page-item <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo buildQueryString(['pagina']); ?>&pagina=<?php echo $pagina_atual + 1; ?>">
                                Próximo<i class="bi bi-chevron-right ms-1"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportar(tipo) {
            const totalRegistros = <?php echo $total_registros; ?>;
            const loading = document.getElementById('loading');
            
            // Mostrar loading
            loading.style.display = 'flex';
            
            // Adicionar confirmação para exportações grandes
            if (totalRegistros > 1000) {
                if (!confirm(`Você está prestes a exportar ${totalRegistros.toLocaleString('pt-BR')} registros. Isso pode demorar alguns instantes. Deseja continuar?`)) {
                    loading.style.display = 'none';
                    return;
                }
            }
            
            // Construir URL de exportação
            const params = new URLSearchParams(window.location.search);
            params.set('exportar', tipo);
            
            // Redirecionar para exportação
            window.location.href = 'relatorios.php?' + params.toString();
            
            // Esconder loading após 5 segundos (fallback)
            setTimeout(() => {
                loading.style.display = 'none';
            }, 5000);
        }

        // Auto-submit form quando alguns filtros mudarem
        document.getElementById('status').addEventListener('change', function() {
            this.form.submit();
        });

        // Mostrar loading durante o carregamento dos filtros
        document.getElementById('filtrosForm').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Carregando...';
                submitBtn.disabled = true;
            }
        });

        // Restaurar botão se a página for recarregada
        window.addEventListener('load', function() {
            const submitBtn = document.querySelector('#filtrosForm button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="bi bi-funnel me-1"></i>Aplicar Filtros';
                submitBtn.disabled = false;
            }
        });
    </script>
</body>
</html>