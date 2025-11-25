<?php
// visualizar_bem.php - Página para visualização detalhada de um bem
require_once 'includes/init.php';

Auth::protegerPagina();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: sistema_crud.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Buscar dados do bem
$query = "SELECT * FROM bens WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $_GET['id']]);
$bem = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bem) {
    $_SESSION['erro'] = "Bem não encontrado!";
    header("Location: sistema_crud.php");
    exit;
}

// Função para formatar data
function formatarData($data) {
    if (empty($data) || $data == '0000-00-00') return 'Não informada';
    return date('d/m/Y', strtotime($data));
}

// Função para formatar data e hora
function formatarDataHora($dataHora) {
    if (empty($dataHora) || $dataHora == '0000-00-00 00:00:00') return 'Não informada';
    return date('d/m/Y H:i', strtotime($dataHora));
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizar Bem - Sistema Patrimonial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, var(--primary-color) 0%, #34495e 100%);
        }
        
        .card-hover {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important;
        }
        
        .info-card {
            border-left: 4px solid var(--secondary-color);
        }
        
        .status-badge {
            font-size: 0.9em;
            padding: 0.5em 1em;
        }
        
        .field-label {
            font-weight: 600;
            color: #495057;
        }
        
        .field-value {
            color: #6c757d;
        }
        
        .back-button {
            transition: all 0.3s ease;
        }
        
        .back-button:hover {
            transform: translateX(-3px);
        }
        
        .imagem-bem {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .sem-imagem {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 3rem;
            text-align: center;
            color: #6c757d;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            .card {
                border: 1px solid #000 !important;
                box-shadow: none !important;
            }
            
            .imagem-bem {
                max-width: 200px;
            }
        }
        
        @media (max-width: 768px) {
            .imagem-bem {
                max-height: 200px;
            }
        }
    </style>
