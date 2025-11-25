<?php
// backup_limpeza.php - Sistema de Backup e Limpeza do Banco de Dados
require_once 'includes/init.php';

// Proteger página - apenas administradores
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();

$mensagem = '';
$tipo_mensagem = '';
$backup_realizado = false;
$backup_arquivo = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['fazer_backup'])) {
            $resultado = fazerBackup($db);
            $mensagem = $resultado['mensagem'];
            $tipo_mensagem = $resultado['tipo'];
            $backup_realizado = $resultado['sucesso'];
            $backup_arquivo = $resultado['arquivo'] ?? '';
            
        } elseif (isset($_POST['limpar_dados'])) {
            // Verificar se backup foi feito
            if (empty($_POST['backup_confirmacao'])) {
                throw new Exception('Você deve confirmar que fez backup antes de limpar os dados!');
            }
            
            $resultado = limparBaseDados($db);
            $mensagem = $resultado['mensagem'];
            $tipo_mensagem = $resultado['tipo'];
        }
    } catch (Exception $e) {
        $mensagem = "Erro: " . $e->getMessage();
        $tipo_mensagem = 'danger';
    }
}

// Função para fazer backup
function fazerBackup($db) {
    $backup_dir = 'backups/';
    
    // Criar diretório de backups se não existir
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
    // Nome do arquivo de backup
    $data_hora = date('Y-m-d_H-i-s');
    $backup_file = $backup_dir . 'backup_' . DB_NAME . '_' . $data_hora . '.sql';
    
    try {
        // Obter todas as tabelas
        $stmt = $db->query("SHOW TABLES");
        $tabelas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($tabelas)) {
            throw new Exception('Nenhuma tabela encontrada no banco de dados.');
        }
        
        $backup_content = "-- Backup do Sistema Patrimonial\n";
        $backup_content .= "-- Data: " . date('d/m/Y H:i:s') . "\n";
        $backup_content .= "-- Banco: " . DB_NAME . "\n\n";
        
        foreach ($tabelas as $tabela) {
            $backup_content .= "--\n-- Estrutura da tabela `$tabela`\n--\n\n";
            
            // Obter estrutura da tabela
            $stmt = $db->query("SHOW CREATE TABLE `$tabela`");
            $create_table = $stmt->fetch(PDO::FETCH_ASSOC);
            $backup_content .= $create_table['Create Table'] . ";\n\n";
            
            // Obter dados da tabela
            $backup_content .= "--\n-- Dump dos dados da tabela `$tabela`\n--\n\n";
            
            $stmt = $db->query("SELECT * FROM `$tabela`");
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($dados)) {
                foreach ($dados as $linha) {
                    $colunas = array_map(function($col) use ($db) {
                        return "`$col`";
                    }, array_keys($linha));
                    
                    $valores = array_map(function($valor) use ($db) {
                        if ($valor === null) {
                            return 'NULL';
                        } else {
                            // Escapar valores para SQL
                            return $db->quote($valor);
                        }
                    }, array_values($linha));
                    
                    $backup_content .= "INSERT INTO `$tabela` (" . implode(', ', $colunas) . ") VALUES (" . implode(', ', $valores) . ");\n";
                }
                $backup_content .= "\n";
            }
        }
        
        // Salvar arquivo
        if (file_put_contents($backup_file, $backup_content)) {
            return [
                'sucesso' => true,
                'mensagem' => "Backup realizado com sucesso! Arquivo: " . basename($backup_file),
                'tipo' => 'success',
                'arquivo' => $backup_file
            ];
        } else {
            throw new Exception('Erro ao salvar arquivo de backup.');
        }
        
    } catch (Exception $e) {
        throw new Exception('Erro ao fazer backup: ' . $e->getMessage());
    }
}

