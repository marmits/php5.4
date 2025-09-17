<?php
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$db   = getenv('DB_NAME');
$port = (int)(getenv('DB_PORT'));

$mysqli = @new mysqli($host, $user, $pass, $db, $port);
if ($mysqli->connect_error) {
    die('Erreur connexion: ' . $mysqli->connect_error);
}
echo $mysqli->query('SELECT 1') ? "OK DB !" : "Query failed";
