<?php
// includes/config.php - Configurações do sistema

// Configurações de banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'sistema_patrimonial');
define('DB_USER', 'root');
define('DB_PASS', '');

// Configurações do sistema
define('SISTEMA_NOME', 'Sistema de Controle Patrimonial');
define('SISTEMA_VERSAO', '1.0.0');
define('BASE_URL', 'http://localhost/sistema_patrimonial/');

// Configurações de upload
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('UPLOAD_ALLOWED_TYPES', ['xlsx', 'xls']);
?>