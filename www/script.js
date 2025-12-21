function p(folder) {
    let message = prompt("Commit message", "Inleveren opdracht");

    document.location.href="?p=" + folder + "&m=" + message;
}

function d(folder) {
    if (confirm("Project " + folder + " verwijderen?")) {
        document.location.href="?d=" + folder;
    }
}