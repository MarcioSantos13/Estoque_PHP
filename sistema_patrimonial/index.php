<?php
// index.php - Página principal melhorada
require_once 'includes/init.php';

Auth::protegerPagina();

// Função para formatar números no padrão brasileiro - CORRIGIDA
function formatarNumero($numero) {
    $numero = $numero ?? 0; // Converte null para 0
    return number_format(floatval($numero), 0, ',', '.');
}

// Função para formatar data no padrão brasileiro
function formatarData($data) {
    if (empty($data) || $data == '0000-00-00') return 'N/A';
    return date('d/m/Y', strtotime($data));
}

// Função para abreviar texto longo
function abreviarTexto($texto, $limite = 30) {
    if (strlen($texto) <= $limite) return $texto;
    return substr($texto, 0, $limite) . '...';
}

// Buscar estatísticas básicas
$database = new Database();
$db = $database->getConnection();

// Inicializar variáveis para evitar erros
$total_count = 0;
$localizados_count = 0;
$pendentes_count = 0;
$percentual_localizados = 0;
$percentual_pendentes = 0;

try {
    // Contar bens
    $query_total = "SELECT COUNT(*) as total FROM bens";
    $total_count = $db->query($query_total)->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $query_localizados = "SELECT COUNT(*) as total FROM bens WHERE status = 'Localizado'";
    $localizados_count = $db->query($query_localizados)->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $query_pendentes = "SELECT COUNT(*) as total FROM bens WHERE status = 'Pendente'";
    $pendentes_count = $db->query($query_pendentes)->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Calcular percentuais - CORRIGIDO
    $percentual_localizados = $total_count > 0 ? ($localizados_count / $total_count) * 100 : 0;
    $percentual_pendentes = $total_count > 0 ? ($pendentes_count / $total_count) * 100 : 0;

} catch (PDOException $e) {
    error_log("Erro ao buscar estatísticas: " . $e->getMessage());
    // Valores permanecem 0 em caso de erro
}

