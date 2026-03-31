function p(folder) {
    let message = prompt("Commit message", "Inleveren opdracht");

    document.location.href="?p=" + folder + "&m=" + message;
}

function d(folder) {
    if (confirm("Project " + folder + " verwijderen?")) {
        document.location.href="?d=" + folder;
    }
}

function dssl(filename) {
    if (confirm(decodeURIComponent(filename) + " verwijderen?")) {
        window.location.href = "?dssl=" + filename;
    }
}

function cloneStart(form) {
    var btn = form.querySelector('#clone-btn');
    var input = form.querySelector('#repo');
    if (!input.value.trim()) return false;
    btn.disabled = true;
    btn.textContent = 'CLONEN...';
    btn.style.opacity = '0.6';
    btn.style.cursor = 'wait';
    form.style.cursor = 'wait';
    return true;
}

function openSSLModal() {
    document.getElementById('ssl-modal').style.display = 'block';
}

function closeSSLModal() {
    document.getElementById('ssl-modal').style.display = 'none';
}

function openVhostModal(link) {
    var modal = document.getElementById('vhost-modal');
    var folder = link.dataset.folder || '';
    var hostname = link.dataset.hostname || '';

    document.getElementById('vhost-project-name').textContent = folder;
    document.getElementById('vhost-project-folder').value = folder;
    document.getElementById('vhost-hostname').value = hostname;
    document.getElementById('vhost-action').value = 'save_vhost';
    document.getElementById('vhost-delete-btn').style.display = hostname ? 'inline-block' : 'none';

    modal.style.display = 'block';
}

function closeVhostModal() {
    document.getElementById('vhost-modal').style.display = 'none';
}

function submitDeleteVhost() {
    if (!confirm('Deze vhost verwijderen?')) {
        return false;
    }

    document.getElementById('vhost-action').value = 'delete_vhost';
    return true;
}

window.onclick = function(event) {
    var sslModal = document.getElementById('ssl-modal');
    var vhostModal = document.getElementById('vhost-modal');

    if (event.target === sslModal) {
        sslModal.style.display = 'none';
    }

    if (event.target === vhostModal) {
        vhostModal.style.display = 'none';
    }
}
