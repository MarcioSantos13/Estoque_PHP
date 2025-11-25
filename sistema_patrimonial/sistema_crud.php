<?php
// sistema_crud.php - Sistema CRUD para gerenciamento de bens patrimoniais
require_once 'includes/init.php';

Auth::protegerPagina();

$database = new Database();
$db = $database->getConnection();

// =============================================================================
// CONFIGURAÇÕES INICIAIS
// =============================================================================
$itens_por_pagina = isset($_GET['itens_por_pagina']) ? (int)$_GET['itens_por_pagina'] : 20;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Parâmetros de pesquisa
$pesquisa_patrimonio = isset($_GET['patrimonio']) ? trim($_GET['patrimonio']) : '';
$pesquisa_descricao = isset($_GET['descricao']) ? trim($_GET['descricao']) : '';
$pesquisa_localidade = isset($_GET['localidade']) ? trim($_GET['localidade']) : '';
$pesquisa_responsavel = isset($_GET['responsavel']) ? trim($_GET['responsavel']) : '';

$pesquisa_ativa = !empty($pesquisa_patrimonio) || !empty($pesquisa_descricao) || !empty($pesquisa_localidade) || !empty($pesquisa_responsavel);

// =============================================================================
// PROCESSAMENTO DE FORMULÁRIOS
// =============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['adicionar_bem'])) {
        adicionarBem($db);
    } 
    elseif (isset($_POST['editar_bem'])) {
        editarBem($db);
    } 
    elseif (isset($_POST['excluir_bem'])) {
        excluirBem($db);
    }
    elseif (isset($_POST['remover_imagem'])) {
        removerImagem($db);
    }
    elseif (isset($_POST['registrar_auditoria'])) {
        registrarAuditoria($db);
    }
}

// =============================================================================
// FUNÇÕES DO SISTEMA
// =============================================================================

function registrarAuditoria($db) {
    if (!isset($_POST['bem_id']) || empty($_POST['bem_id'])) {
        $_SESSION['erro'] = "ID do bem não especificado.";
        return;
    }

    try {
        $observacao = "Auditor: " . ($_POST['auditor'] ?? 'Sistema') . "\n";
        $observacao .= "Status alterado: " . ($_POST['status_anterior'] ?? 'N/A') . " → " . ($_POST['novo_status'] ?? 'N/A') . "\n";
        
        if (!empty($_POST['observacao_auditoria'])) {
            $observacao .= "Observações: " . $_POST['observacao_auditoria'] . "\n";
        }
        
        if (isset($_FILES['imagem_auditoria']) && $_FILES['imagem_auditoria']['error'] === UPLOAD_ERR_OK) {
            $imagem_path = processarUploadImagemAuditoria($_POST['bem_id']);
            if ($imagem_path) {
                $observacao .= "Imagem da auditoria registrada: " . basename($imagem_path) . "\n";
                
                // Atualizar imagem principal do bem
                $query_update_img = "UPDATE bens SET imagem = :imagem WHERE id = :id";
                $stmt_update_img = $db->prepare($query_update_img);
                $stmt_update_img->execute([
                    ':imagem' => $imagem_path,
                    ':id' => $_POST['bem_id']
                ]);
            }
        }
        
        $observacao .= "Data: " . date('d/m/Y H:i:s') . "\n";
        $observacao .= "---\n";
        
        // Buscar observações atuais
        $query_select = "SELECT observacoes FROM bens WHERE id = :id";
        $stmt_select = $db->prepare($query_select);
        $stmt_select->execute([':id' => $_POST['bem_id']]);
        $bem = $stmt_select->fetch(PDO::FETCH_ASSOC);
        
        $novas_observacoes = $observacao . ($bem['observacoes'] ?? '');
        
        // Atualizar observações
        $query_update = "UPDATE bens SET 
                        observacoes = :observacoes,
                        status = :status,
                        auditor = :auditor,
                        data_vistoria_atual = CURRENT_DATE,
                        data_atualizacao = CURRENT_TIMESTAMP
                        WHERE id = :id";
        
        $stmt_update = $db->prepare($query_update);
        $stmt_update->execute([
            ':observacoes' => $novas_observacoes,
            ':status' => $_POST['novo_status'] ?? 'Localizado',
            ':auditor' => $_POST['auditor'] ?? $_SESSION['usuario_nome'],
            ':id' => $_POST['bem_id']
        ]);
        
        $_SESSION['sucesso'] = "Auditoria registrada com sucesso!";
        
    } catch (PDOException $e) {
        $_SESSION['erro'] = "Erro ao registrar auditoria: " . $e->getMessage();
    }
}

function processarUploadImagemAuditoria($bem_id) {
    if (!isset($_FILES['imagem_auditoria']) || $_FILES['imagem_auditoria']['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $arquivo = $_FILES['imagem_auditoria'];
    
    // Verificar tipo de arquivo
    $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extensao, $extensoes_permitidas)) {
        return null;
    }

    // Verificar tamanho
    if ($arquivo['size'] > 5 * 1024 * 1024) {
        return null;
    }

    // Verificar se é imagem válida
    if (!getimagesize($arquivo['tmp_name'])) {
        return null;
    }

    // Criar diretório se não existir
    $diretorio_uploads = 'uploads/auditorias/';
    if (!is_dir($diretorio_uploads)) {
        mkdir($diretorio_uploads, 0755, true);
    }

    // Gerar nome único para o arquivo
    $nome_arquivo = 'auditoria_' . $bem_id . '_' . time() . '.' . $extensao;
    $caminho_completo = $diretorio_uploads . $nome_arquivo;

    // Mover arquivo
    if (move_uploaded_file($arquivo['tmp_name'], $caminho_completo)) {
        return $caminho_completo;
    }
    
    return null;
}

