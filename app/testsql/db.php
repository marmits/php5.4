<?php
// db.php
function db()
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';
    $db = $config['db'];

    $dsn = 'mysql:host=' . $db['host'] . ';port=' . (int)$db['port'] .
        ';dbname=' . $db['name'] . ';charset=' . $db['charset'];

    try {
        $pdo = new PDO($dsn, $db['user'], $db['pass']);
        // Réglages PDO conseillés
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_PERSISTENT, false);

        // Pour PHP 5.4 + vieilles libmysql, forcer SET NAMES est parfois utile :
        $pdo->exec('SET NAMES ' . $db['charset']);

        return $pdo;
    } catch (PDOException $e) {
        $msg = 'Erreur de connexion à la base de données.';
        if (!empty($config['debug'])) {
            $msg .= ' Détails : ' . $e->getMessage();
        }
        // Évite d’exposer les détails en prod
        header('Content-Type: text/plain; charset=UTF-8', true, 500);
        exit($msg);
    }
}