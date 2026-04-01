function p(folder) {
    let message = prompt("Commit message", "Inleveren opdracht");

    document.location.href = "?p=" + folder + "&m=" + message;
}

function d(folder) {
    if (confirm("Project " + folder + " verwijderen?")) {
        document.location.href = "?d=" + folder;
    }
}

function dssl(filename) {
    if (confirm(decodeURIComponent(filename) + " verwijderen?")) {
        window.location.href = "?dssl=" + filename;
    }
}

function cloneStart(form) {
    var btn = form.querySelector("#clone-btn");
    var input = form.querySelector("#repo");
    if (!input.value.trim()) return false;
    btn.disabled = true;
    btn.textContent = "CLONEN...";
    btn.style.opacity = "0.6";
    btn.style.cursor = "wait";
    form.style.cursor = "wait";
    return true;
}

function openModal(id) {
    var modal = document.getElementById(id);
    if (modal) {
        modal.style.display = "block";
    }
}

function closeModal(id) {
    var modal = document.getElementById(id);
    if (modal) {
        modal.style.display = "none";
    }
}

function openVhostModal() {
    var select = document.getElementById("vhost-project-folder");
    if (select) {
        syncVhostHostname(select);
    }
    openModal("vhost-modal");
}

function syncVhostHostname(select) {
    var hostnameInput = document.getElementById("vhost-hostname");
    if (!hostnameInput) {
        return;
    }

    var option = select.options[select.selectedIndex];
    hostnameInput.value = option ? (option.dataset.hostname || "") : "";
}

window.onclick = function(event) {
    if (event.target.classList.contains("modal")) {
        event.target.style.display = "none";
    }
};
