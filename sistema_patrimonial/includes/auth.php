<?php
// includes/auth.php - Sistema de autenticação

class Auth {
    
    public static function login($email, $senha) {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT * FROM usuarios WHERE email = :email AND ativo = 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        
        if($stmt->rowCount() == 1) {
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            if(password_verify($senha, $usuario['senha'])) {
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_nome'] = $usuario['nome'];
                $_SESSION['usuario_email'] = $usuario['email'];
                $_SESSION['usuario_tipo'] = $usuario['tipo'];
                $_SESSION['logged_in'] = true;
                return true;
            }
        }
        return false;
    }
    
    public static function logout() {
        $_SESSION = array();
        
        // Destruir cookie de sessão
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        header("Location: ../login.php");
        exit;
    }
    
    public static function isLogged() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public static function isAdmin() {
        return isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] == 'admin';
    }
    
    public static function protegerPagina($requireAdmin = false) {
        if(!self::isLogged()) {
            header("Location: login.php");
            exit;
        }
        
        if($requireAdmin && !self::isAdmin()) {
            header("Location: ../index.php");
            exit;
        }
    }
    
    // 🔥 MÉTODO ADICIONADO AQUI 🔥
    public static function getUsuarioNome() {
        return $_SESSION['usuario_nome'] ?? 'Usuário';
    }
}

// Logout via GET
if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    Auth::logout();
}
?>