// Função para limpar base de dados
function limparBaseDados($db) {
    try {
        // Desativar chaves estrangeiras temporariamente
        $db->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        // Obter todas as tabelas
        $stmt = $db->query("SHOW TABLES");
        $tabelas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $tabelas_limpas = [];
        
        foreach ($tabelas as $tabela) {
            // Não limpar tabela de usuários para manter acesso ao sistema
            if ($tabela === 'usuarios') {
                continue;
            }
            
            // Limpar tabela
            $db->exec("TRUNCATE TABLE `$tabela`");
            $tabelas_limpas[] = $tabela;
        }
        
        // Reativar chaves estrangeiras
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        return [
            'sucesso' => true,
            'mensagem' => "Base de dados limpa com sucesso! Tabelas afetadas: " . implode(', ', $tabelas_limpas),
            'tipo' => 'success'
        ];
        
    } catch (Exception $e) {
        // Reativar chaves estrangeiras em caso de erro
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");
        throw new Exception('Erro ao limpar base de dados: ' . $e->getMessage());
    }
}

// Listar backups existentes
function listarBackups() {
    $backup_dir = 'backups/';
    $backups = [];
    
    if (is_dir($backup_dir)) {
        $arquivos = scandir($backup_dir);
        foreach ($arquivos as $arquivo) {
            if ($arquivo !== '.' && $arquivo !== '..' && pathinfo($arquivo, PATHINFO_EXTENSION) === 'sql') {
                $caminho_completo = $backup_dir . $arquivo;
                $backups[] = [
                    'nome' => $arquivo,
                    'caminho' => $caminho_completo,
                    'tamanho' => filesize($caminho_completo),
                    'data' => filemtime($caminho_completo)
                ];
            }
        }
        
        // Ordenar por data (mais recente primeiro)
        usort($backups, function($a, $b) {
            return $b['data'] - $a['data'];
        });
    }
    
    return $backups;
}

