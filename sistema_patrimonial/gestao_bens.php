<?php
// gestao_bens.php - Sistema de Gestão de Bens (Versão Segura)
require_once 'includes/init.php';

// Verificar se usuário está logado e é admin
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['usuario_tipo'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Ação padrão: listar
$acao = $_GET['acao'] ?? 'listar';
$id = $_GET['id'] ?? null;

// Mensagens
$mensagem = '';
$tipo_mensagem = '';

// Processar formulários
if ($_POST) {
    if (isset($_POST['cadastrar'])) {
        try {
            $stmt = $db->prepare("INSERT INTO bens (numero_patrimonio, descricao, localidade, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $_POST['numero_patrimonio'],
                $_POST['descricao'],
                $_POST['localidade'],
                $_POST['status']
            ]);
            $mensagem = "Bem cadastrado com sucesso!";
            $tipo_mensagem = "success";
        } catch (Exception $e) {
            $mensagem = "Erro: " . $e->getMessage();
            $tipo_mensagem = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Bens - <?php echo SISTEMA_NOME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Header -->
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <span class="navbar-brand mb-0 h1">Gestão de Bens</span>
            <div>
                <a href="index.php" class="btn btn-light btn-sm">Início</a>
                <a href="includes/auth.php?logout=true" class="btn btn-outline-light btn-sm">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Mensagens -->
        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                <?php echo $mensagem; ?>
            </div>
        <?php endif; ?>

        <!-- Navegação -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="btn-group">
                    <a href="?acao=listar" class="btn btn-outline-primary">Listar Bens</a>
                    <a href="?acao=cadastrar" class="btn btn-outline-success">Cadastrar Bem</a>
                </div>
            </div>
        </div>

        <!-- Conteúdo -->
        <?php if ($acao === 'listar'): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Lista de Bens</h5>
                </div>
                <div class="card-body">
                    <?php
                    $stmt = $db->query("SELECT * FROM bens ORDER BY id DESC LIMIT 50");
                    $bens = $stmt->fetchAll();
                    ?>
                    
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Nº Patrimônio</th>
                                    <th>Descrição</th>
                                    <th>Localidade</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bens as $bem): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($bem['numero_patrimonio']); ?></td>
                                    <td><?php echo htmlspecialchars($bem['descricao']); ?></td>
                                    <td><?php echo htmlspecialchars($bem['localidade']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $bem['status'] === 'Localizado' ? 'success' : 'warning'; ?>">
                                            <?php echo $bem['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?acao=editar&id=<?php echo $bem['id']; ?>" class="btn btn-sm btn-primary">Editar</a>
                                        <a href="?acao=visualizar&id=<?php echo $bem['id']; ?>" class="btn btn-sm btn-info">Ver</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($acao === 'cadastrar'): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Cadastrar Novo Bem</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Número do Patrimônio</label>
                                <input type="text" name="numero_patrimonio" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Descrição</label>
                                <input type="text" name="descricao" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Localidade</label>
                                <input type="text" name="localidade" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="Localizado">Localizado</option>
                                    <option value="Pendente">Pendente</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" name="cadastrar" class="btn btn-success">Cadastrar</button>
                                <a href="?acao=listar" class="btn btn-secondary">Cancelar</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>