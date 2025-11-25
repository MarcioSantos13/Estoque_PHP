<?php
header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';

Auth::protegerPagina();

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        // Buscar bem específico ou lista
        if(isset($_GET['id'])) {
            $id = $_GET['id'];
            $query = "SELECT * FROM bens WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":id", $id);
            $stmt->execute();
            
            if($stmt->rowCount() > 0) {
                $bem = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $bem]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Bem não encontrado']);
            }
        } else {
            // Listar bens com paginação e filtros
            $pagina = $_GET['pagina'] ?? 1;
            $por_pagina = $_GET['por_pagina'] ?? 20;
            $offset = ($pagina - 1) * $por_pagina;
            
            $where = [];
            $params = [];
            
            if(isset($_GET['q']) && !empty($_GET['q'])) {
                $where[] = "(descricao LIKE :q OR numero_patrimonio LIKE :q)";
                $params[':q'] = '%' . $_GET['q'] . '%';
            }
            
            if(isset($_GET['status']) && !empty($_GET['status'])) {
                $where[] = "status = :status";
                $params[':status'] = $_GET['status'];
            }
            
            $where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";
            
            // Total de registros
            $count_query = "SELECT COUNT(*) as total FROM bens $where_sql";
            $count_stmt = $db->prepare($count_query);
            foreach($params as $key => $value) {
                $count_stmt->bindValue($key, $value);
            }
            $count_stmt->execute();
            $total_registros = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Dados
            $query = "SELECT * FROM bens $where_sql ORDER BY id DESC LIMIT :offset, :limit";
            $stmt = $db->prepare($query);
            foreach($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            $stmt->bindValue(':limit', (int)$por_pagina, PDO::PARAM_INT);
            $stmt->execute();
            
            $bens = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $bens,
                'paginacao' => [
                    'pagina_atual' => (int)$pagina,
                    'por_pagina' => (int)$por_pagina,
                    'total_registros' => (int)$total_registros,
                    'total_paginas' => ceil($total_registros / $por_pagina)
                ]
            ]);
        }
        break;
        
    case 'POST':
        // Criar novo bem
        $dados = json_decode(file_get_contents('php://input'), true);
        
        $query = "INSERT INTO bens SET 
            unidade_gestora = :unidade_gestora,
            localidade = :localidade,
            responsavel_localidade = :responsavel_localidade,
            numero_patrimonio = :numero_patrimonio,
            descricao = :descricao,
            observacoes = :observacoes,
            status = :status,
            responsavel_bem = :responsavel_bem,
            auditor = :auditor,
            data_ultima_vistoria = :data_ultima_vistoria,
            data_vistoria_atual = :data_vistoria_atual,
            localizacao_atual = :localizacao_atual";
            
        $stmt = $db->prepare($query);
        $stmt->bindParam(":unidade_gestora", $dados['unidade_gestora']);
        $stmt->bindParam(":localidade", $dados['localidade']);
        $stmt->bindParam(":responsavel_localidade", $dados['responsavel_localidade']);
        $stmt->bindParam(":numero_patrimonio", $dados['numero_patrimonio']);
        $stmt->bindParam(":descricao", $dados['descricao']);
        $stmt->bindParam(":observacoes", $dados['observacoes']);
        $stmt->bindParam(":status", $dados['status']);
        $stmt->bindParam(":responsavel_bem", $dados['responsavel_bem']);
        $stmt->bindParam(":auditor", $dados['auditor']);
        $stmt->bindParam(":data_ultima_vistoria", $dados['data_ultima_vistoria']);
        $stmt->bindParam(":data_vistoria_atual", $dados['data_vistoria_atual']);
        $stmt->bindParam(":localizacao_atual", $dados['localizacao_atual']);
        
        if($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Bem cadastrado com sucesso']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao cadastrar bem']);
        }
        break;
        
    case 'PUT':
        // Atualizar bem
        $id = $_GET['id'];
        $dados = json_decode(file_get_contents('php://input'), true);
        
        $query = "UPDATE bens SET 
            unidade_gestora = :unidade_gestora,
            localidade = :localidade,
            responsavel_localidade = :responsavel_localidade,
            descricao = :descricao,
            observacoes = :observacoes,
            status = :status,
            responsavel_bem = :responsavel_bem,
            auditor = :auditor,
            data_ultima_vistoria = :data_ultima_vistoria,
            data_vistoria_atual = :data_vistoria_atual,
            localizacao_atual = :localizacao_atual
            WHERE id = :id";
            
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":unidade_gestora", $dados['unidade_gestora']);
        $stmt->bindParam(":localidade", $dados['localidade']);
        $stmt->bindParam(":responsavel_localidade", $dados['responsavel_localidade']);
        $stmt->bindParam(":descricao", $dados['descricao']);
        $stmt->bindParam(":observacoes", $dados['observacoes']);
        $stmt->bindParam(":status", $dados['status']);
        $stmt->bindParam(":responsavel_bem", $dados['responsavel_bem']);
        $stmt->bindParam(":auditor", $dados['auditor']);
        $stmt->bindParam(":data_ultima_vistoria", $dados['data_ultima_vistoria']);
        $stmt->bindParam(":data_vistoria_atual", $dados['data_vistoria_atual']);
        $stmt->bindParam(":localizacao_atual", $dados['localizacao_atual']);
        
        if($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Bem atualizado com sucesso']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar bem']);
        }
        break;
        
    case 'DELETE':
        // Excluir bem
        $id = $_GET['id'];
        
        $query = "DELETE FROM bens WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $id);
        
        if($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Bem excluído com sucesso']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao excluir bem']);
        }
        break;
}
?>