$backups_existentes = listarBackups();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup e Limpeza - <?php echo SISTEMA_NOME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .card-danger {
            border-left: 4px solid #dc3545;
        }
        .card-warning {
            border-left: 4px solid #ffc107;
        }
        .card-success {
            border-left: 4px solid #198754;
        }
        .backup-file {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin: 5px 0;
        }
        .file-size {
            color: #6c757d;
            font-size: 0.9em;
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
                        <i class="bi bi-database-gear me-2"></i>Backup e Limpeza
                    </h1>
                    <p class="mb-0 opacity-75">Bem-vindo, <?php echo $_SESSION['usuario_nome'] ?? 'Usuário'; ?>!</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="<?php echo BASE_URL; ?>sistema_crud.php" class="btn btn-light btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>Voltar ao CRUD
                    </a>
                    <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-house me-1"></i>Início
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container py-4">
        <!-- Mensagens -->
        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show">
                <?php echo $mensagem; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Coluna de Backup -->
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm card-success">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-cloud-arrow-down me-2"></i>Backup do Banco de Dados</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">
                            Crie um backup completo do banco de dados antes de realizar qualquer limpeza.
                            O backup incluirá todas as tabelas e dados atuais.
                        </p>
                        
                        <form method="POST">
                            <button type="submit" name="fazer_backup" class="btn btn-success w-100">
                                <i class="bi bi-download me-1"></i>Fazer Backup Agora
                            </button>
                        </form>

                        <?php if ($backup_realizado && $backup_arquivo): ?>
                        <div class="mt-3 p-3 bg-light rounded">
                            <h6><i class="bi bi-file-earmark-text me-1"></i>Backup Criado:</h6>
                            <div class="backup-file">
                                <strong><?php echo basename($backup_arquivo); ?></strong>
                                <br>
                                <span class="file-size">
                                    <?php echo round(filesize($backup_arquivo) / 1024, 2); ?> KB
                                </span>
                                <div class="mt-2">
                                    <a href="<?php echo $backup_arquivo; ?>" download class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-download me-1"></i>Download
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Backups Existentes -->
                <?php if (!empty($backups_existentes)): ?>
                <div class="card shadow-sm mt-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-archive me-2"></i>Backups Existentes</h6>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted">Últimos backups realizados:</p>
                        <?php foreach (array_slice($backups_existentes, 0, 5) as $backup): ?>
                            <div class="backup-file">
                                <strong><?php echo $backup['nome']; ?></strong>
                                <br>
                                <span class="file-size">
                                    <?php echo round($backup['tamanho'] / 1024, 2); ?> KB - 
                                    <?php echo date('d/m/Y H:i', $backup['data']); ?>
                                </span>
                                <div class="mt-2">
                                    <a href="<?php echo $backup['caminho']; ?>" download class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-download me-1"></i>Download
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (count($backups_existentes) > 5): ?>
                            <div class="text-center mt-2">
                                <small class="text-muted">
                                    + <?php echo count($backups_existentes) - 5; ?> backups mais antigos
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Coluna de Limpeza -->
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm card-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="bi bi-trash3 me-2"></i>Limpeza do Banco de Dados</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <h6><i class="bi bi-exclamation-triangle me-2"></i>Atenção!</h6>
                            <p class="mb-2">Esta ação irá:</p>
                            <ul class="mb-2">
                                <li>Limpar TODOS os dados do sistema</li>
                                <li>Manter apenas os usuários cadastrados</li>
                                <li>Remover todos os bens patrimoniais</li>
                                <li>Esta ação NÃO pode ser desfeita</li>
                            </ul>
                            <strong class="text-danger">FAÇA BACKUP ANTES DE CONTINUAR!</strong>
                        </div>

                        <form method="POST" id="formLimpeza">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="backup_confirmacao" id="backup_confirmacao" required>
                                    <label class="form-check-label text-danger fw-bold" for="backup_confirmacao">
                                        Confirmo que fiz backup dos dados e entendo que esta ação é irreversível
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirmacao_texto" class="form-label">
                                    Digite <strong>"LIMPAR DADOS"</strong> para confirmar:
                                </label>
                                <input type="text" class="form-control" id="confirmacao_texto" name="confirmacao_texto" 
                                       placeholder="LIMPAR DADOS" required pattern="LIMPAR DADOS">
                            </div>

                            <button type="submit" name="limpar_dados" class="btn btn-danger w-100" id="btnLimpar" disabled>
                                <i class="bi bi-trash3 me-1"></i>Limpar Todos os Dados
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Informações do Banco -->
                <div class="card shadow-sm mt-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Informações do Banco</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            // Contar registros nas tabelas
                            $stmt = $db->query("SHOW TABLES");
                            $tabelas = $stmt->fetchAll(PDO::FETCH_COLUMN);
                            
                            echo '<div class="table-responsive">';
                            echo '<table class="table table-sm">';
                            echo '<thead><tr><th>Tabela</th><th>Registros</th></tr></thead>';
                            echo '<tbody>';
                            
                            $total_registros = 0;
                            foreach ($tabelas as $tabela) {
                                $stmt_count = $db->query("SELECT COUNT(*) as total FROM `$tabela`");
                                $count = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
                                $total_registros += $count;
                                
                                echo "<tr>";
                                echo "<td>$tabela</td>";
                                echo "<td>" . number_format($count) . "</td>";
                                echo "</tr>";
                            }
                            
                            echo '</tbody>';
                            echo '<tfoot><tr class="table-info"><td><strong>Total</strong></td><td><strong>' . number_format($total_registros) . '</strong></td></tr></tfoot>';
                            echo '</table>';
                            echo '</div>';
                            
                        } catch (Exception $e) {
                            echo '<p class="text-muted">Erro ao obter informações do banco.</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validação do formulário de limpeza
        document.addEventListener('DOMContentLoaded', function() {
            const checkbox = document.getElementById('backup_confirmacao');
            const texto = document.getElementById('confirmacao_texto');
            const btnLimpar = document.getElementById('btnLimpar');
            
            function validarFormulario() {
                if (checkbox.checked && texto.value === 'LIMPAR DADOS') {
                    btnLimpar.disabled = false;
                } else {
                    btnLimpar.disabled = true;
                }
            }
            
            checkbox.addEventListener('change', validarFormulario);
            texto.addEventListener('input', validarFormulario);
            
            // Confirmação final
            document.getElementById('formLimpeza').addEventListener('submit', function(e) {
                if (!confirm('⚠️ ATENÇÃO! Esta ação irá APAGAR TODOS os dados do sistema (exceto usuários). Tem certeza absoluta que deseja continuar?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>