<?php 


// Openen in Visual Studio Code aan/uit
const OPEN_VSC = TRUE;
// Om directe link naar VSCode te maken volgende lokale pad instellen:
const WWW_MAP_LOKAAL_PAD = 'c:\\dockernexed\\docker-compose-lamp\\www\\';

// GIT Clone functie aan/uit
const GIT_CLONE = TRUE;

// Folders deleten aan/uit
const ALLOW_DELETE = TRUE;


// Hieronder niets aan te passen...

session_start();

if (isset($_GET['warp'])) {
    $_POST['repo'] = 'https://github.com/danny-talland/js_warpspeed.git';
}

if (isset($_POST['repo']) && !empty($_POST['repo'])) {
    $repo = trim($_POST['repo']);
    
    if (stripos($repo, "git clone ") === 0) {
        $repo = substr($repo, 10);
    }

    list($repo, $target) = explode(" ", $repo . " ");

    if (empty($target)) {
        $target = str_ireplace(".git", "", basename($_POST['repo']));
    }

    $target = '/var/www/html/' . $target;

    $repo = escapeshellarg($repo);
    $target = escapeshellarg($target);

    $result = shell_exec("git clone $repo $target 2>&1") . " ";

    if (stripos($result, "fatal") === true) {
        $_SESSION['shellmsg'] = "<div class='error'>$result</div>";
    } else {
        $_SESSION['shellmsg'] = "<div class='succes'>Cloned $repo in $target...</div>";
    }

    header("Location:index.php");
    die();
}

if (isset($_GET['d'])) {
    $target = escapeshellarg($_GET['d']); 

    $shell = "rm -rf $target";
    $result = shell_exec($shell);

    header("Location:index.php");
    die();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Talland Docker LAMP stack</title>
    <link rel="shortcut icon" href="assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="style.css">
    <script src="script.js"></script>
</head>
<body>
    <header>
        <div id="logo">
            <img src="logo_talland.png">
        </div>
        <div id="status">
            <ul>
                <li><?= apache_get_version(); ?></li>
                <li>PHP <?= phpversion(); ?></li>
                <li>
<?php

try {
    $pdo = new PDO("mysql:host=database", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // throw exceptions on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $version = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    echo "MySQL Server $version";
}
catch (PDOException $e) {
    echo "MySQL connection failed: " . htmlspecialchars($e->getMessage());
}
?>
                    </li>
                </ul>
                <a target="_blank" href="http://localhost:8080"><img src="logo_pma.png"></a>
            </div>
        </header>

        <main>

<?php

if (isset($_SESSION['shellmsg'])) {
    echo $_SESSION['shellmsg'];
    unset($_SESSION['shellmsg']);
}

if (GIT_CLONE) {
?>

            <h2>Clone repository</h2>

            <form id="clone" action="" method="post">
                <input id="repo" name="repo" placeholder="https://github.com/user/repo.git" />
                <button type="submit">CLONE</button>
            </form>

<?php
}
?>

             <h2>Projecten</h2>
<?php 

$it = new DirectoryIterator(".");

foreach ($it as $f) {
    if ($f->isDot()) continue;
    if ($f->isDir()) 
        $items[] = $f->getFilename();
}
    
if (empty($items)) { ?>
    <div class="error">Nog geen projecten...
        <a href="?warp=warp">&#128512;</a>
    </div>
<?php } else { ?>
    <table>
    <tr>
<?php
    natcasesort($items); 
    foreach ($items as $folder) {
        $vsc = "";

        if (OPEN_VSC) {
            $local = str_replace('\\', '/', WWW_MAP_LOKAAL_PAD . $folder);

            $local = rawurlencode($local);
            $local = str_replace('%2F', '/', $local);

            $vsc = "<td class='action'><a href='vscode://file/" . $local . "' title='Open in VSC'><img class='icon' src='logo_vscode.png' alt='Visual Studio Code logo'></a></td>";
        }

        $delete = "";

        if (ALLOW_DELETE) {
            $delete = "<td class='action'><a href='javascript:d(\"" . rawurlencode($folder) . "\")' title='Verwijderen'><img class='icon' src='icon_delete.png' alt='Delete icon'></a></td>";
        }
    ?>
        <?php
            echo $vsc;
            echo $delete;
                ?>

            <td><a target="_blank" href="http://localhost/<?= htmlspecialchars($folder, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($folder, ENT_QUOTES, 'UTF-8') ?></a></td>
        </tr>
    <?php } ?>
        </table>
<?php } ?>

        </main>
    </body>
</html>
