<?php 

const PROJECTS_DIR = '/var/www/html/projects/';
const VHOSTS_DIR = '/etc/apache2/sites-enabled/';
const VHOST_FILE_PREFIX = 'tlamp-project-';

function decode_mount_path(string $path): string
{
    return strtr($path, [
        '\\040' => ' ',
        '\\011' => "\t",
        '\\012' => "\n",
        '\\134' => '\\',
    ]);
}

function convert_mount_source_to_local_path(string $source): string
{
    $source = decode_mount_path($source);

    if (preg_match('#^/(?:host_mnt|run/desktop/mnt/host)/([a-zA-Z])/(.+)$#', $source, $matches)) {
        return strtoupper($matches[1]) . ':\\' . str_replace('/', '\\', $matches[2]);
    }

    if (preg_match('#^/(?:host_mnt|run/desktop/mnt/host)/(Users/.+)$#', $source, $matches)) {
        return '/' . $matches[1];
    }

    if (preg_match('#^/(?:host_mnt|run/desktop/mnt/host)/(private/.+)$#', $source, $matches)) {
        return '/' . $matches[1];
    }

    if (preg_match('#^/(?:host_mnt|run/desktop/mnt/host)/(Volumes/.+)$#', $source, $matches)) {
        return '/' . $matches[1];
    }

    return $source;
}

