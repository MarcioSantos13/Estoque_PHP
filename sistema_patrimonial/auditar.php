<?php
// auditar.php - Sistema de Auditoria de Bens Patrimoniais Melhorado
require_once 'includes/init.php';

Auth::protegerPagina();

$database = new Database();
$db = $database->getConnection();

$mensagem = '';
$tipo_mensagem = '';
$bem_encontrado = null;
$localidades = [];

// ========== VERIFICAÇÃO DE MENSAGENS DA SESSÃO ==========
if (isset($_SESSION['auditoria_mensagem'])) {
    $mensagem = $_SESSION['auditoria_mensagem'];
    $tipo_mensagem = $_SESSION['auditoria_tipo'] ?? 'success';

    // Limpar mensagem da sessão
    unset($_SESSION['auditoria_mensagem']);
    unset($_SESSION['auditoria_tipo']);
}
// ========== FIM VERIFICAÇÃO DE MENSAGENS ==========

// ========== CARREGAR LOCALIDADES ==========
try {
    $stmt = $db->query("SELECT DISTINCT localidade FROM bens WHERE localidade IS NOT NULL AND localidade != '' ORDER BY localidade");
    $localidades = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $mensagem = "Erro ao carregar localidades: " . $e->getMessage();
    $tipo_mensagem = 'danger';
}
// ========== FIM CARREGAR LOCALIDADES ==========

// ========== CARREGAR ESTATÍSTICAS ==========
try {
    $total_bens = $db->query("SELECT COUNT(*) FROM bens")->fetchColumn();
    $bens_localizados = $db->query("SELECT COUNT(*) FROM bens WHERE status = 'Localizado'")->fetchColumn();
    $bens_pendentes = $db->query("SELECT COUNT(*) FROM bens WHERE status = 'Pendente' OR status IS NULL OR status = ''")->fetchColumn();
    $percentual_concluido = $total_bens > 0 ? round(($bens_localizados / $total_bens) * 100, 2) : 0;
} catch (Exception $e) {
    error_log("Erro ao carregar estatísticas: " . $e->getMessage());
    $total_bens = 0;
    $bens_localizados = 0;
    $bens_pendentes = 0;
    $percentual_concluido = 0;
}
// ========== FIM CARREGAR ESTATÍSTICAS ==========

// ========== FUNÇÃO FORMATAR NÚMEROS ==========
function formatarNumero($numero) {
    if ($numero === null || $numero === '') {
        return '0';
    }
    return number_format((float)$numero, 0, ',', '.');
}
// ========== FIM FUNÇÃO FORMATAR NÚMEROS ==========

// ========== FUNÇÃO PROCESSAR UPLOAD DE IMAGEM BASE64 ==========
function processarUploadImagemBase64($imagem_base64, $id_bem) {
    $diretorio_uploads = 'uploads/auditoria/';
    
    // Criar diretório se não existir
    if (!is_dir($diretorio_uploads)) {
        mkdir($diretorio_uploads, 0755, true);
    }
    
    // Validar se a string base64 não está vazia
    if (empty($imagem_base64) || $imagem_base64 === 'undefined') {
        throw new Exception('String da imagem base64 está vazia ou inválida');
    }
    
    // Extrair o tipo MIME e os dados da imagem
    if (preg_match('/^data:image\/(\w+);base64,/', $imagem_base64, $matches)) {
        $tipo = $matches[1]; // jpg, png, gif, etc.
        $dados_imagem = substr($imagem_base64, strpos($imagem_base64, ',') + 1);
    } else {
        // Se não tem prefixo, assumir que é JPEG
        $tipo = 'jpeg';
        $dados_imagem = $imagem_base64;
    }
    
    // Validar tipo de arquivo
    $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($tipo, $extensoes_permitidas)) {
        throw new Exception('Tipo de imagem não permitido. Use apenas: ' . implode(', ', $extensoes_permitidas));
    }
    
    // Decodificar base64
    $dados_binarios = base64_decode($dados_imagem);
    if ($dados_binarios === false) {
        throw new Exception('Erro ao decodificar imagem base64');
    }
    
    // Validar tamanho (máximo 5MB)
    if (strlen($dados_binarios) > 5 * 1024 * 1024) {
        throw new Exception('Imagem muito grande. Tamanho máximo: 5MB');
    }
    
    // Gerar nome único para o arquivo
    $extensao = $tipo === 'jpeg' ? 'jpg' : $tipo;
    $novo_nome = 'auditoria_' . $id_bem . '_' . time() . '.' . $extensao;
    $caminho_completo = $diretorio_uploads . $novo_nome;
    
    // Salvar arquivo
    if (!file_put_contents($caminho_completo, $dados_binarios)) {
        throw new Exception('Erro ao salvar a imagem no servidor');
    }
    
    // Verificar se o arquivo foi criado
    if (!file_exists($caminho_completo)) {
        throw new Exception('Arquivo de imagem não foi criado');
    }
    
    return $caminho_completo;
}
// ========== FIM FUNÇÃO PROCESSAR UPLOAD DE IMAGEM ==========

// ========== PROCESSAR AUDITORIA MANUAL (BUSCAR BEM) ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buscar_bem'])) {
    try {
        $numero_patrimonio = trim($_POST['numero_patrimonio'] ?? '');

        if (empty($numero_patrimonio)) {
            throw new Exception('Informe o número do patrimônio');
        }

        $stmt = $db->prepare("SELECT * FROM bens WHERE numero_patrimonio = ?");
        $stmt->execute([$numero_patrimonio]);
        $bem_encontrado = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bem_encontrado) {
            throw new Exception('Bem patrimonial não encontrado: ' . htmlspecialchars($numero_patrimonio));
        }

        // Verificar se o bem já está localizado
        if ($bem_encontrado['status'] === 'Localizado') {
            $mensagem = "ℹ️ Bem " . htmlspecialchars($bem_encontrado['numero_patrimonio']) . " já foi localizado anteriormente.";
            $tipo_mensagem = 'info';
        } else {
            $mensagem = "Bem encontrado! Confirme a auditoria abaixo.";
            $tipo_mensagem = 'success';
        }
    } catch (Exception $e) {
        $mensagem = "Erro: " . $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}
