<?php
// crud_usuarios.php - Sistema CRUD para usuários
require_once 'includes/init.php';

Auth::protegerPagina();
// Apenas administradores podem acessar
if ($_SESSION['usuario_tipo'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();

$mensagem = '';
$tipo_mensagem = '';

// Processar operações CRUD
if ($_POST) {
    // CREATE - Adicionar novo usuário
    if (isset($_POST['acao']) && $_POST['acao'] === 'criar') {
        try {
            $nome = trim($_POST['nome']);
            $email = trim($_POST['email']);
            $senha = $_POST['senha'];
            $tipo = $_POST['tipo'];
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            // Validar dados
            if (empty($nome) || empty($email) || empty($senha)) {
                throw new Exception("Todos os campos obrigatórios devem ser preenchidos");
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("E-mail inválido");
            }

            // Verificar se email já existe
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new Exception("E-mail já cadastrado");
            }

            // Hash da senha
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

            // Inserir usuário
            $stmt = $db->prepare("INSERT INTO usuarios (nome, email, senha, tipo, ativo) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nome, $email, $senha_hash, $tipo, $ativo]);

            $mensagem = "Usuário criado com sucesso!";
            $tipo_mensagem = "success";

        } catch (Exception $e) {
            $mensagem = $e->getMessage();
            $tipo_mensagem = "danger";
        }
    }

    // UPDATE - Editar usuário
    if (isset($_POST['acao']) && $_POST['acao'] === 'editar') {
        try {
            $id = $_POST['id'];
            $nome = trim($_POST['nome']);
            $email = trim($_POST['email']);
            $tipo = $_POST['tipo'];
            $ativo = isset($_POST['ativo']) ? 1 : 0;

            // Validar dados
            if (empty($nome) || empty($email)) {
                throw new Exception("Todos os campos obrigatórios devem ser preenchidos");
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("E-mail inválido");
            }

            // Verificar se email já existe (excluindo o próprio usuário)
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $stmt->execute([$email, $id]);
            if ($stmt->fetch()) {
                throw new Exception("E-mail já cadastrado para outro usuário");
            }

            // Atualizar usuário
            $sql = "UPDATE usuarios SET nome = ?, email = ?, tipo = ?, ativo = ?";
            $params = [$nome, $email, $tipo, $ativo];

            // Se senha foi fornecida, atualizar também
            if (!empty($_POST['senha'])) {
                $senha_hash = password_hash($_POST['senha'], PASSWORD_DEFAULT);
                $sql .= ", senha = ?";
                $params[] = $senha_hash;
            }

            $sql .= " WHERE id = ?";
            $params[] = $id;

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            $mensagem = "Usuário atualizado com sucesso!";
            $tipo_mensagem = "success";

        } catch (Exception $e) {
            $mensagem = $e->getMessage();
            $tipo_mensagem = "danger";
        }
    }
}

// DELETE - Excluir usuário
if (isset($_GET['excluir'])) {
    try {
        $id = $_GET['excluir'];

        // Não permitir excluir o próprio usuário
        if ($id == $_SESSION['usuario_id']) {
            throw new Exception("Você não pode excluir sua própria conta");
        }

        $stmt = $db->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);

        $mensagem = "Usuário excluído com sucesso!";
        $tipo_mensagem = "success";

    } catch (Exception $e) {
        $mensagem = $e->getMessage();
        $tipo_mensagem = "danger";
    }
}

// Buscar todos os usuários
$usuarios = [];
try {
    $stmt = $db->query("SELECT id, nome, email, tipo, ativo, data_criacao FROM usuarios ORDER BY nome");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erro ao buscar usuários: " . $e->getMessage());
    $mensagem = "Erro ao carregar usuários";
    $tipo_mensagem = "danger";
}

// Buscar usuário para edição
$usuario_edicao = null;
if (isset($_GET['editar'])) {
    try {
        $stmt = $db->prepare("SELECT id, nome, email, tipo, ativo FROM usuarios WHERE id = ?");
        $stmt->execute([$_GET['editar']]);
        $usuario_edicao = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao buscar usuário para edição: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - Sistema Patrimonial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .card-hover {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }
        
        .admin-badge {
            font-size: 0.7em;
        }
        
        .table-actions {
            white-space: nowrap;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Header -->
    <header class="bg-dark text-white shadow-sm">
        <div class="container py-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-people-fill fs-4 me-2 text-primary"></i>
                        <h1 class="h4 mb-0 fw-bold">Gerenciar Usuários</h1>
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <a href="sistema_crud.php" class="btn btn-outline-light btn-sm me-2">
                        <i class="bi bi-arrow-left"></i> Voltar
                    </a>
                    <a href="index.php" class="btn btn-light btn-sm">
                        <i class="bi bi-house"></i> Início
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container py-4">
        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
                <?php echo $mensagem; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Formulário de Cadastro/Edição -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm border-0 card-hover">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold">
                            <i class="bi bi-<?php echo $usuario_edicao ? 'pencil' : 'plus-circle'; ?> me-2"></i>
                            <?php echo $usuario_edicao ? 'Editar Usuário' : 'Novo Usuário'; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="formUsuario">
                            <input type="hidden" name="acao" value="<?php echo $usuario_edicao ? 'editar' : 'criar'; ?>">
                            <?php if ($usuario_edicao): ?>
                                <input type="hidden" name="id" value="<?php echo $usuario_edicao['id']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="nome" class="form-label">Nome Completo *</label>
                                <input type="text" class="form-control" id="nome" name="nome" required
                                       value="<?php echo htmlspecialchars($usuario_edicao['nome'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">E-mail *</label>
                                <input type="email" class="form-control" id="email" name="email" required
                                       value="<?php echo htmlspecialchars($usuario_edicao['email'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="senha" class="form-label">
                                    Senha <?php echo $usuario_edicao ? '(deixe em branco para manter atual)' : '*'; ?>
                                </label>
                                <input type="password" class="form-control" id="senha" name="senha"
                                    <?php echo $usuario_edicao ? '' : 'required'; ?>
                                    minlength="6">
                                <div class="form-text">Mínimo 6 caracteres</div>
                            </div>

                            <div class="mb-3">
                                <label for="tipo" class="form-label">Tipo de Usuário</label>
                                <select class="form-select" id="tipo" name="tipo" required>
                                    <option value="usuario" <?php echo ($usuario_edicao['tipo'] ?? '') === 'usuario' ? 'selected' : ''; ?>>Usuário</option>
                                    <option value="admin" <?php echo ($usuario_edicao['tipo'] ?? '') === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                                </select>
                            </div>

                            <div class="mb-3 form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="ativo" name="ativo" value="1"
                                    <?php echo ($usuario_edicao['ativo'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="ativo">Usuário Ativo</label>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-<?php echo $usuario_edicao ? 'check' : 'plus'; ?>-circle me-1"></i>
                                    <?php echo $usuario_edicao ? 'Atualizar' : 'Cadastrar'; ?> Usuário
                                </button>
                                
                                <?php if ($usuario_edicao): ?>
                                    <a href="crud_usuarios.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-circle me-1"></i> Cancelar
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Lista de Usuários -->
            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">
                            <i class="bi bi-people me-2"></i>
                            Usuários do Sistema (<?php echo count($usuarios); ?>)
                        </h5>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="toggleInativos">
                            <label class="form-check-label" for="toggleInativos">Mostrar inativos</label>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($usuarios) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Usuário</th>
                                            <th>E-mail</th>
                                            <th>Tipo</th>
                                            <th>Status</th>
                                            <th>Cadastro</th>
                                            <th class="text-end">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($usuarios as $usuario): ?>
                                            <tr class="<?php echo !$usuario['ativo'] ? 'table-secondary text-muted' : ''; ?>" 
                                                data-ativo="<?php echo $usuario['ativo']; ?>">
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="user-avatar bg-primary me-3">
                                                            <?php echo strtoupper(substr($usuario['nome'], 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-medium"><?php echo htmlspecialchars($usuario['nome']); ?></div>
                                                            <small class="text-muted">ID: <?php echo $usuario['id']; ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $usuario['tipo'] === 'admin' ? 'bg-danger' : 'bg-secondary'; ?> admin-badge">
                                                        <?php echo $usuario['tipo'] === 'admin' ? 'Administrador' : 'Usuário'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $usuario['ativo'] ? 'bg-success' : 'bg-warning'; ?>">
                                                        <?php echo $usuario['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('d/m/Y', strtotime($usuario['data_criacao'])); ?>
                                                    </small>
                                                </td>
                                                <td class="text-end table-actions">
                                                    <a href="crud_usuarios.php?editar=<?php echo $usuario['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary me-1"
                                                       title="Editar usuário">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <?php if ($usuario['id'] != $_SESSION['usuario_id']): ?>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-danger" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#modalExcluir"
                                                                data-usuario-id="<?php echo $usuario['id']; ?>"
                                                                data-usuario-nome="<?php echo htmlspecialchars($usuario['nome']); ?>"
                                                                title="Excluir usuário">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted" title="Não é possível excluir sua própria conta">
                                                            <i class="bi bi-trash"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-people fs-1 text-muted mb-3"></i>
                                <p class="text-muted">Nenhum usuário cadastrado</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal de Confirmação de Exclusão -->
    <div class="modal fade" id="modalExcluir" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Exclusão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Tem certeza que deseja excluir o usuário <strong id="nomeUsuarioExcluir"></strong>?</p>
                    <p class="text-danger"><small>Esta ação não pode ser desfeita.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <a href="#" id="btnConfirmarExcluir" class="btn btn-danger">Excluir Usuário</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Modal de exclusão
        const modalExcluir = document.getElementById('modalExcluir');
        if (modalExcluir) {
            modalExcluir.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const usuarioId = button.getAttribute('data-usuario-id');
                const usuarioNome = button.getAttribute('data-usuario-nome');
                
                document.getElementById('nomeUsuarioExcluir').textContent = usuarioNome;
                document.getElementById('btnConfirmarExcluir').href = `crud_usuarios.php?excluir=${usuarioId}`;
            });
        }

        // Toggle para mostrar/ocultar usuários inativos
        const toggleInativos = document.getElementById('toggleInativos');
        if (toggleInativos) {
            toggleInativos.addEventListener('change', function() {
                const linhas = document.querySelectorAll('tbody tr');
                linhas.forEach(linha => {
                    if (linha.getAttribute('data-ativo') === '0') {
                        linha.style.display = this.checked ? '' : 'none';
                    }
                });
            });
        }

        // Validação do formulário
        document.getElementById('formUsuario')?.addEventListener('submit', function(e) {
            const senha = document.getElementById('senha').value;
            const isEdicao = <?php echo $usuario_edicao ? 'true' : 'false'; ?>;
            
            if (!isEdicao && senha.length < 6) {
                e.preventDefault();
                alert('A senha deve ter pelo menos 6 caracteres');
                return false;
            }
        });
    </script>
</body>
</html>