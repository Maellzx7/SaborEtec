<?php


define('DB_HOST', 'localhost');
define('DB_NAME', 'merenda_etec');
define('DB_USER', 'root');       
define('DB_PASS', '');           
define('DB_CHARSET', 'utf8mb4');

function conectar(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:40px;color:#8b0000;">
                <h2>Erro de conexão com o banco de dados</h2>
                <p>' . htmlspecialchars($e->getMessage()) . '</p>
                <p>Verifique as configurações em <code>config/database.php</code></p>
            </div>');
        }
    }
    return $pdo;
}