function processarUploadImagem($bem_id = null) {
    if (!isset($_FILES['imagem_upload']) || $_FILES['imagem_upload']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $arquivo = $_FILES['imagem_upload'];
    
    // Verificar erro no upload
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['erro'] = "Erro no upload da imagem.";
        return null;
    }

    // Verificar tipo de arquivo
    $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extensao, $extensoes_permitidas)) {
        $_SESSION['erro'] = "Tipo de arquivo não permitido. Use: JPG, PNG, GIF ou WEBP.";
        return null;
    }

    // Verificar tamanho
    if ($arquivo['size'] > 5 * 1024 * 1024) {
        $_SESSION['erro'] = "Arquivo muito grande. Tamanho máximo: 5MB";
        return null;
    }

    // Verificar se é imagem válida
    if (!getimagesize($arquivo['tmp_name'])) {
        $_SESSION['erro'] = "O arquivo não é uma imagem válida.";
        return null;
    }

    // Criar diretório se não existir
    $diretorio_uploads = 'uploads/';
    if (!is_dir($diretorio_uploads)) {
        mkdir($diretorio_uploads, 0755, true);
    }

    // Gerar nome único para o arquivo
    $nome_arquivo = uniqid() . '_' . time() . '.' . $extensao;
    $caminho_completo = $diretorio_uploads . $nome_arquivo;

    // Mover arquivo
    if (move_uploaded_file($arquivo['tmp_name'], $caminho_completo)) {
        return $caminho_completo;
    } else {
        $_SESSION['erro'] = "Erro ao salvar imagem.";
        return null;
    }
}

function removerImagem($db) {
    if (isset($_POST['bem_id']) && !empty($_POST['bem_id'])) {
        try {
            // Buscar imagem atual
            $query = "SELECT imagem FROM bens WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute([':id' => $_POST['bem_id']]);
            $bem = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Remover arquivo físico
            if ($bem && !empty($bem['imagem']) && file_exists($bem['imagem'])) {
                unlink($bem['imagem']);
            }
            
            // Atualizar banco
            $query = "UPDATE bens SET imagem = NULL WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute([':id' => $_POST['bem_id']]);
            
            $_SESSION['sucesso'] = "Imagem removida com sucesso!";
            
        } catch (PDOException $e) {
            $_SESSION['erro'] = "Erro ao remover imagem: " . $e->getMessage();
        }
    }
    
    header("Location: sistema_crud.php?" . http_build_query($_GET));
    exit;
}

function adicionarBem($db) {
    try {
        // Processar upload de imagem
        $imagem_path = processarUploadImagem();
        
        $query = "INSERT INTO bens 
                 (unidade_gestora, localidade, responsavel_localidade, numero_patrimonio, 
                  descricao, observacoes, status, situacao, responsavel_bem, auditor, 
                  data_ultima_vistoria, data_vistoria_atual, imagem, localizacao_atual) 
                 VALUES 
                 (:unidade_gestora, :localidade, :responsavel_localidade, :numero_patrimonio,
                  :descricao, :observacoes, :status, :situacao, :responsavel_bem, :auditor,
                  :data_ultima_vistoria, :data_vistoria_atual, :imagem, :localizacao_atual)";
        
        $stmt = $db->prepare($query);
        
        $observacoes_iniciais = "Bem cadastrado em: " . date('d/m/Y H:i:s') . "\n";
        $observacoes_iniciais .= "Por: " . ($_SESSION['usuario_nome'] ?? 'Sistema') . "\n";
        $observacoes_iniciais .= "---\n";
        
        $stmt->execute([
            ':unidade_gestora' => $_POST['unidade_gestora'] ?? '',
            ':localidade' => $_POST['localidade'] ?? '',
            ':responsavel_localidade' => $_POST['responsavel_localidade'] ?? '',
            ':numero_patrimonio' => $_POST['numero_patrimonio'] ?? '',
            ':descricao' => $_POST['descricao'] ?? '',
            ':observacoes' => $observacoes_iniciais,
            ':status' => $_POST['status'] ?? 'Pendente',
            ':situacao' => $_POST['situacao'] ?? '',
            ':responsavel_bem' => $_POST['responsavel_bem'] ?? '',
            ':auditor' => $_POST['auditor'] ?? '',
            ':data_ultima_vistoria' => !empty($_POST['data_ultima_vistoria']) ? $_POST['data_ultima_vistoria'] : null,
            ':data_vistoria_atual' => !empty($_POST['data_vistoria_atual']) ? $_POST['data_vistoria_atual'] : null,
            ':imagem' => $imagem_path,
            ':localizacao_atual' => $_POST['localizacao_atual'] ?? ''
        ]);
        
        $_SESSION['sucesso'] = "Bem cadastrado com sucesso!";
        
    } catch (PDOException $e) {
        $_SESSION['erro'] = "Erro ao cadastrar bem: " . $e->getMessage();
    }
    
    header("Location: sistema_crud.php");
    exit;
}

