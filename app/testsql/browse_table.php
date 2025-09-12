<?php
/**
 * browse_table.php — PHP 5.4
 * Affiche le contenu d'une table passée en paramètre GET ?t=table
 * Utilise les variables d'environnement: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
 */

header('Content-Type: text/html; charset=UTF-8');

// --------- Helpers ----------
function h($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function bad_request($msg, $http_code = 400) {
    header('Content-Type: text/plain; charset=UTF-8', true, $http_code);
    exit($msg);
}

// --------- Lecture des paramètres ----------
$table = isset($_GET['t']) ? $_GET['t'] : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
$page  = isset($_GET['page'])  ? (int)$_GET['page']  : 1;

if ($limit < 1 || $limit > 5000) $limit = 200;
if ($page  < 1) $page = 1;

// Autoriser uniquement des identifiants simples (lettres/chiffres/underscore)
// (évite toute injection via nom de table)
if ($table !== '' && !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
    bad_request("Nom de table invalide. Utilise uniquement lettres, chiffres et underscore (_).");
}

// --------- Connexion PDO (via env) ----------
$dbhost   = getenv('DB_HOST') ?: 'host.docker.internal';
$dbport   = getenv('DB_PORT') ?: '3306';
$dbname   = getenv('DB_NAME') ?: 'bdd';
$dbuser   = getenv('DB_USER') ?: 'root';
$dbpass   = getenv('DB_PASS') ?: '';
$charset  = 'utf8mb4';

$dsn = 'mysql:host='.$dbhost.';port='.$dbport.';dbname='.$dbname.';charset='.$charset;

try {
    $pdo = new PDO($dsn, $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Pour de vieilles stacks PHP 5.x, SET NAMES peut aider :
    $pdo->exec('SET NAMES '.$charset);
} catch (PDOException $e) {
    header('Content-Type: text/plain; charset=UTF-8', true, 500);
    exit("Erreur de connexion à la base: " . $e->getMessage());
}

// Si pas de table demandée, proposer un petit formulaire
if ($table === '') {
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Parcourir une table</title>';
    echo '<style>body{font-family:Arial,sans-serif;margin:20px}input,button{font-size:14px;padding:6px}</style>';
    echo '</head><body>';
    echo '<h1>Parcourir une table</h1>';
    echo '<form method="get" action="">';
    echo '  <label>Nom de table : <input type="text" name="t" required></label> ';
    echo '  <label>Limite : <input type="number" name="limit" min="1" max="5000" value="200"></label> ';
    echo '  <button type="submit">Afficher</button>';
    echo '</form>';
    echo '<p style="color:#666">La connexion utilise les variables d’environnement DB_HOST/PORT/NAME/USER/PASS.</p>';
    echo '</body></html>';
    exit;
}

// --------- Vérifier l'existence de la table ----------
try {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute(array($table));
    $exists = (int)$stmt->fetchColumn();
    if ($exists === 0) {
        bad_request("La table '". $table ."' n'existe pas dans la base '". $dbname ."'.");
    }

    // Récupérer la liste des colonnes dans l’ordre
    $colsStmt = $pdo->prepare('
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
        ORDER BY ORDINAL_POSITION
    ');
    $colsStmt->execute(array($table));
    $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);

    if (!$cols) {
        bad_request("Impossible de récupérer les colonnes de la table '". $table ."'.");
    }

    // Pagination - total des lignes
    $safeTable = '`' . str_replace('`', '``', $table) . '`';

    $countSql = 'SELECT COUNT(*) FROM ' . $safeTable;
    $countStmt = $pdo->query($countSql);
    $total = (int)$countStmt->fetchColumn();

    $pages = max(1, (int)ceil($total / $limit));
    if ($page > $pages) $page = $pages;

    $offset = ($page - 1) * $limit;

    // Construire SELECT avec identifiants quotés
    $quotedCols = array();
    foreach ($cols as $c) {
        $quotedCols[] = '`' . str_replace('`', '``', $c) . '`';
    }
    $sql = 'SELECT ' . implode(',', $quotedCols) . ' FROM ' . $safeTable . ' LIMIT :limit OFFSET :offset';
    $dataStmt = $pdo->prepare($sql);
    $dataStmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $dataStmt->execute();
    $rows = $dataStmt->fetchAll();

} catch (PDOException $e) {
    header('Content-Type: text/plain; charset=UTF-8', true, 500);
    exit("Erreur SQL: " . $e->getMessage());
}

// --------- Rendu HTML ----------
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?php echo 'Table ' . h($table) . ' — ' . h($dbname); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="../css/styles.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; margin:20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
        th { background: #f5f5f5; text-align: left; position: sticky; top: 0; }
        .mono { font-family: Consolas, "Courier New", monospace; white-space: pre-wrap; }
        .muted { color: #666; }
        .toolbar { margin: 0 0 12px 0; }
        .toolbar form { display:inline-block; }
        .pager a { margin: 0 6px; text-decoration: none; }
        .pager strong { margin: 0 6px; }
        .wrap { word-break: break-word; }
    </style>
</head>
<body>
<h1>Base <code><?php echo h($dbname); ?></code> — Table <code><?php echo h($table); ?></code></h1>

<div class="toolbar">
    <form method="get" action="">
        <label>Table :
            <input type="text" name="t" value="<?php echo h($table); ?>" required>
        </label>
        <label>Limite :
            <input type="number" name="limit" min="1" max="5000" value="<?php echo (int)$limit; ?>">
        </label>
        <button type="submit">Afficher</button>
    </form>
    <span class="muted">Connexion via variables d’environnement (DB_HOST/PORT/NAME/USER/PASS)</span>
</div>

<p class="muted">
    Lignes <?php echo ($total ? ($offset + 1) : 0); ?>–<?php echo min($offset + $limit, $total); ?>
    sur <?php echo $total; ?>.
</p>

<?php if (!$rows): ?>
    <p class="muted">Aucune ligne à afficher.</p>
<?php else: ?>
    <table class="table">
        <thead>
        <tr class="table-primary">
            <?php foreach ($cols as $c): ?>
                <th><?php echo h($c); ?></th>
            <?php endforeach; ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <?php foreach ($cols as $c): ?>
                    <?php
                    $val = isset($r[$c]) ? $r[$c] : null;
                    // Heuristique simple pour l'affichage
                    $isLong = is_string($val) && strlen($val) > 120;
                    ?>
                    <td class="<?php echo $isLong ? 'mono wrap' : 'wrap'; ?>">
                        <?php
                        if (is_null($val)) {
                            echo '<span class="muted"><em>NULL</em></span>';
                        } else {
                            echo h((string)$val);
                        }
                        ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if ($pages > 1): ?>
    <p class="pager">
        <?php
        $base = '?t='.urlencode($table).'&limit='.(int)$limit.'&page=';
        if ($page > 1) {
            echo '<a href="'.$base.'1">&laquo; Début</a>';
            echo '<a href="'.$base.($page-1).'">&lsaquo; Préc.</a>';
        } else {
            echo '<span class="muted">&laquo; Début</span>';
            echo '<span class="muted">&lsaquo; Préc.</span>';
        }
        echo '<strong>Page '.(int)$page.' / '.(int)$pages.'</strong>';
        if ($page < $pages) {
            echo '<a href="'.$base.($page+1).'">Suiv. &rsaquo;</a>';
            echo '<a href="'.$base.$pages.'">Fin &raquo;</a>';
        } else {
            echo '<span class="muted">Suiv. &rsaquo;</span>';
            echo '<span class="muted">Fin &raquo;</span>';
        }
        ?>
    </p>
<?php endif; ?>
</body>
</html>