// Buscar últimos bens cadastrados - CORRIGIDO
$ultimos_bens = [];
try {
    // Primeiro, vamos descobrir quais colunas existem na tabela
    $stmt = $db->query("DESCRIBE bens");
    $colunas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Definir colunas baseadas no que existe
    $coluna_nome = in_array('descricao', $colunas) ? 'descricao' : 
                   (in_array('nome', $colunas) ? 'nome' : 'id');
    
    $coluna_patrimonio = in_array('numero_patrimonio', $colunas) ? 'numero_patrimonio' : 
                        (in_array('patrimonio', $colunas) ? 'patrimonio' : 'id');
    
    $coluna_data = in_array('data_criacao', $colunas) ? 'data_criacao' : 
                  (in_array('data_cadastro', $colunas) ? 'data_cadastro' : 
                  (in_array('created_at', $colunas) ? 'created_at' : 'id'));
    
    $query_ultimos = "SELECT id, $coluna_nome as descricao, $coluna_patrimonio as patrimonio, 
                             $coluna_data as data_cadastro, status 
                      FROM bens 
                      ORDER BY $coluna_data DESC 
                      LIMIT 5";
    $ultimos_bens = $db->query($query_ultimos)->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Se ainda houver erro, usar uma consulta mais simples
    error_log("Erro ao buscar últimos bens: " . $e->getMessage());
    $query_ultimos = "SELECT id, status FROM bens ORDER BY id DESC LIMIT 5";
    $ultimos_bens = $db->query($query_ultimos)->fetchAll(PDO::FETCH_ASSOC);
    
    // Adicionar campos padrão para evitar erros no template
    foreach ($ultimos_bens as &$bem) {
        $bem['descricao'] = "Bem #" . $bem['id'];
        $bem['patrimonio'] = "PAT" . $bem['id'];
        $bem['data_cadastro'] = date('Y-m-d');
    }
    unset($bem); // Quebrar a referência
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Controle Patrimonial - CEAD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, var(--primary-color) 0%, #34495e 100%);
        }
        
        .card-hover {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
        }
        
        .stat-card {
            border-left: 4px solid;
            border-radius: 8px;
        }
        
        .stat-card.total { border-left-color: var(--secondary-color); }
        .stat-card.localizados { border-left-color: var(--success-color); }
        .stat-card.pendentes { border-left-color: var(--warning-color); }
        
        .progress {
            height: 8px;
            border-radius: 10px;
        }
        
        .quick-action-btn {
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .quick-action-btn:hover {
            border-color: var(--secondary-color);
            transform: scale(1.05);
        }
        
        .user-welcome {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
        }
        
        .status-badge {
            font-size: 0.7em;
            padding: 0.25em 0.6em;
        }
        
        @media (max-width: 768px) {
            .header-content {
                text-align: center;
            }
            
            .user-stats {
                justify-content: center !important;
                margin-top: 1rem;
            }
            
            .quick-action-btn {
                padding: 1rem !important;
            }
        }
    </style>
</head>
<body class="bg-light">
    <!-- Header Modernizado -->
    <header class="gradient-bg text-white shadow-lg">
        <div class="container py-3">
            <div class="row align-items-center">
                <div class="col-lg-6 header-content">
                    <div class="d-flex align-items-center">
                        <div class="bg-white bg-opacity-20 rounded-circle p-2 me-3">
                            <i class="bi bi-building-gear fs-4"></i>
                        </div>
                        <div>
                            <h1 class="h4 mb-1 fw-bold">Controle Patrimonial - CEAD</h1>
                            <div class="d-flex align-items-center user-welcome px-3 py-1">
                                <i class="bi bi-person-circle me-2"></i>
                                <small class="fw-medium">Bem-vindo, <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?>!</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="d-flex align-items-center justify-content-lg-end user-stats gap-3 mt-3 mt-lg-0">
                        <div class="text-center">
                            <div class="fs-5 fw-bold"><?php echo formatarNumero($total_count); ?></div>
                            <small class="opacity-75">Total de Bens</small>
                        </div>
                        <div class="vr text-white opacity-50"></div>
                        <div class="text-center">
                            <div class="fs-5 fw-bold text-success"><?php echo formatarNumero($localizados_count); ?></div>
                            <small class="opacity-75">Localizados</small>
                        </div>
                        <div class="vr text-white opacity-50"></div>
                        <div class="text-center">
                            <div class="fs-5 fw-bold text-warning"><?php echo formatarNumero($pendentes_count); ?></div>
                            <small class="opacity-75">Pendentes</small>
                        </div>
                        <div class="d-flex gap-2 ms-3">
                            <a href="sistema_crud.php" class="btn btn-light btn-sm" title="Gerenciar Sistema">
                                <i class="bi bi-gear-fill"></i>
                            </a>
                            <a href="includes/auth.php?logout=true" class="btn btn-outline-light btn-sm" title="Sair do Sistema">
                                <i class="bi bi-box-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="container py-4">
        <!-- Cards de Estatísticas Melhorados -->
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card stat-card total card-hover border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h3 class="h2 fw-bold text-primary mb-1">
                                    <?php echo formatarNumero($total_count); ?>
                                </h3>
                                <p class="text-muted mb-2">Total de Bens</p>
                                <small class="text-muted">
                                    <i class="bi bi-database me-1"></i>Inventário completo
                                </small>
                            </div>
                            <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-database text-primary fs-2"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card stat-card localizados card-hover border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h3 class="h2 fw-bold text-success mb-1">
                                    <?php echo formatarNumero($localizados_count); ?>
                                </h3>
                                <p class="text-muted mb-2">Bens Localizados</p>
                                <div class="progress mb-2" style="width: 120px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo number_format($percentual_localizados, 2); ?>%"></div>
                                </div>
                                <small class="text-muted">
                                    <?php echo number_format($percentual_localizados, 1, ',', '.'); ?>% do total
                                </small>
                            </div>
                            <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-check-circle-fill text-success fs-2"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card stat-card pendentes card-hover border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h3 class="h2 fw-bold text-warning mb-1">
                                    <?php echo formatarNumero($pendentes_count); ?>
                                </h3>
                                <p class="text-muted mb-2">Bens Pendentes</p>
                                <div class="progress mb-2" style="width: 120px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo number_format($percentual_pendentes, 2); ?>%"></div>
                                </div>
                                <small class="text-muted">
                                    <?php echo number_format($percentual_pendentes, 1, ',', '.'); ?>% do total
                                </small>
                            </div>
                            <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-clock text-warning fs-2"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Ações Rápidas -->
            <div class="col-lg-8">
                <div class="card shadow-lg border-0 rounded-3 h-100">
                    <div class="card-header bg-white py-3 border-0">
                        <h2 class="h5 mb-0 fw-bold">
                            <i class="bi bi-lightning-charge-fill text-primary me-2"></i> Ações Rápidas
                        </h2>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-sm-6 col-md-4">
                                <a href="sistema_crud.php" class="btn btn-outline-primary quick-action-btn w-100 py-4 d-flex flex-column">
                                    <i class="bi bi-list-check fs-1 mb-2"></i>
                                    <span class="fw-medium">Gerenciar Bens</span>
                                    <small class="text-muted mt-1">Cadastrar e editar</small>
                                </a>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <a href="auditar.php" class="btn btn-outline-success quick-action-btn w-100 py-4 d-flex flex-column">
                                    <i class="bi bi-search fs-1 mb-2"></i>
                                    <span class="fw-medium">Auditar Bens</span>
                                    <small class="text-muted mt-1">Verificar status</small>
                                </a>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <a href="importar.php" class="btn btn-outline-info quick-action-btn w-100 py-4 d-flex flex-column">
                                    <i class="bi bi-upload fs-1 mb-2"></i>
                                    <span class="fw-medium">Importar Dados</span>
                                    <small class="text-muted mt-1">CSV e planilhas</small>
                                </a>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <a href="relatorios.php" class="btn btn-outline-warning quick-action-btn w-100 py-4 d-flex flex-column">
                                    <i class="bi bi-graph-up fs-1 mb-2"></i>
                                    <span class="fw-medium">Relatórios</span>
                                    <small class="text-muted mt-1">Análises e dados</small>
                                </a>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <a href="backup_limpeza.php" class="btn btn-outline-secondary quick-action-btn w-100 py-4 d-flex flex-column">
                                    <i class="bi bi-shield-check fs-1 mb-2"></i>
                                    <span class="fw-medium">Backup</span>
                                    <small class="text-muted mt-1">Segurança</small>
                                </a>
                            </div>
                            <div class="col-sm-6 col-md-4">
                                <a href="ajuda.html" class="btn btn-outline-dark quick-action-btn w-100 py-4 d-flex flex-column">
                                    <i class="bi bi-question-circle fs-1 mb-2"></i>
                                    <span class="fw-medium">Ajuda</span>
                                    <small class="text-muted mt-1">Suporte</small>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Últimos Bens Cadastrados -->
            <div class="col-lg-4">
                <div class="card shadow-lg border-0 rounded-3 h-100">
                    <div class="card-header bg-white py-3 border-0">
                        <h2 class="h5 mb-0 fw-bold">
                            <i class="bi bi-clock-history text-primary me-2"></i> Últimos Cadastros
                        </h2>
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($ultimos_bens) > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($ultimos_bens as $bem): ?>
                                    <div class="list-group-item border-0 py-3">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="flex-grow-1 me-3">
                                                <h6 class="mb-1 fw-bold text-truncate" title="<?php echo htmlspecialchars($bem['descricao']); ?>">
                                                    <?php echo abreviarTexto(htmlspecialchars($bem['descricao'])); ?>
                                                </h6>
                                                <small class="text-muted">
                                                    Patrimônio: <?php echo htmlspecialchars($bem['patrimonio']); ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge status-badge <?php echo $bem['status'] === 'Localizado' ? 'bg-success' : 'bg-warning'; ?>">
                                                    <?php echo htmlspecialchars($bem['status']); ?>
                                                </span>
                                                <small class="d-block text-muted mt-1">
                                                    <?php echo formatarData($bem['data_cadastro']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                                <p class="text-muted mb-0">Nenhum bem cadastrado</p>
                                <a href="sistema_crud.php" class="btn btn-primary btn-sm mt-2">
                                    <i class="bi bi-plus-circle me-1"></i>Cadastrar Primeiro Bem
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (count($ultimos_bens) > 0): ?>
                    <div class="card-footer bg-white border-0 py-3">
                        <a href="sistema_crud.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-list-ul me-2"></i>Ver Todos os Bens
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Status do Sistema -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card border-0 bg-transparent">
                    <div class="card-body text-center py-3">
                        <div class="d-flex flex-wrap justify-content-center align-items-center gap-4 text-muted">
                            <small>
                                <i class="bi bi-calendar-check me-1"></i>
                                <?php echo date('d/m/Y'); ?>
                            </small>
                            <small>
                                <i class="bi bi-clock me-1"></i>
                                <span data-hora><?php echo date('H:i'); ?></span>
                            </small>
                            <small>
                                <i class="bi bi-info-circle me-1"></i>
                                Sistema de Controle Patrimonial - Versão 2.0
                            </small>
                            <small>
                                <i class="bi bi-database me-1"></i>
                                <?php echo formatarNumero($total_count); ?> itens cadastrados
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Atualizar hora em tempo real
        function atualizarHora() {
            const agora = new Date();
            const horaFormatada = agora.toLocaleTimeString('pt-BR');
            const elementoHora = document.querySelector('[data-hora]');
            if (elementoHora) {
                elementoHora.textContent = horaFormatada;
            }
        }
        
        setInterval(atualizarHora, 1000);
        
        // Adicionar efeitos de interação
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card-hover');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>