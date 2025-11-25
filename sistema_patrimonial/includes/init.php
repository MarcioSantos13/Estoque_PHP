<?php
// includes/init.php - Inicialização do sistema

// Iniciar sessão se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir configurações
require_once 'config.php';

// Incluir classes
require_once 'database.php';
require_once 'auth.php';

// Configurações de erro (apenas em desenvolvimento)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('America/Sao_Paulo');
?>