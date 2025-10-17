// Fonction : Charger dynamiquement les EC (cours) attribués à l'enseignant
document.addEventListener("DOMContentLoaded", function () {
    const ecContainer = document.querySelector("#ecContainer");

    if (ecContainer) {
        fetch("get_ec_enseignant.php")
            .then(response => response.json())
            .then(data => {
                data.forEach(ec => {
                    const card = document.createElement("div");
                    card.className = "ec-card";
                    card.innerHTML = `
                        <h3>${ec.nom_ec}</h3>
                        <p><strong>Niveau :</strong> ${ec.niveau}</p>
                        <p><strong>Division :</strong> ${ec.division}</p>
                        <button onclick="ouvrirFicheNote(${ec.id})">Voir fiche de notes</button>
                    `;
                    ecContainer.appendChild(card);
                });
            })
            .catch(error => console.error("Erreur chargement EC:", error));
    }
});

// Fonction : Ouvrir la fiche de notes pour un EC donné
function ouvrirFicheNote(ec_id) {
    window.location.href = `fiche_notes.php?ec_id=${ec_id}`;
}

// Fonction : Enregistrer les notes saisies
function enregistrerNotes(ec_id) {
    const lignes = document.querySelectorAll(".ligne-note");
    const notes = [];

    lignes.forEach(ligne => {
        const idEtudiant = ligne.dataset.id;
        const noteCc = ligne.querySelector(".note-cc").value;
        const noteTp = ligne.querySelector(".note-tp").value;
        const noteExam = ligne.querySelector(".note-exam").value;

        notes.push({
            etudiant_id: idEtudiant,
            cc: noteCc,
            tp: noteTp,
            examen: noteExam
        });
    });

    fetch("enregistrer_notes.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({
            ec_id: ec_id,
            notes: notes
        })
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.success) location.reload();
    })
    .catch(error => console.error("Erreur enregistrement notes:", error));
}

// Fonction : Confirmer ou rejeter une requête
function traiterRequete(requete_id, action) {
    fetch("traiter_requete.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: `id=${requete_id}&action=${action}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            document.querySelector(`#requete-${requete_id}`).remove();
        }
    })
    .catch(error => console.error("Erreur traitement requête:", error));
}