function editarBem($db) {
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        $_SESSION['erro'] = "ID do bem não especificado.";
        header("Location: sistema_crud.php");
        exit;
    }

    try {
        // Verificar se há nova imagem
        $imagem_path = null;
        if (isset($_FILES['imagem_upload']) && $_FILES['imagem_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
            $imagem_path = processarUploadImagem($_POST['id']);
            
            // Se upload foi bem sucedido, remover imagem antiga
            if ($imagem_path) {
                $query_select = "SELECT imagem FROM bens WHERE id = :id";
                $stmt_select = $db->prepare($query_select);
                $stmt_select->execute([':id' => $_POST['id']]);
                $bem_antigo = $stmt_select->fetch(PDO::FETCH_ASSOC);
                
                if ($bem_antigo && !empty($bem_antigo['imagem']) && file_exists($bem_antigo['imagem'])) {
                    unlink($bem_antigo['imagem']);
                }
            }
        }

        // Montar query dinamicamente
        if ($imagem_path) {
            $query = "UPDATE bens SET 
                     unidade_gestora = :unidade_gestora,
                     localidade = :localidade,
                     responsavel_localidade = :responsavel_localidade,
                     numero_patrimonio = :numero_patrimonio,
                     descricao = :descricao,
                     observacoes = :observacoes,
                     status = :status,
                     situacao = :situacao,
                     responsavel_bem = :responsavel_bem,
                     auditor = :auditor,
                     data_ultima_vistoria = :data_ultima_vistoria,
                     data_vistoria_atual = :data_vistoria_atual,
                     imagem = :imagem,
                     localizacao_atual = :localizacao_atual,
                     data_atualizacao = CURRENT_TIMESTAMP
                     WHERE id = :id";
            
            $params = [
                ':imagem' => $imagem_path
            ];
        } else {
            $query = "UPDATE bens SET 
                     unidade_gestora = :unidade_gestora,
                     localidade = :localidade,
                     responsavel_localidade = :responsavel_localidade,
                     numero_patrimonio = :numero_patrimonio,
                     descricao = :descricao,
                     observacoes = :observacoes,
                     status = :status,
                     situacao = :situacao,
                     responsavel_bem = :responsavel_bem,
                     auditor = :auditor,
                     data_ultima_vistoria = :data_ultima_vistoria,
                     data_vistoria_atual = :data_vistoria_atual,
                     localizacao_atual = :localizacao_atual,
                     data_atualizacao = CURRENT_TIMESTAMP
                     WHERE id = :id";
        }

        // Parâmetros comuns
        $params = array_merge($params ?? [], [
            ':id' => $_POST['id'],
            ':unidade_gestora' => $_POST['unidade_gestora'] ?? '',
            ':localidade' => $_POST['localidade'] ?? '',
            ':responsavel_localidade' => $_POST['responsavel_localidade'] ?? '',
            ':numero_patrimonio' => $_POST['numero_patrimonio'] ?? '',
            ':descricao' => $_POST['descricao'] ?? '',
            ':observacoes' => $_POST['observacoes'] ?? '',
            ':status' => $_POST['status'] ?? 'Pendente',
            ':situacao' => $_POST['situacao'] ?? '',
            ':responsavel_bem' => $_POST['responsavel_bem'] ?? '',
            ':auditor' => $_POST['auditor'] ?? '',
            ':data_ultima_vistoria' => !empty($_POST['data_ultima_vistoria']) ? $_POST['data_ultima_vistoria'] : null,
            ':data_vistoria_atual' => !empty($_POST['data_vistoria_atual']) ? $_POST['data_vistoria_atual'] : null,
            ':localizacao_atual' => $_POST['localizacao_atual'] ?? ''
        ]);

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        $_SESSION['sucesso'] = "Bem atualizado com sucesso!";
        
    } catch (PDOException $e) {
        $_SESSION['erro'] = "Erro ao atualizar bem: " . $e->getMessage();
    }
    
    header("Location: sistema_crud.php");
    exit;
}

function excluirBem($db) {
    try {
        // Buscar e remover imagem
        $query_select = "SELECT imagem FROM bens WHERE id = :id";
        $stmt_select = $db->prepare($query_select);
        $stmt_select->execute([':id' => $_POST['id']]);
        $bem = $stmt_select->fetch(PDO::FETCH_ASSOC);
        
        if ($bem && !empty($bem['imagem']) && file_exists($bem['imagem'])) {
            unlink($bem['imagem']);
        }
        
        // Excluir bem
        $query = "DELETE FROM bens WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute([':id' => $_POST['id']]);
        
        $_SESSION['sucesso'] = "Bem excluído com sucesso!";
        
    } catch (PDOException $e) {
        $_SESSION['erro'] = "Erro ao excluir bem: " . $e->getMessage();
    }
    
    header("Location: sistema_crud.php");
    exit;
}

// =============================================================================
// CONSULTAS AO BANCO DE DADOS
// =============================================================================

// Construir query base com filtros
$query_bens_base = "FROM bens WHERE 1=1";
$params = [];

