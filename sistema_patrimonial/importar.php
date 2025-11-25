<?php
/**
 * importar.php
 * Importação otimizada de bens patrimoniais via arquivo CSV.
 * 
 * - Detecta automaticamente o separador (, ; ou tab)
 * - Detecta e converte a codificação para UTF-8
 * - Ignora linhas em branco
 * - Opção de ignorar ou sobrescrever registros duplicados
 */

// ===== DEBUG TEMPORÁRIO =====
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once 'includes/init.php';
Auth::protegerPagina();

// Impede acesso de usuários não administradores
if (!Auth::isAdmin()) {
    header("Location: index.php");
    exit;
}

// Função para formatar datas da importação
function formatarDataImportacao($data) {
    if (empty($data) || $data == '0000-00-00') {
        return null;
    }
    
    // Tenta converter de vários formatos
    $timestamp = strtotime($data);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }
    
    return null;
}

// Variáveis de feedback
$mensagem = '';
$tipo_mensagem = '';
$dados_importacao = null;
$acao_duplicados = isset($_POST['acao_duplicados']) ? $_POST['acao_duplicados'] : 'ignorar';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo_excel'])) {
    try {
        // Aumenta limites temporariamente para grandes arquivos
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 600);

        // Verifica se houve erro no upload
        if ($_FILES['arquivo_excel']['error'] !== UPLOAD_ERR_OK) {
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => 'Arquivo muito grande (limite do servidor)',
                UPLOAD_ERR_FORM_SIZE => 'Arquivo muito grande (limite do formulário)',
                UPLOAD_ERR_PARTIAL => 'Upload parcialmente feito',
                UPLOAD_ERR_NO_FILE => 'Nenhum arquivo enviado',
                UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada',
                UPLOAD_ERR_CANT_WRITE => 'Erro ao gravar arquivo no disco',
                UPLOAD_ERR_EXTENSION => 'Extensão PHP interrompeu o upload'
            ];
            $error_msg = $upload_errors[$_FILES['arquivo_excel']['error']] ?? 'Erro desconhecido';
            throw new Exception('Erro no upload do arquivo: ' . $error_msg);
        }

        // Verifica extensão (somente .csv)
        $extensao = strtolower(pathinfo($_FILES['arquivo_excel']['name'], PATHINFO_EXTENSION));
        if ($extensao !== 'csv') {
            throw new Exception('Formato inválido. Envie um arquivo .csv');
        }

        $arquivo_temp = $_FILES['arquivo_excel']['tmp_name'];
        $arquivo_nome = $_FILES['arquivo_excel']['name'];
        $acao_duplicados = $_POST['acao_duplicados'] ?? 'ignorar';

        if (!file_exists($arquivo_temp) || !is_uploaded_file($arquivo_temp)) {
            throw new Exception('Arquivo não encontrado ou inválido.');
        }

        /**
         * 1️⃣ DETECTA CODIFICAÇÃO E CONVERTE PARA UTF-8
         */
        $amostra_bytes = file_get_contents($arquivo_temp, false, null, 0, 4096);
        $encoding_detectado = mb_detect_encoding($amostra_bytes, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
        
        // Cria arquivo temporário convertido
        $arquivo_convertido = tempnam(sys_get_temp_dir(), 'csv_convert_');
        
        if ($encoding_detectado && $encoding_detectado !== 'UTF-8') {
            $conteudo = file_get_contents($arquivo_temp);
            $conteudo_utf8 = mb_convert_encoding($conteudo, 'UTF-8', $encoding_detectado);
            file_put_contents($arquivo_convertido, $conteudo_utf8);
        } else {
            // Se já for UTF-8 ou não detectado, copia o arquivo original
            copy($arquivo_temp, $arquivo_convertido);
        }

        /**
         * 2️⃣ DETECTA AUTOMATICAMENTE O SEPARADOR
         */
        $amostra = file_get_contents($arquivo_convertido, false, null, 0, 2048);
        $delimitadores = [
            ","  => substr_count($amostra, ","),
            ";"  => substr_count($amostra, ";"),
            "\t" => substr_count($amostra, "\t")
        ];
        
        // Remove delimitadores com contagem zero
        $delimitadores = array_filter($delimitadores);
        
        if (empty($delimitadores)) {
            throw new Exception('Não foi possível detectar o delimitador do CSV.');
        }
        
        arsort($delimitadores);
        $delimitador = array_key_first($delimitadores);

        /**
         * 3️⃣ ABRE O ARQUIVO CSV E LÊ O CABEÇALHO
         */
        $handle = fopen($arquivo_convertido, "r");
        if (!$handle) {
            throw new Exception('Não foi possível abrir o arquivo CSV.');
        }

        // Lê BOM (Byte Order Mark) se existir
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            // Não é BOM UTF-8, volta para início
            rewind($handle);
        }

        $cabecalho = fgetcsv($handle, 0, $delimitador);
        if ($cabecalho === false || empty(array_filter($cabecalho))) {
            fclose($handle);
            unlink($arquivo_convertido);
            throw new Exception('Arquivo CSV vazio ou formato incorreto.');
        }

        /**
         * 4️⃣ PREPARA VARIÁVEIS DE CONTROLE E CONEXÃO COM BANCO
         */
        $linhas_dados = 0;
        $importados = 0;
        $atualizados = 0;
        $erros = 0;
        $ignorados = 0;
        $logs = [];

        $database = new Database();
        $db = $database->getConnection();

        /**
         * 5️⃣ LOOP PRINCIPAL: LÊ CADA LINHA DO CSV
         */
        while (($linha = fgetcsv($handle, 0, $delimitador)) !== false) {
            $linhas_dados++;

            // Ignora linhas totalmente vazias
            if (empty(array_filter($linha, function($v) { 
                return trim($v) !== ''; 
            }))) {
                continue;
            }

            // Garante o número mínimo de colunas
            $linha = array_pad($linha, count($cabecalho), '');

            // ===== CORREÇÃO: MAPEAMENTO CORRETO DOS CAMPOS =====
            // Baseado na estrutura do CSV: 11 colunas
            $unidade_gestora = trim($linha[0] ?? 'CEAD');
            $localidade = trim($linha[1] ?? '');
            $responsavel_localidade = trim($linha[2] ?? '');
            $numero_patrimonio = trim($linha[3] ?? '');
            $descricao = trim($linha[4] ?? 'Item importado');
            $observacoes = trim($linha[5] ?? '');
            $status_csv = trim($linha[6] ?? '');
            $responsavel_bem = trim($linha[7] ?? '');
            $auditor = trim($linha[8] ?? '');
            $data_ultima_vistoria = trim($linha[9] ?? '');
            $data_vistoria_atual = trim($linha[10] ?? '');
            $imagem = trim($linha[11] ?? ''); // Campo adicional se existir

            // ===== CORREÇÃO: TRATAMENTO DO STATUS =====
            // Na primeira importação, definir como "Pendente" se não especificado
            if (empty($status_csv)) {
                $status = 'Pendente';
            } else {
                // Converte status do CSV para o formato do banco
                $status_csv = strtolower($status_csv);
                if (strpos($status_csv, 'localizado') !== false) {
                    $status = 'Localizado';
                } else {
                    $status = 'Pendente';
                }
            }

            // ===== CORREÇÃO: FORMATAÇÃO DE DATAS =====
            // Usa a função formatarDataImportacao() que agora está fora do loop
            $data_ultima_vistoria_db = formatarDataImportacao($data_ultima_vistoria);
            $data_vistoria_atual_db = formatarDataImportacao($data_vistoria_atual);

            // Valida campo obrigatório
            if (empty($numero_patrimonio)) {
                $erros++;
                $logs[] = "Linha " . ($linhas_dados + 1) . ": número de patrimônio vazio";
                continue;
            }

            try {
                // Verifica se o patrimônio já existe
                $query_verificar = "SELECT id FROM bens WHERE numero_patrimonio = :pat LIMIT 1";
                $stmt_verificar = $db->prepare($query_verificar);
                $stmt_verificar->execute([':pat' => $numero_patrimonio]);
                $existe = $stmt_verificar->rowCount() > 0;

                if ($existe) {
                    if ($acao_duplicados === 'ignorar') {
                        // 🔄 COMPORTAMENTO ORIGINAL: IGNORAR
                        $ignorados++;
                        $logs[] = "Linha " . ($linhas_dados + 1) . ": patrimônio {$numero_patrimonio} já cadastrado (ignorado)";
                        continue;
                    } elseif ($acao_duplicados === 'sobrescrever') {
                        // 🔄 NOVO COMPORTAMENTO: SOBRESCREVER
                        $query = "UPDATE bens SET 
                            unidade_gestora = :ug, 
                            localidade = :loc, 
                            responsavel_localidade = :resp_loc,
                            descricao = :desc,
                            observacoes = :obs,
                            status = :status,
                            responsavel_bem = :resp_bem,
                            auditor = :auditor,
                            data_ultima_vistoria = :data_ultima,
                            data_vistoria_atual = :data_atual,
                            imagem = :imagem,
                            data_atualizacao = NOW()
                            WHERE numero_patrimonio = :num";
                        
                        $stmt = $db->prepare($query);
                        $ok = $stmt->execute([
                            ':ug' => $unidade_gestora,
                            ':loc' => $localidade,
                            ':resp_loc' => $responsavel_localidade,
                            ':desc' => $descricao,
                            ':obs' => $observacoes,
                            ':status' => $status,
                            ':resp_bem' => $responsavel_bem,
                            ':auditor' => $auditor,
                            ':data_ultima' => $data_ultima_vistoria_db,
                            ':data_atual' => $data_vistoria_atual_db,
                            ':imagem' => $imagem,
                            ':num' => $numero_patrimonio
                        ]);

                        if ($ok) {
                            $atualizados++;
                            $logs[] = "Linha " . ($linhas_dados + 1) . ": patrimônio {$numero_patrimonio} atualizado";
                            if ($atualizados % 100 === 0) {
                                $logs[] = "🔄 {$atualizados} itens atualizados...";
                            }
                        } else {
                            $erros++;
                            $logs[] = "Linha " . ($linhas_dados + 1) . ": erro ao atualizar patrimônio {$numero_patrimonio}";
                        }
                    }
                } else {
                    // 🔄 INSERIR NOVO REGISTRO
                    $query = "INSERT INTO bens 
                        (unidade_gestora, localidade, responsavel_localidade, numero_patrimonio, 
                         descricao, observacoes, status, responsavel_bem, auditor,
                         data_ultima_vistoria, data_vistoria_atual, imagem)
                        VALUES (:ug, :loc, :resp_loc, :num, :desc, :obs, :status, 
                                :resp_bem, :auditor, :data_ultima, :data_atual, :imagem)";
                    
                    $stmt = $db->prepare($query);
                    $ok = $stmt->execute([
                        ':ug' => $unidade_gestora,
                        ':loc' => $localidade,
                        ':resp_loc' => $responsavel_localidade,
                        ':num' => $numero_patrimonio,
                        ':desc' => $descricao,
                        ':obs' => $observacoes,
                        ':status' => $status,
                        ':resp_bem' => $responsavel_bem,
                        ':auditor' => $auditor,
                        ':data_ultima' => $data_ultima_vistoria_db,
                        ':data_atual' => $data_vistoria_atual_db,
                        ':imagem' => $imagem
                    ]);

                    if ($ok) {
                        $importados++;
                        if ($importados % 100 === 0) {
                            $logs[] = "✅ {$importados} itens importados...";
                        }
                    } else {
                        $erros++;
                        $logs[] = "Linha " . ($linhas_dados + 1) . ": erro ao inserir patrimônio {$numero_patrimonio}";
                    }
                }
            } catch (PDOException $e) {
                $erros++;
                if ($e->getCode() == '23000') {
                    $logs[] = "Linha " . ($linhas_dados + 1) . ": patrimônio duplicado ({$numero_patrimonio})";
                } else {
                    $logs[] = "Linha " . ($linhas_dados + 1) . ": erro no banco - " . $e->getMessage();
                }
            }
        }

        fclose($handle);
        
        // Limpa arquivo temporário
        if (file_exists($arquivo_convertido)) {
            unlink($arquivo_convertido);
        }

        /**
         * 6️⃣ RESULTADO FINAL E LOG DE IMPORTAÇÃO
         */
        $dados_importacao = [
            'success' => true,
            'importados' => $importados,
            'atualizados' => $atualizados,
            'ignorados' => $ignorados,
            'erros' => $erros,
            'total_linhas' => $linhas_dados,
            'arquivo' => $arquivo_nome,
            'tamanho' => round($_FILES['arquivo_excel']['size'] / 1024 / 1024, 2) . ' MB',
            'acao_duplicados' => $acao_duplicados,
            'logs' => array_slice($logs, -20)
        ];

        if ($acao_duplicados === 'sobrescrever') {
            $mensagem = "✅ Importação concluída: {$importados} novos itens, {$atualizados} atualizados, {$erros} erros.";
        } else {
            $mensagem = "✅ Importação concluída: {$importados} itens importados, {$ignorados} duplicados ignorados, {$erros} erros.";
        }
        $tipo_mensagem = 'success';

    } catch (Exception $e) {
        /**
         * 7️⃣ TRATAMENTO DE ERROS GERAIS
         */
        $mensagem = "❌ Erro: " . $e->getMessage();
        $tipo_mensagem = 'danger';
        error_log("ERRO IMPORTACAO CSV: " . $e->getMessage());
        
        // Limpa arquivo temporário em caso de erro
        if (isset($arquivo_convertido) && file_exists($arquivo_convertido)) {
            unlink($arquivo_convertido);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Bens Patrimoniais | Sistema Patrimonial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-bg: #f8f9fa;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        body {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary-color), #1a2530);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 10px 10px;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid #eaeaea;
            font-weight: 600;
            padding: 1rem 1.5rem;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .option-card {
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .option-card:hover {
            transform: translateY(-2px);
            border-color: #e0e0e0;
        }
        
        .option-card.selected {
            border-color: var(--secondary-color);
            background-color: #f0f7ff;
        }
        
        .option-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #ccc;
        }
        
        .option-card.selected .option-icon {
            color: var(--secondary-color);
        }
        
        .upload-area {
            border: 2px dashed #ccc;
            border-radius: 10px;
            padding: 3rem 2rem;
            text-align: center;
            background-color: #fafafa;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .upload-area:hover, .upload-area.dragover {
            border-color: var(--secondary-color);
            background-color: #f0f7ff;
        }
        
        .upload-icon {
            font-size: 3rem;
            color: #ccc;
            margin-bottom: 1rem;
        }
        
        .upload-area:hover .upload-icon, .upload-area.dragover .upload-icon {
            color: var(--secondary-color);
        }
        
        .file-info {
            display: none;
            margin-top: 1rem;
            padding: 1rem;
            background-color: #e8f4fd;
            border-radius: 5px;
            border-left: 4px solid var(--secondary-color);
        }
        
        .import-stats {
            display: flex;
            justify-content: space-around;
            text-align: center;
            margin: 1.5rem 0;
        }
        
        .stat-item {
            padding: 1rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            display: block;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #777;
        }
        
        .stat-success .stat-value { color: var(--success-color); }
        .stat-warning .stat-value { color: var(--warning-color); }
        .stat-info .stat-value { color: var(--secondary-color); }
        .stat-danger .stat-value { color: var(--danger-color); }
        
        .logs-container {
            max-height: 300px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
        }
        
        .log-success { color: var(--success-color); }
        .log-warning { color: var(--warning-color); }
        .log-error { color: var(--danger-color); }
        .log-info { color: var(--secondary-color); }
        
        .csv-structure {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 1rem;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0"><i class="fas fa-file-import me-2"></i>Importar Bens Patrimoniais</h1>
                    <p class="mb-0 opacity-75">Sistema de Gerenciamento Patrimonial</p>
                </div>
                <a href="index.php" class="btn btn-outline-light">
                    <i class="fas fa-arrow-left me-1"></i> Voltar
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show">
                <?php echo $mensagem; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Card de Estrutura do CSV -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-info-circle me-2"></i>Estrutura do Arquivo CSV
            </div>
            <div class="card-body">
                <p>Seu arquivo CSV deve seguir esta estrutura com as colunas na ordem abaixo:</p>
                <div class="csv-structure mb-3">
                    <strong>Colunas esperadas (11 colunas):</strong><br>
                    1. Unidade Gestora<br>
                    2. Localidade<br>
                    3. Responsável Localidade<br>
                    4. Patrimonio<br>
                    5. Descrição<br>
                    6. Observação<br>
                    7. Status<br>
                    8. Responsável pelo bem<br>
                    9. Auditor<br>
                    10. Data da ultima vistoria<br>
                    11. Data da Vistoria atual<br>
                    12. Imagem do bem (opcional)
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-lightbulb me-2"></i>
                    <strong>Dica:</strong> Na primeira importação, todos os bens serão marcados como <strong>"Pendente"</strong> se o campo Status estiver vazio no CSV.
                </div>
            </div>
        </div>

        <!-- Card de Opções de Duplicados -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-cogs me-2"></i>Opções de Importação
            </div>
            <div class="card-body">
                <h5 class="mb-3">O que fazer quando encontrar patrimônios já cadastrados?</h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="card option-card h-100 <?php echo $acao_duplicados === 'ignorar' ? 'selected' : ''; ?>" 
                             onclick="selectOption('ignorar')">
                            <div class="card-body text-center">
                                <div class="option-icon">
                                    <i class="fas fa-eye-slash"></i>
                                </div>
                                <h5>Ignorar Duplicados</h5>
                                <p class="text-muted">Mantém os registros existentes no banco e ignora os duplicados do arquivo CSV</p>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="acao_duplicados" 
                                           value="ignorar" id="ignorar" 
                                           <?php echo $acao_duplicados === 'ignorar' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="ignorar">
                                        Selecionar
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card option-card h-100 <?php echo $acao_duplicados === 'sobrescrever' ? 'selected' : ''; ?>" 
                             onclick="selectOption('sobrescrever')">
                            <div class="card-body text-center">
                                <div class="option-icon">
                                    <i class="fas fa-sync-alt"></i>
                                </div>
                                <h5>Sobrescrever Duplicados</h5>
                                <p class="text-muted">Atualiza os registros existentes com os dados do arquivo CSV</p>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="acao_duplicados" 
                                           value="sobrescrever" id="sobrescrever" 
                                           <?php echo $acao_duplicados === 'sobrescrever' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="sobrescrever">
                                        Selecionar
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Importante:</strong> A opção "Sobrescrever" irá atualizar todos os campos do registro existente com os dados do arquivo CSV, exceto o número de patrimônio.
                </div>
            </div>
        </div>

        <!-- Card de Upload -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-upload me-2"></i>Upload do Arquivo CSV
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <input type="hidden" name="acao_duplicados" id="acaoDuplicadosInput" value="<?php echo $acao_duplicados; ?>">
                    
                    <div class="upload-area" id="dropArea">
                        <input type="file" class="d-none" id="arquivo_excel" name="arquivo_excel" accept=".csv">
                        <div class="upload-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <h5>Arraste seu arquivo CSV aqui ou clique para selecionar</h5>
                        <p class="text-muted">Tamanho máximo: 50MB | Formatos aceitos: .csv</p>
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('arquivo_excel').click()">
                            <i class="fas fa-folder-open me-1"></i> Selecionar Arquivo
                        </button>
                    </div>
                    
                    <div class="file-info" id="fileInfo">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-file-csv text-primary me-2"></i>
                                <strong id="fileName"></strong>
                                <span id="fileSize" class="text-muted ms-2"></span>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearFile()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mt-4 d-flex justify-content-between">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                            <i class="fas fa-play me-1"></i> Iniciar Importação
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Resultados -->
        <?php if ($dados_importacao): ?>
        <div class="card" id="resultsCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-chart-bar me-2"></i>Resultado da Importação</span>
                <span class="badge bg-success">Concluído</span>
            </div>
            <div class="card-body">
                <div class="import-stats">
                    <?php if ($acao_duplicados === 'sobrescrever'): ?>
                        <div class="stat-item stat-success">
                            <span class="stat-value"><?php echo $dados_importacao['importados']; ?></span>
                            <span class="stat-label">Novos Itens</span>
                        </div>
                        <div class="stat-item stat-info">
                            <span class="stat-value"><?php echo $dados_importacao['atualizados']; ?></span>
                            <span class="stat-label">Atualizados</span>
                        </div>
                    <?php else: ?>
                        <div class="stat-item stat-success">
                            <span class="stat-value"><?php echo $dados_importacao['importados']; ?></span>
                            <span class="stat-label">Importados</span>
                        </div>
                        <div class="stat-item stat-warning">
                            <span class="stat-value"><?php echo $dados_importacao['ignorados']; ?></span>
                            <span class="stat-label">Duplicados Ignorados</span>
                        </div>
                    <?php endif; ?>
                    <div class="stat-item stat-danger">
                        <span class="stat-value"><?php echo $dados_importacao['erros']; ?></span>
                        <span class="stat-label">Erros</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?php echo $dados_importacao['total_linhas']; ?></span>
                        <span class="stat-label">Total Processado</span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <strong>Arquivo:</strong> <?php echo htmlspecialchars($dados_importacao['arquivo']); ?> 
                    <span class="text-muted ms-2"><?php echo $dados_importacao['tamanho']; ?></span>
                    <br>
                    <strong>Opção selecionada:</strong> 
                    <?php echo $acao_duplicados === 'sobrescrever' ? 'Sobrescrever duplicados' : 'Ignorar duplicados'; ?>
                </div>
                
                <?php if (!empty($dados_importacao['logs'])): ?>
                <h6>Logs do Processamento:</h6>
                <div class="logs-container border p-3 bg-light">
                    <?php foreach ($dados_importacao['logs'] as $log): 
                        $classe = 'log-info';
                        if (strpos($log, '✅') !== false || strpos($log, 'importados') !== false) $classe = 'log-success';
                        if (strpos($log, '⚠️') !== false || strpos($log, 'ignorado') !== false) $classe = 'log-warning';
                        if (strpos($log, '❌') !== false || strpos($log, 'erro') !== false) $classe = 'log-error';
                    ?>
                        <div class="<?php echo $classe; ?>"><?php echo htmlspecialchars($log); ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectOption(option) {
            document.querySelectorAll('.option-card').forEach(card => {
                card.classList.remove('selected');
            });
            document.querySelector(`.option-card[onclick="selectOption('${option}')"]`).classList.add('selected');
            document.getElementById(option).checked = true;
            document.getElementById('acaoDuplicadosInput').value = option;
        }

        // Resto do JavaScript para upload (mantido do código anterior)
        const fileInput = document.getElementById('arquivo_excel');
        const dropArea = document.getElementById('dropArea');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const submitBtn = document.getElementById('submitBtn');
        
        // Eventos de Drag & Drop (implementação similar à anterior)
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            dropArea.classList.add('dragover');
        }
        
        function unhighlight() {
            dropArea.classList.remove('dragover');
        }
        
        dropArea.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length) {
                fileInput.files = files;
                updateFileInfo(files[0]);
            }
        }
        
        fileInput.addEventListener('change', function() {
            if (this.files.length) {
                updateFileInfo(this.files[0]);
            }
        });
        
        function updateFileInfo(file) {
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            fileInfo.style.display = 'block';
            submitBtn.disabled = false;
            
            if (!file.name.toLowerCase().endsWith('.csv')) {
                alert('Por favor, selecione um arquivo CSV.');
                clearFile();
                return;
            }
            
            if (file.size > 50 * 1024 * 1024) {
                alert('O arquivo é muito grande. O tamanho máximo permitido é 50MB.');
                clearFile();
                return;
            }
        }
        
        function clearFile() {
            fileInput.value = '';
            fileInfo.style.display = 'none';
            submitBtn.disabled = true;
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    </script>
</body>
</html>