// ========== FIM PROCESSAR BUSCA MANUAL ==========

// ========== PROCESSAR CONFIRMAÇÃO DE AUDITORIA MANUAL ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_auditoria'])) {
    try {
        $id = $_POST['id'] ?? '';
        $localidade_atual = trim($_POST['localidade_atual'] ?? '');
        $nova_localidade = trim($_POST['nova_localidade'] ?? '');
        $observacoes_auditoria = trim($_POST['observacoes_auditoria'] ?? '');
        $imagem_data = $_POST['imagem_data'] ?? '';

        if (empty($id)) {
            throw new Exception('ID do bem não informado');
        }

        // Verificar se o bem existe
        $stmt = $db->prepare("SELECT * FROM bens WHERE id = ?");
        $stmt->execute([$id]);
        $bem = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bem) {
            throw new Exception('Bem não encontrado');
        }

        // Preparar dados para atualização
        $auditor = $_SESSION['usuario_nome'];

        $dados_atualizacao = [
            'status' => 'Localizado',
            'auditor' => $auditor,
            'data_vistoria_atual' => date('Y-m-d H:i:s')
        ];

        // Processar upload de imagem se fornecida
        $caminho_imagem = '';
        if (!empty($imagem_data) && $imagem_data !== 'undefined' && strpos($imagem_data, 'data:image') === 0) {
            try {
                // Excluir imagem anterior se existir
                if (!empty($bem['imagem']) && file_exists($bem['imagem'])) {
                    unlink($bem['imagem']);
                }
                
                $caminho_imagem = processarUploadImagemBase64($imagem_data, $id);
                $dados_atualizacao['imagem'] = $caminho_imagem;
            } catch (Exception $e) {
                // Log do erro, mas não interrompe a auditoria
                error_log("Erro no upload da imagem para bem {$id}: " . $e->getMessage());
                $observacoes_auditoria .= "\n[ERRO IMAGEM: " . $e->getMessage() . "]";
            }
        }

        // Verificar se a localidade foi alterada
        $localidade_alterada = false;
        if (!empty($nova_localidade) && $nova_localidade !== $localidade_atual) {
            $dados_atualizacao['localidade'] = $nova_localidade;
            $localidade_alterada = true;
        }

        // Adicionar observações se fornecidas
        if (!empty($observacoes_auditoria) || $localidade_alterada || !empty($caminho_imagem)) {
            $observacoes_atual = $bem['observacoes'] ?? '';
            $observacoes_nova = $observacoes_atual . "\n\n--- AUDITORIA " . date('d/m/Y H:i') . " ---\n" .
                "Auditor: " . $auditor . "\n" .
                "Status alterado: " . $bem['status'] . " → Localizado\n";

            if ($localidade_alterada) {
                $observacoes_nova .= "Localidade alterada: " . ($bem['localidade'] ?? 'Não informada') . " → " . $nova_localidade . "\n";
            }

            if (!empty($caminho_imagem)) {
                $observacoes_nova .= "Imagem da auditoria registrada: " . basename($caminho_imagem) . "\n";
            }

            if (!empty($observacoes_auditoria)) {
                $observacoes_nova .= "Observações: " . $observacoes_auditoria . "\n";
            }

            $dados_atualizacao['observacoes'] = $observacoes_nova;
        }

        // Construir query de atualização
        $campos = [];
        $valores = [];

        foreach ($dados_atualizacao as $campo => $valor) {
            $campos[] = "{$campo} = ?";
            $valores[] = $valor;
        }

        $valores[] = $id;

        $sql = "UPDATE bens SET " . implode(', ', $campos) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $sucesso = $stmt->execute($valores);

        if ($sucesso) {
            $msg_sucesso = "✅ Auditoria realizada com sucesso! Bem " . htmlspecialchars($bem['numero_patrimonio']) . " foi localizado.";
            if ($localidade_alterada) {
                $msg_sucesso .= " Localidade atualizada.";
            }
            if (!empty($caminho_imagem)) {
                $msg_sucesso .= " Foto registrada.";
            }

            // REDIRECIONAMENTO APÓS AUDITORIA (Padrão PRG)
            $_SESSION['auditoria_mensagem'] = $msg_sucesso;
            $_SESSION['auditoria_tipo'] = 'success';
            $_SESSION['ultimo_patrimonio'] = $bem['numero_patrimonio'];
            header('Location: auditar.php');
            exit;
        } else {
            throw new Exception('Erro ao atualizar o bem');
        }
    } catch (Exception $e) {
        $mensagem = "Erro: " . $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}
// ========== FIM PROCESSAR CONFIRMAÇÃO MANUAL ==========