if (!empty($pesquisa_patrimonio)) {
    $query_bens_base .= " AND numero_patrimonio LIKE :patrimonio";
    $params[':patrimonio'] = '%' . $pesquisa_patrimonio . '%';
}

if (!empty($pesquisa_descricao)) {
    $query_bens_base .= " AND descricao LIKE :descricao";
    $params[':descricao'] = '%' . $pesquisa_descricao . '%';
}

if (!empty($pesquisa_localidade)) {
    $query_bens_base .= " AND localidade LIKE :localidade";
    $params[':localidade'] = '%' . $pesquisa_localidade . '%';
}

if (!empty($pesquisa_responsavel)) {
    $query_bens_base .= " AND (responsavel_bem LIKE :responsavel OR responsavel_localidade LIKE :responsavel)";
    $params[':responsavel'] = '%' . $pesquisa_responsavel . '%';
}

// Buscar bens com paginação
$query_bens = "SELECT * " . $query_bens_base . " ORDER BY id DESC LIMIT :limit OFFSET :offset";
$stmt_bens = $db->prepare($query_bens);

foreach ($params as $key => $value) {
    $stmt_bens->bindValue($key, $value, PDO::PARAM_STR);
}

$stmt_bens->bindValue(':limit', $itens_por_pagina, PDO::PARAM_INT);
$stmt_bens->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt_bens->execute();
$bens = $stmt_bens->fetchAll(PDO::FETCH_ASSOC);

// Contar total de bens
$query_total = "SELECT COUNT(*) as total " . $query_bens_base;
$stmt_total = $db->prepare($query_total);

foreach ($params as $key => $value) {
    $stmt_total->bindValue($key, $value, PDO::PARAM_STR);
}

$stmt_total->execute();
$total_bens = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_bens / $itens_por_pagina);

// Buscar bem para edição
$bem_edicao = null;
if (isset($_GET['editar'])) {
    $query_editar = "SELECT * FROM bens WHERE id = :id";
    $stmt_editar = $db->prepare($query_editar);
    $stmt_editar->execute([':id' => $_GET['editar']]);
    $bem_edicao = $stmt_editar->fetch(PDO::FETCH_ASSOC);
}

// Buscar bem para auditoria
$bem_auditoria = null;
if (isset($_GET['auditoria'])) {
    $query_auditoria = "SELECT * FROM bens WHERE id = :id";
    $stmt_auditoria = $db->prepare($query_auditoria);
    $stmt_auditoria->execute([':id' => $_GET['auditoria']]);
    $bem_auditoria = $stmt_auditoria->fetch(PDO::FETCH_ASSOC);
}

// Buscar estatísticas
$query_stats = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Localizado' THEN 1 ELSE 0 END) as localizados,
    SUM(CASE WHEN status = 'Pendente' THEN 1 ELSE 0 END) as pendentes
    FROM bens";