function get_projects_local_path(): string
{
    $mountinfo = @file('/proc/self/mountinfo', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if (!is_array($mountinfo)) {
        return '';
    }

    foreach ($mountinfo as $line) {
        $segments = explode(' - ', $line, 2);
        if (count($segments) !== 2) {
            continue;
        }

        $left = preg_split('/\s+/', $segments[0]);
        $right = preg_split('/\s+/', $segments[1]);

        if (!isset($left[4], $right[1])) {
            continue;
        }

        if ($left[4] === rtrim(PROJECTS_DIR, '/')) {
            return rtrim(convert_mount_source_to_local_path($right[1]), "\\/") . DIRECTORY_SEPARATOR;
        }
    }

    return '';
}

function get_vhost_file_path(string $folder): string
{
    $slug = preg_replace('/[^a-z0-9._-]+/i', '-', strtolower($folder));
    $slug = trim((string) $slug, '-');

    if ($slug === '') {
        $slug = 'project';
    }

    return VHOSTS_DIR . VHOST_FILE_PREFIX . $slug . '-' . substr(md5($folder), 0, 8) . '.conf';
}

function build_vhost_config(string $folder, string $hostname): string
{
    $documentRoot = PROJECTS_DIR . $folder;
    $logName = preg_replace('/[^a-z0-9._-]+/i', '-', strtolower($folder));

    return <<<CONF
# TLAMP_PROJECT={$folder}
<VirtualHost *:80>
    ServerName {$hostname}
    DocumentRoot {$documentRoot}

    <Directory {$documentRoot}>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/{$logName}-error.log
    CustomLog \${APACHE_LOG_DIR}/{$logName}-access.log combined
</VirtualHost>

CONF;
}

function load_project_vhosts(): array
{
    $vhosts = [];

    if (!is_dir(VHOSTS_DIR)) {
        return $vhosts;
    }

    foreach (new DirectoryIterator(VHOSTS_DIR) as $file) {
        if ($file->isDot() || !$file->isFile() || $file->getFilename() === '.gitkeep') {
            continue;
        }

        $content = @file_get_contents($file->getPathname());
        if (!is_string($content)) {
            continue;
        }

        if (!preg_match('/^# TLAMP_PROJECT=(.+)$/m', $content, $projectMatch)) {
            continue;
        }

        if (!preg_match('/^\s*ServerName\s+([^\s#]+)\s*$/mi', $content, $hostMatch)) {
            continue;
        }

        $project = trim($projectMatch[1]);
        $vhosts[$project] = [
            'hostname' => trim($hostMatch[1]),
            'file' => $file->getPathname(),
        ];
    }

    return $vhosts;
}

function is_valid_hostname(string $hostname): bool
{
    return (bool) preg_match('/^(?=.{1,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $hostname);
}

// Openen in Visual Studio Code aan/uit
const OPEN_VSC = TRUE;

// GIT Clone functie aan/uit
const GIT_CLONE = TRUE;

// Folders deleten aan/uit
const ALLOW_DELETE = TRUE;

// SSL certificaten uploaden aan/uit
const CERT_UPLOAD = TRUE;


// Hieronder niets aan te passen...

$projects_local_path = get_projects_local_path();

session_start();

if (isset($_GET['warp'])) {
    $_POST['repo'] = 'https://github.com/danny-talland/js_warpspeed.git';
}

if (isset($_POST['action']) && $_POST['action'] === 'create_folder') {
    $folder = basename(trim((string) ($_POST['folder_name'] ?? '')));

    if ($folder === '' || preg_match('/[\\\\\\/]/', $folder)) {
        $_SESSION['shellmsg'] = "<div class='error'>Ongeldige mapnaam.</div>";
        header("Location:index.php");
        die();
    }

    $targetPath = PROJECTS_DIR . $folder;

    if (is_dir($targetPath)) {
        $_SESSION['shellmsg'] = "<div class='error'>Projectmap bestaat al.</div>";
        header("Location:index.php");
        die();
    }

    if (@mkdir($targetPath, 0775, true)) {
        $_SESSION['shellmsg'] = "<div class='succes'>Projectmap " . htmlspecialchars($folder, ENT_QUOTES, 'UTF-8') . " aangemaakt.</div>";
    } else {
        $_SESSION['shellmsg'] = "<div class='error'>Kon projectmap niet aanmaken.</div>";
    }

    header("Location:index.php");
    die();
}

if (isset($_POST['action']) && $_POST['action'] === 'clone' && isset($_POST['repo']) && !empty($_POST['repo'])) {
    $repo = trim($_POST['repo']);
    
    if (stripos($repo, "git clone ") === 0) {
        $repo = substr($repo, 10);
    }

    list($repo, $target) = explode(" ", $repo . " ");
    $requestedTarget = trim((string) ($_POST['target'] ?? ''));

    if ($requestedTarget !== '') {
        $target = $requestedTarget;
    }

    if (empty($target)) {
        $target = str_ireplace(".git", "", basename($_POST['repo']));
    }

    $targetName = basename($target);
    $target = PROJECTS_DIR . $targetName;

    $repo = escapeshellarg($repo);
    $target = escapeshellarg($target);

    $ssh_key = '/etc/apache2/ssl/id_ed25519';
    $ssh_key_content = @file_get_contents($ssh_key);

    // Placeholder or missing private key is a common local setup issue in this stack.
    if (!is_string($ssh_key_content) || stripos($ssh_key_content, 'BEGIN OPENSSH PRIVATE KEY') === false) {
        $_SESSION['shellmsg'] = "<div class='error'>Git clone geblokkeerd: geen geldige private key gevonden op /etc/apache2/ssl/id_ed25519.<br>Upload je SSH private key via het SSL Certificaten formulier.</div>";
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
        $_SESSION['shellmsg'] = "<div class='succes'>Repository gecloned naar /projects/" . htmlspecialchars($targetName, ENT_QUOTES, 'UTF-8') . "</div>";
    }

    header("Location:index.php");
    die();
}

if (isset($_POST['action']) && $_POST['action'] === 'save_vhost') {
    $folder = basename((string) ($_POST['project_folder'] ?? ''));
    $hostname = strtolower(trim((string) ($_POST['hostname'] ?? '')));

    if ($folder === '' || !is_dir(PROJECTS_DIR . $folder)) {
        $_SESSION['shellmsg'] = "<div class='error'>Ongeldige projectmap voor vhost.</div>";
        header("Location:index.php");
        die();
    }

    if (!is_valid_hostname($hostname)) {
        $_SESSION['shellmsg'] = "<div class='error'>Ongeldige hostname. Gebruik bijvoorbeeld project.local.</div>";
        header("Location:index.php");
        die();
    }

    $existingVhosts = load_project_vhosts();
    foreach ($existingVhosts as $project => $vhost) {
        if ($project !== $folder && strcasecmp($vhost['hostname'], $hostname) === 0) {
            $_SESSION['shellmsg'] = "<div class='error'>Hostname " . htmlspecialchars($hostname, ENT_QUOTES, 'UTF-8') . " is al in gebruik.</div>";
            header("Location:index.php");
            die();
        }
    }

    $filePath = $existingVhosts[$folder]['file'] ?? get_vhost_file_path($folder);
    $written = @file_put_contents($filePath, build_vhost_config($folder, $hostname));

    if ($written === false) {
        $_SESSION['shellmsg'] = "<div class='error'>Kon vhost-config niet opslaan.</div>";
    } else {
        $_SESSION['shellmsg'] = "<div class='succes'>Vhost opgeslagen voor " . htmlspecialchars($hostname, ENT_QUOTES, 'UTF-8') . ".</div>";
    }

    header("Location:index.php");
    die();
}

if (isset($_POST['action']) && $_POST['action'] === 'delete_vhost') {
    $folder = basename((string) ($_POST['project_folder'] ?? ''));
    $existingVhosts = load_project_vhosts();

    if ($folder === '' || !isset($existingVhosts[$folder])) {
        $_SESSION['shellmsg'] = "<div class='error'>Geen vhost gevonden om te verwijderen.</div>";
        header("Location:index.php");
        die();
    }

    if (@unlink($existingVhosts[$folder]['file'])) {
        $_SESSION['shellmsg'] = "<div class='succes'>Vhost verwijderd voor " . htmlspecialchars($folder, ENT_QUOTES, 'UTF-8') . ".</div>";
    } else {
        $_SESSION['shellmsg'] = "<div class='error'>Kon vhost-config niet verwijderen.</div>";
    }

    header("Location:index.php");
    die();
}

if (isset($_GET['d'])) {
    $folder = basename($_GET['d']);
    $target = escapeshellarg(PROJECTS_DIR . $folder);

    $shell = "rm -rf $target";
    $result = shell_exec($shell);

    header("Location:index.php");
    die();
}

if (isset($_GET['dssl'])) {
    $ssl_dir = '/etc/apache2/ssl/';
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', basename($_GET['dssl']));
    $target   = $ssl_dir . $filename;
    $real_ssl = realpath($ssl_dir);
    $real_tgt = realpath($target);

    if ($filename && $real_tgt && $real_ssl && strpos($real_tgt, $real_ssl . '/') === 0 && is_file($real_tgt)) {
        if (unlink($real_tgt)) {
            $_SESSION['shellmsg'] = "<div class='succes'>✓ " . htmlspecialchars($filename) . " verwijderd.</div>";
        } else {
            $_SESSION['shellmsg'] = "<div class='error'>✗ Kon " . htmlspecialchars($filename) . " niet verwijderen.</div>";
        }
    } else {
        $_SESSION['shellmsg'] = "<div class='error'>✗ Bestand niet gevonden: " . htmlspecialchars($filename) . "</div>";
    }

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
        $msg .= "<div class='succes'>✓ Opgeslagen: " . implode(', ', $saved) . "</div>";
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

if (CERT_UPLOAD) {
    // Toon huidige certificaten in de SSL map
    $ssl_dir = '/etc/apache2/ssl/';
    $cert_files = [];
    if (is_dir($ssl_dir)) {
        foreach (new DirectoryIterator($ssl_dir) as $f) {
            if ($f->isDot() || !$f->isFile() || $f->getFilename() === '.gitkeep') {
                continue;
            }
            $cert_files[] = $f->getFilename();
        }
        natcasesort($cert_files);
    }
?>

            <div id="ssl-modal" class="modal" style="display: none;">
                <div class="modal-content">
                    <span class="modal-close" onclick="closeModal('ssl-modal')">&times;</span>
                    <h2>Upload je private certificaten en keys</h2>
                    
                    <form id="cert-upload" action="" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="cert_upload">
                        <input type="file" id="ssl_files" name="ssl_files[]" multiple required>
                        <button type="submit">UPLOADEN</button>
                    </form>

                    <h3>Certificaten en keys:</h3>
<?php if (!empty($cert_files)) { ?>
                    <ul class="ssl-files">
<?php foreach ($cert_files as $cf) { ?>
                        <li>
                            <code><?= htmlspecialchars($cf) ?></code>
                            <a href="#" onclick="dssl('<?= rawurlencode($cf) ?>'); return false;" title="Verwijderen"><img class="icon" src="icon_delete.png" alt="Verwijderen"></a>
                        </li>
<?php } ?>
                    </ul>
<?php } else { ?>
                    <p><em>Nog geen certificaten.</em></p>
<?php } ?>
                </div>
            </div>

<?php
}
?>

            <div class="project-actions">
                <a href="#" class="action-tile" title="Nieuwe projectmap" onclick="openModal('new-project-modal'); return false;">
                    <img src="icon_plus.svg" alt="Nieuw project">
                </a>
<?php if (GIT_CLONE) { ?>
                <a href="#" class="action-tile" title="Repository clonen" onclick="openModal('clone-modal'); return false;">
                    <img src="icon_git_plus.svg" alt="Clone repository">
                </a>
<?php } ?>
                <a href="#" class="action-tile" title="Vhosts beheren" onclick="openVhostModal(); return false;">
                    <img src="icon_vhost.svg" alt="Vhost toevoegen">
                </a>
            </div>

             <h2>Projecten</h2>
<?php 

$items = [];
$projects_dir = PROJECTS_DIR;
$projectVhosts = load_project_vhosts();

if (is_dir($projects_dir)) {
    $it = new DirectoryIterator($projects_dir);

    foreach ($it as $f) {
        if ($f->isDot()) continue;
        if ($f->isDir()) {
            $items[] = $f->getFilename();
        }
    }
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
        $projectVhost = $projectVhosts[$folder]['hostname'] ?? '';
        $vsc = "";

        if (OPEN_VSC && $projects_local_path !== '') {
            $local = str_replace('\\', '/', $projects_local_path . $folder);
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

            <td><a target="_blank" href="http://localhost/projects/<?= htmlspecialchars($folder, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($folder, ENT_QUOTES, 'UTF-8') ?></a></td>
            <td class="project-hostname">
<?php if ($projectVhost !== '') { ?>
                <a target="_blank" href="http://<?= htmlspecialchars($projectVhost, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($projectVhost, ENT_QUOTES, 'UTF-8') ?></a>
<?php } ?>
            </td>
        </tr>
    <?php } ?>
        </table>
<?php } ?>

        <div id="new-project-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <span class="modal-close" onclick="closeModal('new-project-modal')">&times;</span>
                <h2>Nieuwe projectmap</h2>

                <form action="" method="post" class="modal-form">
                    <input type="hidden" name="action" value="create_folder">
                    <label for="folder_name">Mapnaam</label>
                    <input type="text" name="folder_name" id="folder_name" placeholder="mijn-project" required>
                    <button type="submit">AANMAKEN</button>
                </form>
            </div>
        </div>

<?php if (GIT_CLONE) { ?>
        <div id="clone-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <span class="modal-close" onclick="closeModal('clone-modal')">&times;</span>
                <h2>Clone repository</h2>

                <form id="clone" action="" method="post" onsubmit="cloneStart(this)" class="modal-form">
                    <input type="hidden" name="action" value="clone">
                    <label for="repo">Repository</label>
                    <input id="repo" name="repo" placeholder="https://github.com/user/repo.git" required>
                    <label for="clone_target">Mapnaam</label>
                    <input id="clone_target" name="target" placeholder="optioneel, anders repo-naam">
                    <button id="clone-btn" type="submit">CLONE</button>
                </form>

                <p class="modal-note">
                    <a href="#" onclick="closeModal('clone-modal'); openModal('ssl-modal'); return false;">Klik hier om je SSL key toe te voegen.</a>
                </p>
            </div>
        </div>
<?php } ?>

        <div id="vhost-modal" class="modal" style="display: none;">
            <div class="modal-content">
                <span class="modal-close" onclick="closeModal('vhost-modal')">&times;</span>
                <h2>Vhost beheren</h2>

                <form id="vhost-form" action="" method="post" class="modal-form">
                    <input type="hidden" name="action" value="save_vhost">
                    <label for="vhost-project-folder">Projectmap</label>
                    <select name="project_folder" id="vhost-project-folder" onchange="syncVhostHostname(this)" required>
                        <option value="">Kies een projectmap</option>
<?php foreach ($items as $folder) { ?>
                        <option value="<?= htmlspecialchars($folder, ENT_QUOTES, 'UTF-8') ?>" data-hostname="<?= htmlspecialchars($projectVhosts[$folder]['hostname'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($folder, ENT_QUOTES, 'UTF-8') ?></option>
<?php } ?>
                    </select>
                    <label for="vhost-hostname">Hostname</label>
                    <input type="text" name="hostname" id="vhost-hostname" placeholder="project.local" required>
                    <button type="submit">OPSLAAN</button>
                </form>

                <p class="modal-note">Vergeet ook je lokale hosts file niet aan te passen zodat de hostname naar `127.0.0.1` wijst.</p>

                <h3>Bestaande vhosts</h3>
<?php if (!empty($projectVhosts)) { ?>
                <ul class="vhost-files">
<?php foreach ($projectVhosts as $project => $vhost) { ?>
                    <li>
                        <span><strong><?= htmlspecialchars($project, ENT_QUOTES, 'UTF-8') ?></strong> - <?= htmlspecialchars($vhost['hostname'], ENT_QUOTES, 'UTF-8') ?></span>
                        <form action="" method="post">
                            <input type="hidden" name="action" value="delete_vhost">
                            <input type="hidden" name="project_folder" value="<?= htmlspecialchars($project, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="button-link" onclick="return confirm('Deze vhost verwijderen?')">Verwijderen</button>
                        </form>
                    </li>
<?php } ?>
                </ul>
<?php } else { ?>
                <p><em>Nog geen vhosts.</em></p>
<?php } ?>
            </div>
        </div>

        </main>
    </body>
</html>
