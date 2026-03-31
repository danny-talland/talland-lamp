<?php 


// Openen in Visual Studio Code aan/uit
const OPEN_VSC = TRUE;
// Om directe link naar VSCode te maken volgende lokale pad instellen:
const WWW_MAP_LOKAAL_PAD = 'c:\\dockernexed\\docker-compose-lamp\\www\\';

// GIT Clone functie aan/uit
const GIT_CLONE = TRUE;

// Folders deleten aan/uit
const ALLOW_DELETE = TRUE;

// SSL certificaten uploaden aan/uit
const CERT_UPLOAD = TRUE;


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

    $ssh_key = '/var/www/.ssh/id_ed25519';
    $ssh_key_content = @file_get_contents($ssh_key);

    // Placeholder or missing private key is a common local setup issue in this stack.
    if (!is_string($ssh_key_content) || stripos($ssh_key_content, 'BEGIN OPENSSH PRIVATE KEY') === false) {
        $_SESSION['shellmsg'] = "<div class='error'>Git clone geblokkeerd: geen geldige private key gevonden op /var/www/.ssh/id_ed25519.<br>Zet je echte key in ssh/id_ed25519 (niet de placeholder tekst) en herstart de webserver container.</div>";
        header("Location:index.php");
        die();
    }

    $runtime_key = '/tmp/git_id_ed25519';
    @copy($ssh_key, $runtime_key);
    @chmod($runtime_key, 0600);

    $ssh_cmd = "ssh -o IdentitiesOnly=yes -o IdentityFile=/tmp/git_id_ed25519 -o StrictHostKeyChecking=accept-new";
    $result = shell_exec("GIT_SSH_COMMAND='" . $ssh_cmd . "' git clone $repo $target 2>&1") . " ";

    @unlink($runtime_key);

    if (stripos($result, "fatal") !== false || stripos($result, "error:") !== false) {
        $_SESSION['shellmsg'] = "<div class='error'>" . nl2br(htmlspecialchars($result)) . "</div>";
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

if (isset($_POST['action']) && $_POST['action'] === 'cert_upload') {
    $ssl_dir = '/etc/apache2/ssl/';
    $errors  = [];
    $saved   = [];

    if (empty($_FILES['ssl_files']['name'][0])) {
        $_SESSION['shellmsg'] = "<div class='error'>Geen bestanden geselecteerd.</div>";
        header("Location:index.php");
        die();
    }

    // Process multiple files from single input[type=file multiple]
    for ($i = 0; $i < count($_FILES['ssl_files']['name']); $i++) {
        $name = $_FILES['ssl_files']['name'][$i];
        $tmp  = $_FILES['ssl_files']['tmp_name'][$i];
        $err  = $_FILES['ssl_files']['error'][$i];

        if ($err !== UPLOAD_ERR_OK) {
            $errors[] = "Upload fout voor " . htmlspecialchars($name);
            continue;
        }

        // Read file content for type detection (no extension required)
        $content = @file_get_contents($tmp);
        if ($content === false) {
            $errors[] = "Kon niet lezen: " . htmlspecialchars($name);
            continue;
        }

        // Detect type by content
        $is_cert = strpos($content, 'BEGIN CERTIFICATE') !== false || 
                   strpos($content, 'BEGIN TRUSTED CERTIFICATE') !== false;
        $is_key  = strpos($content, 'BEGIN OPENSSH PRIVATE KEY') !== false ||
                   strpos($content, 'BEGIN RSA PRIVATE KEY') !== false ||
                   strpos($content, 'BEGIN EC PRIVATE KEY') !== false ||
                   strpos($content, 'BEGIN PRIVATE KEY') !== false;

        if (!$is_cert && !$is_key) {
            $errors[] = htmlspecialchars($name) . " – ongeldig: geen certificate of private key gevonden.";
            continue;
        }

        // Clean filename (no extension required)
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($name));
        $dest = $ssl_dir . $filename;

        if (move_uploaded_file($tmp, $dest)) {
            chmod($dest, 0640);
            $type = $is_key ? 'key' : 'cert';
            $saved[] = htmlspecialchars($filename) . " (" . $type . ")";
        } else {
            $errors[] = "Kon niet opslaan: " . htmlspecialchars($filename) . " (controleer map-rechten)";
        }
    }

    $msg = '';
    if (!empty($saved)) {
        $msg .= "<div class='succes'>✓ Opgeslagen: " . implode(', ', $saved) . ". Apache wordt automatisch herladen zodra de SSL-map wijzigt.</div>";
    }
    if (!empty($errors)) {
        $msg .= "<div class='error'>✗ " . implode('<br>', $errors) . "</div>";
    }

    $_SESSION['shellmsg'] = $msg;
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

if (CERT_UPLOAD) {
    // Toon huidige certificaten in de SSL map
    $ssl_dir = '/etc/apache2/ssl/';
    $cert_files = [];
    if (is_dir($ssl_dir)) {
        foreach (new DirectoryIterator($ssl_dir) as $f) {
            if (!$f->isDot() && $f->isFile()) $cert_files[] = $f->getFilename();
        }
        natcasesort($cert_files);
    }
?>

            <h2>SSL Certificaten</h2>

            <form id="cert-upload" action="" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="cert_upload">
                <p>
                    <label for="ssl_files"><strong>Upload certificaten en keys:</strong></label><br>
                    <small>Accepteert alle bestanden. Detectie gebeurt server-side op inhoud (geen extensie vereist).</small>
                </p>
                <input type="file" id="ssl_files" name="ssl_files[]" multiple required>
                <button type="submit">UPLOADEN &amp; APACHE HERLADEN</button>
            </form>

<?php if (!empty($cert_files)) { ?>
            <p><strong>Huidige bestanden in /etc/apache2/ssl/:</strong><br>
                <code><?= implode('<br>', array_map('htmlspecialchars', $cert_files)) ?></code>
            </p>
<?php } else { ?>
            <p><em>Nog geen certificaten in /etc/apache2/ssl/.</em></p>
<?php } ?>

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