$stats = $db->query($query_stats)->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Bens - Sistema Patrimonial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light: #ecf0f1;
            --dark: #2c3e50;
        }
        
        .bg-gradient-primary {
            background: linear-gradient(135deg, var(--primary) 0%, #34495e 100%);
        }
        
        .card-dashboard {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .card-dashboard:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0;
        }
        
        .btn-modern {
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .table-modern {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .table-modern thead th {
            background: var(--primary);
            color: white;
            border: none;
            padding: 15px;
            font-weight: 600;
        }
        
        .table-modern tbody tr {
            transition: background-color 0.3s ease;
        }
        
        .table-modern tbody tr:hover {
            background-color: rgba(52, 152, 219, 0.05);
        }
        
        .badge-status {
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: nowrap;
        }
        
        .action-buttons .btn {
            border-radius: 8px;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }
        
        .action-buttons .btn:hover {
            transform: scale(1.1);
        }
        
        .upload-area {
            border: 3px dashed #dee2e6;
            border-radius: 15px;
            padding: 3rem;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .upload-area:hover {
            border-color: var(--secondary);
            background: #e3f2fd;
        }
        
        .preview-imagem {
            max-width: 200px;
            max-height: 150px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .form-control-modern {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control-modern:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        
        .nav-tabs-modern .nav-link {
            border: none;
            border-radius: 10px 10px 0 0;
            padding: 15px 25px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .nav-tabs-modern .nav-link.active {
            background: var(--primary);
            color: white;
        }
        
        .search-highlight {
            background: linear-gradient(120deg, #ffeb3b 0%, #ffeb3b 100%);
            background-repeat: no-repeat;
            background-size: 100% 40%;
            background-position: 0 90%;
        }
        
        .observacoes-box {
            max-height: 300px;
            overflow-y: auto;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            font-family: monospace;
            white-space: pre-wrap;
            font-size: 0.9em;
        }
        
        @media (max-width: 768px) {
            .stat-number {
                font-size: 2rem;
            }
            
            .card-dashboard {
                margin-bottom: 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-buttons .btn {
                margin: 2px 0;
            }
        }
    </style>
</head>
<body class="bg-light">
    <!-- Header -->
    <nav class="navbar navbar-dark bg-gradient-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">
                <i class="bi bi-building-gear me-2"></i>
                Sistema Patrimonial
            </a>
            <div class="d-flex align-items-center">
                <span class="text-light me-3">
                    <i class="bi bi-person-circle me-2"></i>
                    <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?>
                </span>
                <a href="index.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Voltar
                </a>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        <!-- Header da Página -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 fw-bold text-dark mb-1">Gestão de Bens Patrimoniais</h1>
                        <p class="text-muted mb-0">Controle completo do patrimônio institucional</p>
                    </div>
                    <button class="btn btn-success btn-modern" onclick="toggleForm()">
                        <i class="bi bi-plus-circle me-2"></i>Novo Bem
                    </button>
                </div>
            </div>
        </div>

        <!-- Cards de Estatísticas -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card card-dashboard border-start border-primary border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h3 class="stat-number text-primary"><?php echo number_format($stats['total'], 0, ',', '.'); ?></h3>
                                <p class="text-muted mb-0">Total de Bens</p>
                            </div>
                            <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-database text-primary fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card card-dashboard border-start border-success border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h3 class="stat-number text-success"><?php echo number_format($stats['localizados'], 0, ',', '.'); ?></h3>
                                <p class="text-muted mb-0">Bens Localizados</p>
                            </div>
                            <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-check-circle text-success fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card card-dashboard border-start border-warning border-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h3 class="stat-number text-warning"><?php echo number_format($stats['pendentes'], 0, ',', '.'); ?></h3>
                                <p class="text-muted mb-0">Bens Pendentes</p>
                            </div>
                            <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                                <i class="bi bi-clock text-warning fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alertas -->
        <?php if (isset($_SESSION['sucesso'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo $_SESSION['sucesso']; unset($_SESSION['sucesso']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['erro'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo $_SESSION['erro']; unset($_SESSION['erro']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Modal de Auditoria -->
        <?php if ($bem_auditoria): ?>
        <div class="modal fade show" style="display: block; background: rgba(0,0,0,0.5);" id="modalAuditoria">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-clipboard-check me-2"></i>
                            Registrar Auditoria - <?php echo htmlspecialchars($bem_auditoria['numero_patrimonio']); ?>
                        </h5>
                        <a href="sistema_crud.php" class="btn-close btn-close-white"></a>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="modal-body">
                            <input type="hidden" name="bem_id" value="<?php echo $bem_auditoria['id']; ?>">
                            <input type="hidden" name="status_anterior" value="<?php echo $bem_auditoria['status']; ?>">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Status Atual</label>
                                    <p class="form-control-plaintext fw-bold"><?php echo $bem_auditoria['status']; ?></p>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Novo Status *</label>
                                    <select class="form-select form-control-modern" name="novo_status" required>
                                        <option value="Localizado" <?php echo $bem_auditoria['status'] === 'Localizado' ? 'selected' : ''; ?>>Localizado</option>
                                        <option value="Pendente" <?php echo $bem_auditoria['status'] === 'Pendente' ? 'selected' : ''; ?>>Pendente</option>
                                    </select>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Auditor *</label>
                                    <input type="text" class="form-control form-control-modern" name="auditor" 
                                           value="<?php echo htmlspecialchars($_SESSION['usuario_nome']); ?>" required>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Imagem da Auditoria</label>
                                    <input type="file" class="form-control form-control-modern" name="imagem_auditoria" accept="image/*">
                                    <small class="text-muted">Opcional - Imagem comprobatória da vistoria</small>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Observações</label>
                                    <textarea class="form-control form-control-modern" name="observacao_auditoria" rows="3" 
                                              placeholder="Descreva as observações da auditoria..."></textarea>
                                </div>
                                
                                <?php if (!empty($bem_auditoria['observacoes'])): ?>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">Histórico Anterior</label>
                                    <div class="observacoes-box">
                                        <?php echo htmlspecialchars($bem_auditoria['observacoes']); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="sistema_crud.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" name="registrar_auditoria" class="btn btn-primary">
                                <i class="bi bi-check-circle me-2"></i>Registrar Auditoria
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filtros e Pesquisa -->
        <div class="card card-dashboard mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0">
                    <i class="bi bi-funnel me-2"></i>Filtros e Pesquisa
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Nº Patrimônio</label>
                        <input type="text" class="form-control form-control-modern" name="patrimonio" 
                               value="<?php echo htmlspecialchars($pesquisa_patrimonio); ?>" 
                               placeholder="Digite o número...">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Descrição</label>
                        <input type="text" class="form-control form-control-modern" name="descricao" 
                               value="<?php echo htmlspecialchars($pesquisa_descricao); ?>" 
                               placeholder="Buscar por descrição...">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Localidade</label>
                        <input type="text" class="form-control form-control-modern" name="localidade" 
                               value="<?php echo htmlspecialchars($pesquisa_localidade); ?>" 
                               placeholder="Localização...">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Responsável</label>
                        <input type="text" class="form-control form-control-modern" name="responsavel" 
                               value="<?php echo htmlspecialchars($pesquisa_responsavel); ?>" 
                               placeholder="Nome do responsável...">
                    </div>
                    
                    <div class="col-12">
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-primary btn-modern">
                                <i class="bi bi-search me-2"></i>Aplicar Filtros
                            </button>
                            
                            <?php if ($pesquisa_ativa): ?>
                                <a href="sistema_crud.php" class="btn btn-outline-secondary btn-modern">
                                    <i class="bi bi-x-circle me-2"></i>Limpar Todos
                                </a>
                            <?php endif; ?>
                            
                            <div class="ms-auto d-flex align-items-center gap-2">
                                <label class="form-label mb-0 fw-semibold">Itens por página:</label>
                                <select class="form-select form-control-modern" name="itens_por_pagina" onchange="this.form.submit()" style="width: auto;">
                                    <option value="10" <?php echo $itens_por_pagina == 10 ? 'selected' : ''; ?>>10</option>
                                    <option value="20" <?php echo $itens_por_pagina == 20 ? 'selected' : ''; ?>>20</option>
                                    <option value="50" <?php echo $itens_por_pagina == 50 ? 'selected' : ''; ?>>50</option>
                                    <option value="100" <?php echo $itens_por_pagina == 100 ? 'selected' : ''; ?>>100</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </form>

                <?php if ($pesquisa_ativa): ?>
                    <div class="mt-3 p-3 bg-light rounded">
                        <div class="d-flex align-items-center flex-wrap">
                            <i class="bi bi-funnel-fill text-primary me-2"></i>
                            <strong class="me-2">Filtros ativos:</strong>
                            <div class="d-flex flex-wrap gap-2">
                                <?php
                                $filtros = [];
                                if (!empty($pesquisa_patrimonio)) {
                                    $filtros[] = "<span class='badge bg-primary'>Patrimônio: \"{$pesquisa_patrimonio}\"</span>";
                                }
                                if (!empty($pesquisa_descricao)) {
                                    $filtros[] = "<span class='badge bg-secondary'>Descrição: \"{$pesquisa_descricao}\"</span>";
                                }
                                if (!empty($pesquisa_localidade)) {
                                    $filtros[] = "<span class='badge bg-info text-dark'>Localidade: \"{$pesquisa_localidade}\"</span>";
                                }
                                if (!empty($pesquisa_responsavel)) {
                                    $filtros[] = "<span class='badge bg-warning text-dark'>Responsável: \"{$pesquisa_responsavel}\"</span>";
                                }
                                echo implode(' ', $filtros);
                                ?>
                                <span class="badge bg-success"><?php echo number_format($total_bens, 0, ',', '.'); ?> resultado(s)</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Formulário de Cadastro/Edição -->
        <div class="card card-dashboard mb-4" id="formSection">
            <div class="card-header bg-white py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <h5 class="card-title mb-0">
                        <i class="bi <?php echo $bem_edicao ? 'bi-pencil-square' : 'bi-plus-circle'; ?> me-2"></i>
                        <?php echo $bem_edicao ? 'Editar Bem Patrimonial' : 'Cadastrar Novo Bem'; ?>
                    </h5>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleForm()">
                        <i class="bi bi-chevron-up"></i>
                    </button>
                </div>
            </div>
            
            <div class="card-body" id="formCollapse">
                <form method="POST" enctype="multipart/form-data" class="row g-4">
                    <?php if ($bem_edicao): ?>
                        <input type="hidden" name="id" value="<?php echo $bem_edicao['id']; ?>">
                    <?php endif; ?>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Número do Patrimônio *</label>
                        <input type="text" class="form-control form-control-modern" name="numero_patrimonio" 
                               value="<?php echo $bem_edicao['numero_patrimonio'] ?? ''; ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Descrição *</label>
                        <input type="text" class="form-control form-control-modern" name="descricao" 
                               value="<?php echo $bem_edicao['descricao'] ?? ''; ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Unidade Gestora</label>
                        <input type="text" class="form-control form-control-modern" name="unidade_gestora" 
                               value="<?php echo $bem_edicao['unidade_gestora'] ?? ''; ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Localidade</label>
                        <input type="text" class="form-control form-control-modern" name="localidade" 
                               value="<?php echo $bem_edicao['localidade'] ?? ''; ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Responsável Localidade</label>
                        <input type="text" class="form-control form-control-modern" name="responsavel_localidade" 
                               value="<?php echo $bem_edicao['responsavel_localidade'] ?? ''; ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Status</label>
                        <select class="form-select form-control-modern" name="status">
                            <option value="Pendente" <?php echo ($bem_edicao['status'] ?? '') === 'Pendente' ? 'selected' : ''; ?>>Pendente</option>
                            <option value="Localizado" <?php echo ($bem_edicao['status'] ?? '') === 'Localizado' ? 'selected' : ''; ?>>Localizado</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Situação</label>
                        <input type="text" class="form-control form-control-modern" name="situacao" 
                               value="<?php echo $bem_edicao['situacao'] ?? ''; ?>" 
                               placeholder="Ex: Em uso, Em manutenção...">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Responsável pelo Bem</label>
                        <input type="text" class="form-control form-control-modern" name="responsavel_bem" 
                               value="<?php echo $bem_edicao['responsavel_bem'] ?? ''; ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Data Última Vistoria</label>
                        <input type="date" class="form-control form-control-modern" name="data_ultima_vistoria" 
                               value="<?php echo $bem_edicao['data_ultima_vistoria'] ?? ''; ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Data Vistoria Atual</label>
                        <input type="date" class="form-control form-control-modern" name="data_vistoria_atual" 
                               value="<?php echo $bem_edicao['data_vistoria_atual'] ?? ''; ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Localização Atual</label>
                        <input type="text" class="form-control form-control-modern" name="localizacao_atual" 
                               value="<?php echo $bem_edicao['localizacao_atual'] ?? ''; ?>">
                    </div>

                    <!-- Upload de Imagem -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">Imagem do Bem</label>
                        <div class="upload-area" onclick="document.getElementById('imagem_upload').click()">
                            <i class="bi bi-cloud-arrow-up fs-1 text-muted"></i>
                            <h6 class="mt-2">Clique ou arraste uma imagem aqui</h6>
                            <p class="text-muted mb-0">Formatos: JPG, PNG, GIF, WEBP (Máx. 5MB)</p>
                            
                            <div id="previewContainer" class="mt-3 <?php echo (!empty($bem_edicao['imagem'])) ? '' : 'd-none'; ?>">
                                <?php if (!empty($bem_edicao['imagem']) && file_exists($bem_edicao['imagem'])): ?>
                                    <img src="<?php echo $bem_edicao['imagem']; ?>" alt="Preview" class="preview-imagem">
                                    <?php if ($bem_edicao): ?>
                                        <div class="mt-2">
                                            <button type="submit" name="remover_imagem" class="btn btn-outline-danger btn-sm">
                                                <i class="bi bi-trash me-1"></i>Remover Imagem
                                            </button>
                                            <input type="hidden" name="bem_id" value="<?php echo $bem_edicao['id']; ?>">
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <input type="file" id="imagem_upload" name="imagem_upload" accept="image/*" class="d-none" onchange="previewImagem(this)">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Observações</label>
                        <textarea class="form-control form-control-modern" name="observacoes" rows="4" placeholder="Observações iniciais sobre o bem..."><?php echo $bem_edicao['observacoes'] ?? ''; ?></textarea>
                        <small class="text-muted">As observações de auditoria serão adicionadas automaticamente.</small>
                    </div>

                    <div class="col-12">
                        <div class="d-flex gap-2">
                            <?php if ($bem_edicao): ?>
                                <button type="submit" name="editar_bem" class="btn btn-primary btn-modern">
                                    <i class="bi bi-check-circle me-2"></i>Atualizar Bem
                                </button>
                                <a href="sistema_crud.php" class="btn btn-secondary btn-modern">
                                    <i class="bi bi-x-circle me-2"></i>Cancelar
                                </a>
                            <?php else: ?>
                                <button type="submit" name="adicionar_bem" class="btn btn-success btn-modern">
                                    <i class="bi bi-plus-circle me-2"></i>Cadastrar Bem
                                </button>
                                <button type="reset" class="btn btn-outline-secondary btn-modern">
                                    <i class="bi bi-arrow-clockwise me-2"></i>Limpar
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de Bens -->
        <div class="card card-dashboard">
            <div class="card-header bg-white py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-list-check me-2"></i>Lista de Bens
                        <?php if ($pesquisa_ativa): ?>
                            <span class="badge bg-primary ms-2">Filtrado</span>
                        <?php endif; ?>
                    </h5>
                    <span class="badge bg-primary fs-6"><?php echo number_format($total_bens, 0, ',', '.'); ?> itens</span>
                </div>
            </div>
            
            <div class="card-body p-0">
                <?php if (count($bens) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-modern table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Patrimônio</th>
                                    <th>Descrição</th>
                                    <th class="d-none d-md-table-cell">Localidade</th>
                                    <th>Status</th>
                                    <th class="d-none d-lg-table-cell">Situação</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bens as $bem): ?>
                                    <tr class="<?php echo $pesquisa_ativa ? 'search-highlight' : ''; ?>">
                                        <td class="fw-bold">
                                            <?php echo highlightSearchTerm(htmlspecialchars($bem['numero_patrimonio']), $pesquisa_patrimonio); ?>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="fw-semibold">
                                                    <?php echo highlightSearchTerm(htmlspecialchars($bem['descricao']), $pesquisa_descricao); ?>
                                                </span>
                                                <small class="text-muted d-md-none">
                                                    <?php echo highlightSearchTerm(htmlspecialchars($bem['localidade']), $pesquisa_localidade); ?>
                                                </small>
                                                <small class="text-muted d-lg-none">
                                                    <strong>Situação:</strong> 
                                                    <?php echo !empty($bem['situacao']) ? htmlspecialchars($bem['situacao']) : 'Não informada'; ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td class="d-none d-md-table-cell">
                                            <?php echo highlightSearchTerm(htmlspecialchars($bem['localidade']), $pesquisa_localidade); ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-status <?php echo $bem['status'] === 'Localizado' ? 'bg-success' : 'bg-warning'; ?>">
                                                <?php echo htmlspecialchars($bem['status']); ?>
                                            </span>
                                        </td>
                                        <td class="d-none d-lg-table-cell">
                                            <small class="text-muted">
                                                <?php echo !empty($bem['situacao']) ? htmlspecialchars($bem['situacao']) : 'Não informada'; ?>
                                            </small>
                                        </td>
                                        <td class="action-buttons">
                                            <a href="visualizar_bem.php?id=<?php echo $bem['id']; ?>" 
                                               class="btn btn-outline-info btn-sm" title="Visualizar">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="sistema_crud.php?auditoria=<?php echo $bem['id']; ?>" 
                                               class="btn btn-outline-warning btn-sm" title="Registrar Auditoria">
                                                <i class="bi bi-clipboard-check"></i>
                                            </a>
                                            <a href="sistema_crud.php?editar=<?php echo $bem['id']; ?>&<?php echo http_build_query($_GET); ?>" 
                                               class="btn btn-outline-primary btn-sm" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Tem certeza que deseja excluir o bem <?php echo addslashes($bem['numero_patrimonio']); ?>?');">
                                                <input type="hidden" name="id" value="<?php echo $bem['id']; ?>">
                                                <button type="submit" name="excluir_bem" 
                                                        class="btn btn-outline-danger btn-sm" title="Excluir">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginação -->
                    <?php if ($total_paginas > 1): ?>
                        <div class="card-footer bg-white">
                            <nav aria-label="Navegação de páginas">
                                <ul class="pagination justify-content-center mb-0">
                                    <?php if ($pagina_atual > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="sistema_crud.php?<?php echo buildPaginationQuery($pagina_atual - 1, $_GET); ?>">
                                                <i class="bi bi-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                        <?php if ($i == 1 || $i == $total_paginas || ($i >= $pagina_atual - 2 && $i <= $pagina_atual + 2)): ?>
                                            <li class="page-item <?php echo $i == $pagina_atual ? 'active' : ''; ?>">
                                                <a class="page-link" href="sistema_crud.php?<?php echo buildPaginationQuery($i, $_GET); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php elseif ($i == $pagina_atual - 3 || $i == $pagina_atual + 3): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                        <?php endif; ?>
                                    <?php endfor; ?>

                                    <?php if ($pagina_atual < $total_paginas): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="sistema_crud.php?<?php echo buildPaginationQuery($pagina_atual + 1, $_GET); ?>">
                                                <i class="bi bi-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                                <div class="text-center mt-2">
                                    <small class="text-muted">
                                        Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?>
                                        - <?php echo number_format($total_bens, 0, ',', '.'); ?> itens no total
                                    </small>
                                </div>
                            </nav>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                        <h5 class="text-muted">
                            <?php echo $pesquisa_ativa ? 'Nenhum resultado encontrado' : 'Nenhum bem cadastrado'; ?>
                        </h5>
                        <p class="text-muted mb-4">
                            <?php echo $pesquisa_ativa ? 'Tente ajustar os termos da pesquisa.' : 'Comece cadastrando o primeiro bem patrimonial.'; ?>
                        </p>
                        <?php if ($pesquisa_ativa): ?>
                            <a href="sistema_crud.php" class="btn btn-outline-primary btn-modern">
                                <i class="bi bi-arrow-clockwise me-2"></i>Limpar Pesquisa
                            </a>
                        <?php else: ?>
                            <button class="btn btn-primary btn-modern" onclick="toggleForm()">
                                <i class="bi bi-plus-circle me-2"></i>Cadastrar Primeiro Bem
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleForm() {
            const formCollapse = document.getElementById('formCollapse');
            const formSection = document.getElementById('formSection');
            
            if (formCollapse.style.display === 'none') {
                formCollapse.style.display = 'block';
                formSection.scrollIntoView({ behavior: 'smooth' });
            } else {
                formCollapse.style.display = 'none';
            }
        }

        function previewImagem(input) {
            const previewContainer = document.getElementById('previewContainer');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    let previewImg = previewContainer.querySelector('img');
                    if (!previewImg) {
                        previewImg = document.createElement('img');
                        previewImg.className = 'preview-imagem';
                        previewContainer.innerHTML = '';
                        previewContainer.appendChild(previewImg);
                    }
                    previewImg.src = e.target.result;
                    previewContainer.classList.remove('d-none');
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Drag and drop para upload
        document.addEventListener('DOMContentLoaded', function() {
            const uploadArea = document.querySelector('.upload-area');
            const fileInput = document.getElementById('imagem_upload');
            
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                uploadArea.style.borderColor = '#3498db';
                uploadArea.style.background = '#e3f2fd';
            });
            
            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                uploadArea.style.borderColor = '#dee2e6';
                uploadArea.style.background = '#f8f9fa';
            });
            
            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                uploadArea.style.borderColor = '#dee2e6';
                uploadArea.style.background = '#f8f9fa';
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    previewImagem(fileInput);
                }
            });
            
            <?php if (!$bem_edicao && !$bem_auditoria): ?>
                // Ocultar formulário inicialmente se não estiver editando
                document.getElementById('formCollapse').style.display = 'none';
            <?php endif; ?>
        });
    </script>
</body>
</html>

<?php
// Funções auxiliares
function buildPaginationQuery($pagina, $get_params) {
    $params = $get_params;
    $params['pagina'] = $pagina;
    return http_build_query($params);
}

function highlightSearchTerm($text, $searchTerm) {
    if (empty($searchTerm) || empty(trim($searchTerm))) {
        return $text;
    }
    
    $pattern = '/' . preg_quote($searchTerm, '/') . '/i';
    $replacement = '<mark class="search-term-highlight">$0</mark>';
    
    return preg_replace($pattern, $replacement, $text);
}