<?php
// index.php (PHP 5.4)
// Personnalise ici si besoin :
$btn1_label = 'default ./app';
$btn1_href  = './app'; // Si ce fichier est déjà à la racine du site (docroot), tu peux mettre './' ou '/'.
$btn2_label = 'Browse Table datas';
$btn2_href  = '/testsql/browse_table.php?t=datas';

// Petite fonction d’échappement HTML
function h($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bootstrap demo</title>
    <link href="css/styles.css" rel="stylesheet">

    <style>
        html, body { height: 100%; }
        .full-height { height: 100vh; }
    </style>
</head>
<body>
<div class="d-flex justify-content-center align-items-center full-height">
    <div class="text-center" style="min-width:240px; max-width:360px; width: 100%;">

        <div class="container text-center">
            <div class="row mb-1">
                <div class="col">
                    <button class="btn btn-primary" type="submit">default ./app</button><br />
                </div>
            </div>
            <div class="row mb-1">
                <div class="col">
                    <a class="btn btn-success" href="/testsql/browse_table.php" role="button">TEST SQL</a><br />
                </div>
            </div>
            <div class="row mb-1">
                <div class="col">
                    <a class="btn btn-success" href="/mariaDocker.php" role="button">TEST MARIA DOCKER</a><br />
                </div>
            </div>
            <div class="row mb-1">
                <div class="col">
                    <a class="btn btn-success" href="info.php" role="button">PHP INFO</a><br />
                </div>
            </div>
        </div>

    </div>
</div>
</body>
</html>