// ========== PROCESSAR AUDITORIA RÁPIDA VIA AJAX ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auditoria_rapida'])) {
    try {
        $numero_patrimonio = trim($_POST['numero_patrimonio'] ?? '');
        $nova_localidade = trim($_POST['nova_localidade'] ?? '');
        $imagem_data = $_POST['imagem_data'] ?? '';

        if (empty($numero_patrimonio)) {
            throw new Exception('Informe o número do patrimônio');
        }

        $stmt = $db->prepare("SELECT * FROM bens WHERE numero_patrimonio = ?");
        $stmt->execute([$numero_patrimonio]);
        $bem = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bem) {
            throw new Exception('Bem patrimonial não encontrado: ' . htmlspecialchars($numero_patrimonio));
        }

        // Verificar se já está localizado
        if ($bem['status'] === 'Localizado') {
            echo json_encode([
                'success' => true,
                'mensagem' => 'ℹ️ Bem ' . htmlspecialchars($bem['numero_patrimonio']) . ' já foi localizado anteriormente.',
                'ja_localizado' => true
            ]);
            exit;
        }

        // Processar auditoria automática
        $auditor = $_SESSION['usuario_nome'];

        $dados_atualizacao = [
            'status' => 'Localizado',
            'auditor' => $auditor,
            'data_vistoria_atual' => date('Y-m-d H:i:s')
        ];

        // Processar upload de imagem se fornecida
        $caminho_imagem = '';
        if (!empty($imagem_data) && $imagem_data !== 'undefined' && strpos($imagem_data, 'data:image') === 0) {
            try {
                // Excluir imagem anterior se existir
                if (!empty($bem['imagem']) && file_exists($bem['imagem'])) {
                    unlink($bem['imagem']);
                }
                
                $caminho_imagem = processarUploadImagemBase64($imagem_data, $bem['id']);
                $dados_atualizacao['imagem'] = $caminho_imagem;
            } catch (Exception $e) {
                // Log do erro, mas continua sem a imagem
                error_log("Erro no upload da imagem para bem {$bem['id']}: " . $e->getMessage());
            }
        }

        // Atualizar localidade se fornecida
        $localidade_alterada = false;
        if (!empty($nova_localidade) && $nova_localidade !== $bem['localidade']) {
            $dados_atualizacao['localidade'] = $nova_localidade;
            $localidade_alterada = true;
        }

        // Adicionar observações automáticas
        $observacoes_atual = $bem['observacoes'] ?? '';
        $observacoes_nova = $observacoes_atual . "\n\n--- AUDITORIA RÁPIDA " . date('d/m/Y H:i') . " ---\n" .
            "Auditor: " . $auditor . "\n" .
            "Status alterado: " . ($bem['status'] ?? 'Pendente') . " → Localizado\n";

        if ($localidade_alterada) {
            $observacoes_nova .= "Localidade alterada: " . ($bem['localidade'] ?? 'Não informada') . " → " . $nova_localidade . "\n";
        }

        if (!empty($caminho_imagem)) {
            $observacoes_nova .= "Imagem da auditoria registrada: " . basename($caminho_imagem) . "\n";
        }

        $observacoes_nova .= "Processo: Automático";
        $dados_atualizacao['observacoes'] = $observacoes_nova;

        // Construir query de atualização
        $campos = [];
        $valores = [];

        foreach ($dados_atualizacao as $campo => $valor) {
            $campos[] = "{$campo} = ?";
            $valores[] = $valor;
        }

        $valores[] = $bem['id'];

        $sql = "UPDATE bens SET " . implode(', ', $campos) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $sucesso = $stmt->execute($valores);

        if ($sucesso) {
            $msg_sucesso = "✅ Auditoria rápida realizada! Bem " . htmlspecialchars($bem['numero_patrimonio']) . " localizado.";
            if ($localidade_alterada) {
                $msg_sucesso .= " Localidade atualizada.";
            }
            if (!empty($caminho_imagem)) {
                $msg_sucesso .= " Foto registrada.";
            }

            echo json_encode([
                'success' => true,
                'mensagem' => $msg_sucesso,
                'patrimonio' => $numero_patrimonio
            ]);
        } else {
            throw new Exception('Erro ao atualizar o bem');
        }
        exit;
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'mensagem' => $e->getMessage()
        ]);
        exit;
    }
}
// ========== FIM PROCESSAR AUDITORIA RÁPIDA ==========
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditar Bens - Sistema Patrimonial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .search-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .audit-card { border-left: 4px solid #28a745; }
        .status-badge { font-size: 0.9em; padding: 0.5em 1em; }
        .progress { height: 25px; border-radius: 12px; }
        .progress-bar { border-radius: 12px; }
        .bem-detalhes { background: #f8f9fa; border-radius: 8px; padding: 15px; }
        .campo-destaque { font-weight: bold; color: #2c3e50; }
        .btn-auditoria-rapida { background: linear-gradient(135deg, #28a745, #20c997); border: none; color: white; font-weight: bold; }
        .btn-auditoria-rapida:hover { background: linear-gradient(135deg, #218838, #1e9e8a); color: white; }
        .mensagem-sucesso { animation: fadeIn 0.5s ease-in; }
        
        /* Estilos para captura de imagem */
        .camera-container {
            position: relative;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            background: #f8f9fa;
            min-height: 200px;
        }
        .camera-preview { width: 100%; max-height: 300px; object-fit: cover; }
        .camera-overlay {
            position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            background: rgba(0, 0, 0, 0.7); color: white; text-align: center; padding: 20px;
        }
        .captured-image { max-width: 100%; max-height: 300px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2); }
        .btn-camera { margin: 5px; }
        .camera-controls {
            position: absolute; bottom: 10px; left: 0; right: 0;
            text-align: center; background: rgba(0, 0, 0, 0.7); padding: 10px;
        }

        /* Estilos para o scanner */
        #reader {
            border: 2px solid #28a745; border-radius: 8px; overflow: hidden; 
            margin-bottom: 1rem; width: 100%; display: none;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .mobile-optimized { font-size: 16px; }
            .btn-mobile { padding: 12px; font-size: 16px; }
            .camera-preview { max-height: 250px; }
            .camera-container { min-height: 180px; }
        }
    </style>
</head>
<body class="bg-light">
    <!-- Header -->
    <header class="bg-primary text-white shadow-sm">
        <div class="container py-3">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="h3 mb-1 fw-bold">
                        <i class="bi bi-clipboard-check me-2"></i>Auditoria de Bens Patrimoniais
                    </h1>
                    <p class="mb-0 opacity-75">Bem-vindo, <?php echo $_SESSION['usuario_nome']; ?>!</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="index.php" class="btn btn-light btn-sm"><i class="bi bi-house me-1"></i>Início</a>
                    <a href="sistema_crud.php" class="btn btn-outline-light btn-sm"><i class="bi bi-list-check me-1"></i>Gestão</a>
                </div>
            </div>
        </div>
    </header>

    <main class="container py-4">
        <!-- Mensagens -->
        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show mensagem-sucesso" id="alertMensagem">
                <?php echo $mensagem; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Estatísticas da Auditoria -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Progresso da Auditoria</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3 mb-3"><div class="card border-primary"><div class="card-body"><h3 class="text-primary"><?php echo formatarNumero($total_bens); ?></h3><p class="mb-0 text-muted">Total de Bens</p></div></div></div>
                    <div class="col-md-3 mb-3"><div class="card border-success"><div class="card-body"><h3 class="text-success"><?php echo formatarNumero($bens_localizados); ?></h3><p class="mb-0 text-muted">Localizados</p></div></div></div>
                    <div class="col-md-3 mb-3"><div class="card border-warning"><div class="card-body"><h3 class="text-warning"><?php echo formatarNumero($bens_pendentes); ?></h3><p class="mb-0 text-muted">Pendentes</p></div></div></div>
                    <div class="col-md-3 mb-3"><div class="card border-info"><div class="card-body"><h3 class="text-info"><?php echo number_format($percentual_concluido, 1, ',', '.'); ?>%</h3><p class="mb-0 text-muted">Concluído</p></div></div></div>
                </div>
                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Progresso da Auditoria</span>
                        <span><?php echo number_format($percentual_concluido, 1, ',', '.'); ?>%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percentual_concluido; ?>%" aria-valuenow="<?php echo $percentual_concluido; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Coluna Esquerda: Busca (SIMPLIFICADA - FOTO REMOVIDA) -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm search-card text-white">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-search me-2"></i>Buscar Bem para Auditoria</h5>
                        <p class="card-text">Digite ou escaneie o código de barras do patrimônio.</p>

                        <!-- Container para o Scanner -->
                        <div id="reader"></div>
                        <div id="scannerStatus" class="bg-dark text-white text-center p-2 rounded mb-3" style="display: none;">
                            Scanner Inativo
                        </div>

                        <form method="POST" id="formBusca">
                            
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-upc-scan me-1"></i>Número do Patrimônio</label>
                                <input type="text" class="form-control mobile-optimized" name="numero_patrimonio" id="numero_patrimonio"
                                    placeholder="Digite ou escaneie o patrimônio" required autocomplete="off"
                                    value="<?php echo htmlspecialchars($_POST['numero_patrimonio'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-geo-alt me-1"></i>Nova Localidade (Opcional)</label>
                                <select class="form-select mobile-optimized localidade-rapida" name="nova_localidade" id="nova_localidade_rapida">
                                    <option value="">Manter localidade atual</option>
                                    <?php if (!empty($localidades)): ?>
                                        <optgroup label="Localidades Cadastradas">
                                            <?php foreach ($localidades as $localidade): ?>
                                                <option value="<?php echo htmlspecialchars($localidade); ?>"><?php echo htmlspecialchars($localidade); ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                </select>
                                <div class="form-text text-white-50">
                                    <i class="bi bi-lightning me-1"></i>Se preenchido, a auditoria será automática ao escanear/digitar
                                </div>
                            </div>

                            <!-- SEÇÃO DE FOTO REMOVIDA DA BUSCA RÁPIDA -->

                            <div class="d-grid gap-2">
                                <button type="submit" name="buscar_bem" class="btn btn-light btn-lg btn-mobile">
                                    <i class="bi bi-search me-2"></i>Buscar Bem
                                </button>
                                <button type="button" id="btnAuditoriaRapida" class="btn btn-auditoria-rapida btn-lg btn-mobile">
                                    <i class="bi bi-lightning me-2"></i>Auditoria Rápida
                                </button>
                                <button type="button" id="btnIniciarScanner" class="btn btn-info btn-lg btn-mobile">
                                    <i class="bi bi-upc-scan me-2"></i>Escanear Cód. Barras
                                </button>
                            </div>
                            
                        </form>

                        <!-- ÁREA DO HISTÓRICO - INSERIDA AQUI APÓS O FECHAMENTO DO FORMULÁRIO -->
                        <div id="areaHistorico" class="mt-3" style="display:none;">
                            <h6 class="border-bottom pb-2 mb-2 text-white"><i class="bi bi-clock-history me-2"></i>Histórico da Sessão:</h6>
                            <ul class="list-group" id="listaHistorico" style="max-height: 250px; overflow-y: auto;"></ul>
                        </div>
                        
                    </div>
                </div>
            </div>

            <!-- Coluna Direita: Formulário de Auditoria -->
            <div class="col-lg-6 mb-4">
                <?php if ($bem_encontrado): ?>
                    <!-- Formulário de Auditoria -->
                    <div class="card shadow-sm audit-card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="bi bi-clipboard-check me-2"></i>Confirmar Auditoria
                                <span class="badge bg-light text-success ms-2">Manual</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- Ficha Técnica do Bem -->
                            <div class="bem-detalhes mb-4">
                                <h6 class="border-bottom pb-2 mb-3 campo-destaque"><i class="bi bi-card-checklist me-2"></i>Ficha Técnica do Bem</h6>
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <label class="form-label mb-1"><strong>Nº Patrimônio:</strong></label>
                                        <div class="form-control bg-light"><?php echo htmlspecialchars($bem_encontrado['numero_patrimonio']); ?></div>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label mb-1"><strong>Status Atual:</strong></label>
                                        <div>
                                            <span class="badge <?php echo $bem_encontrado['status'] === 'Localizado' ? 'bg-success' : 'bg-warning'; ?> status-badge">
                                                <?php echo $bem_encontrado['status']; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label mb-1"><strong>Descrição:</strong></label>
                                        <div class="form-control bg-light" style="min-height: 60px;"><?php echo htmlspecialchars($bem_encontrado['descricao'] ?? 'Não informada'); ?></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Captura de Imagem para Auditoria Manual (MANTIDA) -->
                            <div class="mb-4">
                                <label class="form-label campo-destaque"><i class="bi bi-camera me-1"></i>Foto do Bem (Opcional)</label>
                                <div class="camera-container" style="height: 250px;">
                                    <video id="cameraPreviewManual" class="camera-preview d-none"></video>
                                    <canvas id="cameraCanvasManual" class="d-none"></canvas>
                                    <img id="capturedImageManual" class="captured-image d-none">
                                    
                                    <div id="cameraOverlayManual" class="camera-overlay">
                                        <i class="bi bi-camera fs-1 mb-3"></i>
                                        <p class="mb-2">Tire uma foto do bem para registro</p>
                                        <button type="button" id="btnIniciarCameraManual" class="btn btn-light btn-sm">
                                            <i class="bi bi-camera-video me-1"></i>Iniciar Câmera
                                        </button>
                                    </div>
                                    
                                    <div id="cameraControlsManual" class="camera-controls d-none">
                                        <button type="button" id="btnCapturarManual" class="btn btn-success btn-camera">
                                            <i class="bi bi-camera-fill me-1"></i>Capturar
                                        </button>
                                        <button type="button" id="btnRecapturarManual" class="btn btn-warning btn-camera">
                                            <i class="bi bi-arrow-clockwise me-1"></i>Nova Foto
                                        </button>
                                        <button type="button" id="btnCancelarCameraManual" class="btn btn-danger btn-camera">
                                            <i class="bi bi-x-circle me-1"></i>Cancelar
                                        </button>
                                    </div>
                                </div>
                                <input type="hidden" id="imagem_data_manual" name="imagem_data">
                            </div>

                            <form method="POST" id="formAuditoria">
                                <input type="hidden" name="id" value="<?php echo $bem_encontrado['id']; ?>">
                                <input type="hidden" name="imagem_data" id="imagem_data_final">

                                <div class="mb-3">
                                    <label class="form-label campo-destaque"><i class="bi bi-geo-alt me-1"></i>Localidade Atual</label>
                                    <div class="form-control bg-light"><?php echo htmlspecialchars($bem_encontrado['localidade'] ?? 'Não informada'); ?></div>
                                    <input type="hidden" name="localidade_atual" value="<?php echo htmlspecialchars($bem_encontrado['localidade'] ?? ''); ?>">
                                </div>

                                <div class="mb-3">
                                    <label for="nova_localidade" class="form-label campo-destaque"><i class="bi bi-geo-alt-fill me-1"></i>Nova Localidade (se diferente)</label>
                                    <select class="form-select mobile-optimized" id="nova_localidade" name="nova_localidade">
                                        <option value="">Manter localidade atual</option>
                                        <?php if (!empty($localidades)): ?>
                                            <optgroup label="Localidades Cadastradas">
                                                <?php foreach ($localidades as $localidade): ?>
                                                    <?php if ($localidade !== $bem_encontrado['localidade']): ?>
                                                        <option value="<?php echo htmlspecialchars($localidade); ?>"><?php echo htmlspecialchars($localidade); ?></option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                    </select>
                                    <div class="form-text">Selecione uma localidade existente ou mantenha a atual.</div>
                                </div>

                                <div class="mb-3">
                                    <label for="outra_localidade" class="form-label">Ou digite uma nova localidade:</label>
                                    <input type="text" class="form-control mobile-optimized" id="outra_localidade" placeholder="Ex: Sala 201, Almoxarifado, etc.">
                                </div>

                                <div class="mb-3">
                                    <label for="observacoes_auditoria" class="form-label campo-destaque"><i class="bi bi-chat-text me-1"></i>Observações da Auditoria</label>
                                    <textarea class="form-control mobile-optimized" id="observacoes_auditoria" name="observacoes_auditoria" rows="2" placeholder="Observações sobre o estado do bem..."></textarea>
                                </div>

                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle me-2"></i>
                                    <strong>Pronto para confirmar!</strong> O status será alterado para <strong>"Localizado"</strong>.
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" name="confirmar_auditoria" class="btn btn-success btn-lg btn-mobile">
                                        <i class="bi bi-check-circle me-2"></i>Confirmar como Localizado
                                    </button>
                                    <button type="button" id="btnNovaBusca" class="btn btn-outline-primary">
                                        <i class="bi bi-arrow-repeat me-2"></i>Nova Busca
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Placeholder quando não há busca -->
                    <div class="card shadow-sm h-100">
                        <div class="card-body text-center d-flex align-items-center justify-content-center" style="min-height: 300px;">
                            <div>
                                <i class="bi bi-upc-scan display-1 text-muted mb-3"></i>
                                <h5 class="text-muted">Aguardando leitura</h5>
                                <p class="text-muted">Digite ou escaneie o número do patrimônio para iniciar a auditoria.</p>
                                <div class="mt-3">
                                    <span class="badge bg-info text-dark"><i class="bi bi-lightning me-1"></i>Modo Rápido Disponível</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/minified/html5-qrcode.min.js"></script>
    <script>
        // ========== VARIÁVEIS GLOBAIS ==========
        let cameraStream = null;
        let currentCameraType = 'manual'; // Agora só temos câmera manual
        let html5QrCode = null;
        let isScannerActive = false;
        // ========== FIM VARIÁVEIS GLOBAIS ==========

        // ========== FUNÇÕES DA CÂMERA (APENAS MANUAL) ==========
        async function iniciarCamera(tipo) {
            try {
                currentCameraType = tipo;
                const videoElement = document.getElementById(`cameraPreviewManual`);
                const overlayElement = document.getElementById(`cameraOverlayManual`);
                const controlsElement = document.getElementById(`cameraControlsManual`);

                // Parar câmera anterior se existir
                if (cameraStream) {
                    pararCamera();
                }

                cameraStream = await navigator.mediaDevices.getUserMedia({ 
                    video: { facingMode: 'environment' }, audio: false 
                });

                videoElement.srcObject = cameraStream;
                videoElement.classList.remove('d-none');
                overlayElement.classList.add('d-none');
                controlsElement.classList.remove('d-none');
                await videoElement.play();
                
            } catch (error) {
                console.error('Erro ao acessar a câmera:', error);
                let mensagemErro = 'Não foi possível acessar a câmera. ';
                if (error.name === 'NotAllowedError') {
                    mensagemErro += 'Permissão negada.';
                } else if (error.name === 'NotFoundError') {
                    mensagemErro += 'Nenhuma câmera encontrada.';
                } else {
                    mensagemErro += error.message;
                }
                alert(mensagemErro);
                
                const overlayElement = document.getElementById(`cameraOverlayManual`);
                const controlsElement = document.getElementById(`cameraControlsManual`);
                overlayElement.classList.remove('d-none');
                controlsElement.classList.add('d-none');
            }
        }

        function capturarFoto(tipo) {
            try {
                const videoElement = document.getElementById(`cameraPreviewManual`);
                const canvasElement = document.getElementById(`cameraCanvasManual`);
                const imageElement = document.getElementById(`capturedImageManual`);
                const imagemDataInput = document.getElementById('imagem_data_final');

                if (videoElement.readyState !== videoElement.HAVE_ENOUGH_DATA) {
                    alert('A câmera ainda não está pronta.');
                    return;
                }

                canvasElement.width = videoElement.videoWidth;
                canvasElement.height = videoElement.videoHeight;
                const context = canvasElement.getContext('2d');
                context.drawImage(videoElement, 0, 0, canvasElement.width, canvasElement.height);
                
                const imageData = canvasElement.toDataURL('image/jpeg', 0.8);
                imageElement.src = imageData;
                imageElement.classList.remove('d-none');
                videoElement.classList.add('d-none');
                imagemDataInput.value = imageData;
                
                pararCamera();
                
            } catch (error) {
                console.error('Erro ao capturar foto:', error);
                alert('Erro ao capturar a foto.');
            }
        }

        function recapturarFoto(tipo) {
            const imageElement = document.getElementById(`capturedImageManual`);
            const imagemDataInput = document.getElementById('imagem_data_final');
            
            imageElement.classList.add('d-none');
            imagemDataInput.value = '';
            iniciarCamera('manual');
        }

        function cancelarCamera(tipo) {
            const videoElement = document.getElementById(`cameraPreviewManual`);
            const imageElement = document.getElementById(`capturedImageManual`);
            const overlayElement = document.getElementById(`cameraOverlayManual`);
            const controlsElement = document.getElementById(`cameraControlsManual`);
            const imagemDataInput = document.getElementById('imagem_data_final');
            
            pararCamera();
            videoElement.classList.add('d-none');
            imageElement.classList.add('d-none');
            controlsElement.classList.add('d-none');
            overlayElement.classList.remove('d-none');
            imagemDataInput.value = '';
        }

        function pararCamera() {
            if (cameraStream) {
                cameraStream.getTracks().forEach(track => track.stop());
                cameraStream = null;
            }
        }
        // ========== FIM FUNÇÕES DA CÂMERA ==========

        // ========== FUNÇÕES DO SCANNER ==========
        function iniciarScanner() {
            const scannerContainer = document.getElementById('reader');
            const scannerStatus = document.getElementById('scannerStatus');
            const btnIniciarScanner = document.getElementById('btnIniciarScanner');
            
            if (!scannerContainer) return;

            if (isScannerActive) {
                pararScanner();
                return;
            }

            if (!html5QrCode) {
                html5QrCode = new Html5Qrcode("reader");
            }

            const config = {
                fps: 10,
                qrbox: { width: 250, height: 150 },
                formatsToSupport: [
                    Html5QrcodeSupportedFormats.CODE_128,
                    Html5QrcodeSupportedFormats.CODE_39,
                    Html5QrcodeSupportedFormats.EAN_13,
                    Html5QrcodeSupportedFormats.UPC_A,
                    Html5QrcodeSupportedFormats.UPC_E,
                    Html5QrcodeSupportedFormats.QR_CODE
                ]
            };

            html5QrCode.start({ facingMode: "environment" }, config, onScanSuccess, onScanError)
            .then(() => {
                isScannerActive = true;
                scannerContainer.style.display = 'block';
                scannerStatus.style.display = 'block';
                scannerStatus.textContent = 'Scanner Ativo. Aponte para o código.';
                scannerStatus.className = 'bg-success text-white text-center p-2 rounded mb-3';
                btnIniciarScanner.innerHTML = '<i class="bi bi-camera-video-off me-2"></i>Parar Scanner';
                btnIniciarScanner.classList.remove('btn-info');
                btnIniciarScanner.classList.add('btn-danger');
                
                if (cameraStream) {
                    cancelarCamera(currentCameraType);
                }
            }).catch((err) => {
                console.error('Erro ao iniciar scanner:', err);
                let errorMsg = 'Não foi possível iniciar o scanner. ';
                if (err.message.includes('NotAllowedError')) {
                    errorMsg += 'Permissão de câmera negada.';
                } else if (err.message.includes('NotFoundError')) {
                    errorMsg += 'Nenhuma câmera encontrada.';
                } else {
                    errorMsg += err.message;
                }
                scannerStatus.textContent = errorMsg;
                scannerStatus.className = 'bg-danger text-white text-center p-2 rounded mb-3';
                scannerStatus.style.display = 'block';
                isScannerActive = false;
                btnIniciarScanner.innerHTML = '<i class="bi bi-upc-scan me-2"></i>Escanear Cód. Barras';
                btnIniciarScanner.classList.remove('btn-danger');
                btnIniciarScanner.classList.add('btn-info');
            });
        }

        function pararScanner() {
            if (html5QrCode && isScannerActive) {
                html5QrCode.stop().then(() => {
                    isScannerActive = false;
                    const scannerContainer = document.getElementById('reader');
                    const scannerStatus = document.getElementById('scannerStatus');
                    const btnIniciarScanner = document.getElementById('btnIniciarScanner');
                    
                    scannerContainer.style.display = 'none';
                    scannerStatus.textContent = 'Scanner Inativo';
                    scannerStatus.className = 'bg-dark text-white text-center p-2 rounded mb-3';
                    btnIniciarScanner.innerHTML = '<i class="bi bi-upc-scan me-2"></i>Escanear Cód. Barras';
                    btnIniciarScanner.classList.remove('btn-danger');
                    btnIniciarScanner.classList.add('btn-info');
                }).catch((err) => {
                    console.error("Erro ao parar scanner:", err);
                    isScannerActive = false;
                });
            }
        }

        function onScanSuccess(decodedText, decodedResult) {
            document.getElementById('numero_patrimonio').value = decodedText;
            pararScanner();
            const novaLocalidade = document.getElementById('nova_localidade_rapida').value;
            if (novaLocalidade) {
                executarAuditoriaRapida();
            } else {
                document.getElementById('numero_patrimonio').focus();
                document.getElementById('numero_patrimonio').select();
            }
        }

        function onScanError(errorMessage) {
            // Ignorar erros normais
        }
        // ========== FIM FUNÇÕES DO SCANNER ==========

        // ========== FUNÇÃO AUDITORIA RÁPIDA (SEM IMAGEM) ==========
        function executarAuditoriaRapida() {
            const numeroPatrimonio = document.getElementById('numero_patrimonio').value.trim();
            const novaLocalidade = document.getElementById('nova_localidade_rapida').value;

            if (!numeroPatrimonio) {
                alert('Informe o número do patrimônio');
                document.getElementById('numero_patrimonio').focus();
                return;
            }

            const btnAuditoriaRapida = document.getElementById('btnAuditoriaRapida');
            const btnOriginal = btnAuditoriaRapida.innerHTML;
            btnAuditoriaRapida.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Processando...';
            btnAuditoriaRapida.disabled = true;

            const formData = new FormData();
            formData.append('auditoria_rapida', 'true');
            formData.append('numero_patrimonio', numeroPatrimonio);
            formData.append('nova_localidade', novaLocalidade);
            // Não envia mais imagem_data na auditoria rápida

            fetch('auditar.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) throw new Error('Erro na rede');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    mostrarMensagemSucesso(data.mensagem);
                    if (!data.ja_localizado) {
                        limparEProximo();
                    } else {
                        setTimeout(() => {
                            document.getElementById('numero_patrimonio').focus();
                            document.getElementById('numero_patrimonio').select();
                        }, 300);
                    }
                } else {
                    alert('Erro: ' + data.mensagem);
                    document.getElementById('numero_patrimonio').focus();
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro na comunicação com o servidor');
            })
            .finally(() => {
                btnAuditoriaRapida.innerHTML = btnOriginal;
                btnAuditoriaRapida.disabled = false;
            });
        }

        function mostrarMensagemSucesso(mensagem) {
            let alertDiv = document.getElementById('alertMensagem');
            if (!alertDiv) {
                alertDiv = document.createElement('div');
                alertDiv.id = 'alertMensagem';
                alertDiv.className = 'alert alert-success alert-dismissible fade show mensagem-sucesso';
                document.querySelector('main').insertBefore(alertDiv, document.querySelector('main').firstChild);
            }
            alertDiv.innerHTML = `${mensagem}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
            window.scrollTo(0, 0);
        }

        function limparEProximo() {
            document.getElementById('numero_patrimonio').value = '';
            document.getElementById('nova_localidade_rapida').value = '';
            setTimeout(() => document.getElementById('numero_patrimonio').focus(), 500);
        }
        // ========== FIM FUNÇÃO AUDITORIA RÁPIDA ==========

        // ========== INICIALIZAÇÃO ==========
        document.addEventListener('DOMContentLoaded', function() {
            const campoPatrimonio = document.getElementById('numero_patrimonio');
            if (campoPatrimonio) {
                campoPatrimonio.focus();
                campoPatrimonio.select();
            }

            // Event Listeners para Câmera Manual (mantida)
            document.getElementById('btnIniciarCameraManual').addEventListener('click', () => iniciarCamera('manual'));
            document.getElementById('btnCapturarManual').addEventListener('click', () => capturarFoto('manual'));
            document.getElementById('btnRecapturarManual').addEventListener('click', () => recapturarFoto('manual'));
            document.getElementById('btnCancelarCameraManual').addEventListener('click', () => cancelarCamera('manual'));

            // CORREÇÃO PRINCIPAL: Event Listener para Auditoria Rápida - SEM preventDefault
            document.getElementById('btnAuditoriaRapida').addEventListener('click', function(e) {
                // REMOVIDO: e.preventDefault() que estava causando o problema no celular
                executarAuditoriaRapida();
            });

            // Event Listener para Scanner
            document.getElementById('btnIniciarScanner').addEventListener('click', function() {
                iniciarScanner();
            });

            // Event Listener para Enter no campo de patrimônio
            if (campoPatrimonio) {
                campoPatrimonio.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        executarAuditoriaRapida();
                    }
                });
            }

            // Event Listener para Nova Busca
            document.getElementById('btnNovaBusca').addEventListener('click', function() {
                if (campoPatrimonio) {
                    campoPatrimonio.value = '';
                    campoPatrimonio.focus();
                }
                cancelarCamera('manual');
                window.scrollTo(0, 0);
            });

            // Integração entre select e input de localidade
            const selectLocalidade = document.getElementById('nova_localidade');
            const inputOutraLocalidade = document.getElementById('outra_localidade');
            
            if (selectLocalidade && inputOutraLocalidade) {
                selectLocalidade.addEventListener('change', function() {
                    if (this.value === '') {
                        inputOutraLocalidade.value = '';
                    }
                });
                
                inputOutraLocalidade.addEventListener('input', function() {
                    if (this.value.trim() !== '') {
                        selectLocalidade.value = '';
                    }
                });
            }

            // Integração similar para a busca rápida
            const selectLocalidadeRapida = document.getElementById('nova_localidade_rapida');
            if (selectLocalidadeRapida) {
                selectLocalidadeRapida.addEventListener('change', function() {
                    // Se selecionou uma localidade, foca no botão de auditoria rápida
                    if (this.value !== '' && campoPatrimonio.value.trim() !== '') {
                        document.getElementById('btnAuditoriaRapida').focus();
                    }
                });
            }

            window.addEventListener('beforeunload', function() {
                pararCamera();
                pararScanner();
            });
        });
        // ========== FIM INICIALIZAÇÃO ==========
    </script>


<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

<script>
    // ========== CONFIGURAÇÕES GLOBAIS ==========
    let html5QrcodeScanner = null;
    
    // Função para tocar som de sucesso (BIP)
    function tocarBip() {
        // Tenta criar contexto de áudio
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return; // Navegador antigo sem suporte

        const audioContext = new AudioContext();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.type = "sine";
        oscillator.frequency.value = 1200; // Tom agudo
        gainNode.gain.value = 0.1; // Volume baixo
        
        oscillator.start();
        setTimeout(() => oscillator.stop(), 100); // Duração curta (100ms)
    }

    // Função para adicionar item ao histórico visual
    function adicionarAoHistorico(texto, tipo = 'success') {
        const areaHistorico = document.getElementById('areaHistorico');
        const lista = document.getElementById('listaHistorico');
        
        if (areaHistorico) areaHistorico.style.display = 'block';
        if (!lista) return;

        const item = document.createElement('li');
        const hora = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        
        let classeCor = 'list-group-item-success';
        let icone = 'bi-check-circle-fill';

        if (tipo === 'warning') {
            classeCor = 'list-group-item-warning';
            icone = 'bi-exclamation-circle-fill';
        } else if (tipo === 'danger') {
            classeCor = 'list-group-item-danger';
            icone = 'bi-x-circle-fill';
        }

        item.className = `list-group-item ${classeCor} d-flex justify-content-between align-items-center animate__animated animate__fadeIn`;
        item.innerHTML = `
            <div><i class="bi ${icone} me-2"></i>${texto}</div>
            <span class="badge bg-white text-dark rounded-pill" style="opacity: 0.7">${hora}</span>
        `;
        
        // Adiciona no topo da lista
        lista.insertBefore(item, lista.firstChild);
    }

    // ========== INICIALIZAÇÃO ==========
    document.addEventListener('DOMContentLoaded', function() {
        
        const btnScanner = document.getElementById('btnIniciarScanner');
        const inputPatrimonio = document.getElementById('numero_patrimonio');
        const readerDiv = document.getElementById('reader');
        const statusDiv = document.getElementById('scannerStatus');
        const selectLocalidade = document.getElementById('nova_localidade_rapida');

        // Listener do Botão do Scanner
        if (btnScanner) {
            btnScanner.addEventListener('click', function() {
                if (html5QrcodeScanner) {
                    pararScanner();
                } else {
                    iniciarScanner();
                }
            });
        }

        function iniciarScanner() {
            readerDiv.style.display = 'block';
            statusDiv.style.display = 'block';
            statusDiv.className = "bg-dark text-white text-center p-2 rounded mb-3";
            statusDiv.innerText = "Aguardando código...";
            
            btnScanner.innerHTML = '<i class="bi bi-stop-circle me-2"></i>Parar Scanner';
            btnScanner.classList.remove('btn-info');
            btnScanner.classList.add('btn-danger');

            html5QrcodeScanner = new Html5Qrcode("reader");

            const config = { 
                fps: 10, 
                qrbox: { width: 250, height: 250 },
                aspectRatio: 1.0 
            };

            html5QrcodeScanner.start(
                { facingMode: "environment" }, 
                config,
                onScanSuccess,
                onScanFailure
            ).catch(err => {
                console.error("Erro", err);
                statusDiv.innerText = "Erro: Câmera bloqueada ou HTTPS ausente.";
            });
        }

        function pararScanner() {
            if (html5QrcodeScanner) {
                html5QrcodeScanner.stop().then(() => {
                    html5QrcodeScanner.clear();
                    html5QrcodeScanner = null;
                    readerDiv.style.display = 'none';
                    statusDiv.style.display = 'none';
                    btnScanner.innerHTML = '<i class="bi bi-upc-scan me-2"></i>Escanear Cód. Barras';
                    btnScanner.classList.remove('btn-danger');
                    btnScanner.classList.add('btn-info');
                });
            }
        }

        function onScanSuccess(decodedText, decodedResult) {
            // 1. Toca Bip
            tocarBip();
            
            // 2. Pausa Scanner (para não ler o mesmo código 10 vezes)
            html5QrcodeScanner.pause();

            // 3. Preenche input
            inputPatrimonio.value = decodedText;
            
            // 4. Pega a localidade (mesmo se estiver VAZIA)
            const localidade = selectLocalidade.value;

            // 5. Executa auditoria SEMPRE (não importa se tem localidade ou não)
            realizarAuditoriaRapida(decodedText, localidade);
        }

        function onScanFailure(error) {
            // Ignora erros de frame
        }

        // ========== FUNÇÃO AJAX PRINCIPAL ==========
        function realizarAuditoriaRapida(patrimonio, localidade) {
            const formData = new FormData();
            formData.append('auditoria_rapida', '1');
            formData.append('numero_patrimonio', patrimonio);
            formData.append('nova_localidade', localidade); // Se for vazio, o PHP ignora e usa a atual

            statusDiv.className = "bg-warning text-dark text-center p-2 rounded mb-3";
            statusDiv.innerText = "Auditando " + patrimonio + "...";

            fetch('auditar.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Limpa o input imediatamente para evitar confusão
                inputPatrimonio.value = '';

                if (data.success) {
                    // Sucesso ou Aviso de já auditado
                    let tipoMsg = 'success';
                    let textoMsg = `Bem ${patrimonio} localizado!`;

                    if (data.ja_localizado) {
                        tipoMsg = 'warning'; // Amarelo
                        textoMsg = `Bem ${patrimonio} já auditado anteriormente.`;
                    }

                    statusDiv.className = `bg-${tipoMsg === 'success' ? 'success' : 'info'} text-white text-center p-2 rounded mb-3`;
                    statusDiv.innerText = data.mensagem;
                    
                    // Adiciona na lista visual
                    adicionarAoHistorico(textoMsg, tipoMsg);

                } else {
                    // Erro
                    statusDiv.className = "bg-danger text-white text-center p-2 rounded mb-3";
                    statusDiv.innerText = "ERRO: " + data.mensagem;
                    adicionarAoHistorico(`Erro ${patrimonio}: ${data.mensagem}`, 'danger');
                    // Som de erro (grave)
                    // ... (opcional)
                }

                // RETOMA O SCANNER APÓS 1.5 SEGUNDOS
                // Isso dá tempo de tirar a câmera da frente da etiqueta
                setTimeout(() => {
                    statusDiv.className = "bg-dark text-white text-center p-2 rounded mb-3";
                    statusDiv.innerText = "Pronto para o próximo...";
                    if(html5QrcodeScanner) {
                        html5QrcodeScanner.resume();
                    }
                }, 1500);
            })
            .catch(error => {
                console.error('Erro:', error);
                statusDiv.innerText = "Erro de conexão com o servidor.";
                adicionarAoHistorico("Falha de conexão", 'danger');
                
                // Tenta retomar mesmo com erro
                setTimeout(() => { if(html5QrcodeScanner) html5QrcodeScanner.resume(); }, 2000);
            });
        }
        
        // Botão Manual "Auditoria Rápida" (Digitando o código)
        const btnRapidaManual = document.getElementById('btnAuditoriaRapida');
        if(btnRapidaManual) {
            btnRapidaManual.addEventListener('click', function() {
                const pat = inputPatrimonio.value;
                const loc = selectLocalidade.value;
                if(!pat) { alert('Digite o número do patrimônio'); return; }
                
                // Aqui também chama direto, mesmo sem localidade
                realizarAuditoriaRapida(pat, loc);
            });
        }
    });
</script>



</body>
</html>