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
if ($mysqli->query('SELECT 1')){
    echo "<p>"."nom du serveur : ".$host."</p>";
    echo "<p>"."nom user : ".$user."</p>";
    echo "<p>"."nom de la bd : ".$db."</p>";
    echo "<p>"."port de la bd : ".$port."</p>";
} else {
    echo "Query failed";
}