</head>
<body class="bg-light">
    <!-- Header -->
    <header class="gradient-bg text-white shadow-lg no-print">
        <div class="container py-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="d-flex align-items-center">
                        <div class="bg-white bg-opacity-20 rounded-circle p-2 me-3">
                            <i class="bi bi-eye fs-4"></i>
                        </div>
                        <div>
                            <h1 class="h4 mb-1 fw-bold">Visualizar Bem Patrimonial</h1>
                            <small class="opacity-75">Detalhes completos do item</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <div class="d-flex align-items-center justify-content-md-end gap-3">
                        <a href="sistema_crud.php" class="btn btn-outline-light btn-sm back-button">
                            <i class="bi bi-arrow-left me-1"></i>Voltar à Lista
                        </a>
                        <div class="vr opacity-50 d-none d-md-block"></div>
                        <small class="d-none d-md-block">
                            <i class="bi bi-person-circle me-1"></i>
                            <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="container py-4">
        <!-- Alertas -->
        <?php if (isset($_SESSION['sucesso'])): ?>
            <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo $_SESSION['sucesso']; unset($_SESSION['sucesso']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['erro'])): ?>
            <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo $_SESSION['erro']; unset($_SESSION['erro']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Card Principal -->
                <div class="card shadow-lg border-0 card-hover">
                    <div class="card-header bg-white py-3 border-0">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                            <div>
                                <h2 class="h4 mb-1 fw-bold text-primary">
                                    <?php echo htmlspecialchars($bem['descricao']); ?>
                                </h2>
                                <p class="text-muted mb-0">Patrimônio: <?php echo htmlspecialchars($bem['numero_patrimonio']); ?></p>
                            </div>
                            <div class="mt-2 mt-md-0">
                                <span class="badge status-badge rounded-pill <?php echo $bem['status'] === 'Localizado' ? 'bg-success' : 'bg-warning'; ?>">
                                    <i class="bi <?php echo $bem['status'] === 'Localizado' ? 'bi-check-circle' : 'bi-clock'; ?> me-1"></i>
                                    <?php echo htmlspecialchars($bem['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="row g-4">
                            <!-- Imagem do Bem -->
                            <?php if (!empty($bem['imagem']) && file_exists($bem['imagem'])): ?>
                            <div class="col-12">
                                <div class="card info-card">
                                    <div class="card-header bg-light">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-image me-2"></i>Imagem do Bem
                                        </h5>
                                    </div>
                                    <div class="card-body text-center">
                                        <img src="<?php echo $bem['imagem']; ?>" 
                                             alt="Imagem do bem <?php echo htmlspecialchars($bem['descricao']); ?>" 
                                             class="imagem-bem">
                                        <div class="mt-3">
                                            <small class="text-muted">
                                                <i class="bi bi-info-circle me-1"></i>
                                                Imagem registrada para identificação do bem
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="col-12">
                                <div class="card info-card">
                                    <div class="card-header bg-light">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-image me-2"></i>Imagem do Bem
                                        </h5>
                                    </div>
                                    <div class="card-body text-center">
                                        <div class="sem-imagem">
                                            <i class="bi bi-camera fs-1 mb-3 d-block"></i>
                                            <p class="mb-0">Nenhuma imagem cadastrada</p>
                                            <small>Adicione uma imagem através da edição do bem</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Informações Básicas -->
                            <div class="col-md-6">
                                <div class="card info-card h-100">
                                    <div class="card-header bg-light">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-info-circle me-2"></i>Informações Básicas
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <span class="field-label">Número do Patrimônio:</span>
                                            <div class="field-value fs-5 fw-bold text-primary">
                                                <?php echo htmlspecialchars($bem['numero_patrimonio']); ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="field-label">Descrição:</span>
                                            <div class="field-value">
                                                <?php echo htmlspecialchars($bem['descricao']); ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="field-label">Unidade Gestora:</span>
                                            <div class="field-value">
                                                <?php echo !empty($bem['unidade_gestora']) ? htmlspecialchars($bem['unidade_gestora']) : 'Não informada'; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="field-label">Status:</span>
                                            <div class="field-value">
                                                <span class="badge <?php echo $bem['status'] === 'Localizado' ? 'bg-success' : 'bg-warning'; ?>">
                                                    <?php echo htmlspecialchars($bem['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="field-label">Situação:</span>
                                            <div class="field-value">
                                                <?php echo !empty($bem['situacao']) ? htmlspecialchars($bem['situacao']) : 'Não informada'; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Localização e Responsáveis -->
                            <div class="col-md-6">
                                <div class="card info-card h-100">
                                    <div class="card-header bg-light">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-geo-alt me-2"></i>Localização e Responsáveis
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <span class="field-label">Localidade:</span>
                                            <div class="field-value">
                                                <?php echo !empty($bem['localidade']) ? htmlspecialchars($bem['localidade']) : 'Não informada'; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="field-label">Localização Atual:</span>
                                            <div class="field-value">
                                                <?php echo !empty($bem['localizacao_atual']) ? htmlspecialchars($bem['localizacao_atual']) : 'Não informada'; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="field-label">Responsável pela Localidade:</span>
                                            <div class="field-value">
                                                <?php echo !empty($bem['responsavel_localidade']) ? htmlspecialchars($bem['responsavel_localidade']) : 'Não informado'; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="field-label">Responsável pelo Bem:</span>
                                            <div class="field-value">
                                                <?php echo !empty($bem['responsavel_bem']) ? htmlspecialchars($bem['responsavel_bem']) : 'Não informado'; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Auditoria e Vistorias -->
                            <div class="col-md-6">
                                <div class="card info-card h-100">
                                    <div class="card-header bg-light">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-clipboard-check me-2"></i>Auditoria e Vistorias
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <span class="field-label">Auditor:</span>
                                            <div class="field-value">
                                                <?php echo !empty($bem['auditor']) ? htmlspecialchars($bem['auditor']) : 'Não informado'; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="field-label">Data da Última Vistoria:</span>
                                            <div class="field-value">
                                                <?php echo formatarData($bem['data_ultima_vistoria']); ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="field-label">Data da Vistoria Atual:</span>
                                            <div class="field-value">
                                                <?php echo formatarData($bem['data_vistoria_atual']); ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="field-label">Situação do Bem:</span>
                                            <div class="field-value">
                                                <?php echo !empty($bem['situacao']) ? htmlspecialchars($bem['situacao']) : 'Não informada'; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Metadados do Sistema -->
                            <div class="col-md-6">
                                <div class="card info-card h-100">
                                    <div class="card-header bg-light">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-database me-2"></i>Metadados do Sistema
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <span class="field-label">Data de Criação:</span>
                                            <div class="field-value">
                                                <?php echo formatarDataHora($bem['data_criacao']); ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="field-label">Última Atualização:</span>
                                            <div class="field-value">
                                                <?php echo formatarDataHora($bem['data_atualizacao']); ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="field-label">ID do Registro:</span>
                                            <div class="field-value">
                                                #<?php echo $bem['id']; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="field-label">Caminho da Imagem:</span>
                                            <div class="field-value">
                                                <?php if (!empty($bem['imagem'])): ?>
                                                    <code class="small"><?php echo htmlspecialchars($bem['imagem']); ?></code>
                                                <?php else: ?>
                                                    <span class="text-muted">Nenhuma imagem cadastrada</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Observações -->
                            <?php if (!empty($bem['observacoes'])): ?>
                            <div class="col-12">
                                <div class="card info-card">
                                    <div class="card-header bg-light">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-chat-text me-2"></i>Observações
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="field-value">
                                            <?php echo nl2br(htmlspecialchars($bem['observacoes'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Footer com Ações -->
                    <div class="card-footer bg-white border-0 py-3 no-print">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                            <div class="text-muted">
                                <small>
                                    <i class="bi bi-info-circle me-1"></i>
                                    Registro #<?php echo $bem['id']; ?> • 
                                    Última atualização: <?php echo formatarDataHora($bem['data_atualizacao']); ?>
                                </small>
                            </div>
                            <div class="d-flex gap-2 flex-wrap justify-content-center">
                                <a href="sistema_crud.php?editar=<?php echo $bem['id']; ?>" 
                                   class="btn btn-primary btn-sm">
                                    <i class="bi bi-pencil me-1"></i>Editar Bem
                                </a>
                                <button onclick="copiarPatrimonio()" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-clipboard me-1"></i>Copiar Patrimônio
                                </button>
                                <a href="sistema_crud.php" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-list-ul me-1"></i>Voltar à Lista
                                </a>
                                <button onclick="window.print()" class="btn btn-outline-info btn-sm">
                                    <i class="bi bi-printer me-1"></i>Imprimir
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Adicionar confirmação antes de ações importantes
        document.addEventListener('DOMContentLoaded', function() {
            // Destacar card principal
            const mainCard = document.querySelector('.card');
            if (mainCard) {
                mainCard.style.opacity = '0';
                mainCard.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    mainCard.style.transition = 'all 0.5s ease';
                    mainCard.style.opacity = '1';
                    mainCard.style.transform = 'translateY(0)';
                }, 100);
            }
            
            // Adicionar tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
        
        // Função para copiar número do patrimônio
        function copiarPatrimonio() {
            const patrimonio = '<?php echo $bem['numero_patrimonio']; ?>';
            navigator.clipboard.writeText(patrimonio).then(function() {
                // Mostrar feedback visual
                const btn = document.querySelector('[onclick="copiarPatrimonio()"]');
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-check me-1"></i>Copiado!';
                btn.classList.remove('btn-outline-secondary');
                btn.classList.add('btn-success');
                
                setTimeout(() => {
                    btn.innerHTML = originalHTML;
                    btn.classList.remove('btn-success');
                    btn.classList.add('btn-outline-secondary');
                }, 2000);
            });
        }
        
        // Função para expandir imagem
        function expandirImagem(element) {
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><?php echo htmlspecialchars($bem['descricao']); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center">
                            <img src="<?php echo $bem['imagem']; ?>" 
                                 alt="Imagem do bem <?php echo htmlspecialchars($bem['descricao']); ?>" 
                                 class="img-fluid">
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const modalInstance = new bootstrap.Modal(modal);
            modalInstance.show();
            
            modal.addEventListener('hidden.bs.modal', function() {
                document.body.removeChild(modal);
            });
        }
    </script>
</body>
</html>