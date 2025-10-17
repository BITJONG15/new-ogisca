<?php
// ajax_handler.php

header('Content-Type: application/json');

$conn = new mysqli('localhost', 'root', '', 'gestion_academique');
if ($conn->connect_error) {
    echo json_encode(['success'=>false, 'message'=>"Erreur connexion BDD"]);
    exit;
}

function generateMatricule() {
    return 'MAT-' . date('Ymd-His');
}

$action = $_REQUEST['action'] ?? '';

if ($action === 'list') {
    $etudiants = [];
    $sql = "SELECT e.id, e.matricule, e.nom, e.prenom, e.sexe, e.date_naissance, e.email, e.telephone, e.adresse,
            d.nom AS division, n.nom AS niveau, e.division_id, e.niveau_id
            FROM etudiants e
            LEFT JOIN division d ON e.division_id = d.id
            LEFT JOIN niveau n ON e.niveau_id = n.id
            ORDER BY e.nom";
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) $etudiants[] = $row;
    echo json_encode(['success'=>true, 'etudiants'=>$etudiants]);
    exit;
}

if ($action === 'ajouter') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $sexe = $_POST['sexe'] ?? '';
    $date_naissance = $_POST['date_naissance'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    $division_id = intval($_POST['division_id'] ?? 0);
    $niveau_id = intval($_POST['niveau_id'] ?? 0);

    if (!$nom || !$prenom || !$sexe || !$date_naissance || !$email || !$telephone || !$adresse || !$division_id || !$niveau_id || !$mot_de_passe) {
        echo json_encode(['success'=>false, 'message'=>"Tous les champs sont obligatoires."]);
        exit;
    }

    $matricule = generateMatricule();
    $mdp_hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO etudiants (matricule, nom, prenom, sexe, date_naissance, email, telephone, adresse, mot_de_passe, user_id, created_at, niveau_id, division_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), ?, ?)");
    $stmt->bind_param("sssssssssii", $matricule, $nom, $prenom, $sexe, $date_naissance, $email, $telephone, $adresse, $mdp_hash, $niveau_id, $division_id);
    if ($stmt->execute()) {
        echo json_encode(['success'=>true, 'message'=>"Étudiant ajouté avec matricule $matricule"]);
    } else {
        echo json_encode(['success'=>false, 'message'=>"Erreur ajout : " . $stmt->error]);
    }
    $stmt->close();
    exit;
}

if ($action === 'modifier') {
    $id = intval($_POST['id'] ?? 0);
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $sexe = $_POST['sexe'] ?? '';
    $date_naissance = $_POST['date_naissance'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    $division_id = intval($_POST['division_id'] ?? 0);
    $niveau_id = intval($_POST['niveau_id'] ?? 0);

    if (!$id || !$nom || !$prenom || !$sexe || !$date_naissance || !$email || !$telephone || !$adresse || !$division_id || !$niveau_id) {
        echo json_encode(['success'=>false, 'message'=>"Tous les champs sont obligatoires."]);
        exit;
    }

    if ($mot_de_passe) {
        $mdp_hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE etudiants SET nom=?, prenom=?, sexe=?, date_naissance=?, email=?, telephone=?, adresse=?, mot_de_passe=?, niveau_id=?, division_id=? WHERE id=?");
        $stmt->bind_param("ssssssssiii", $nom, $prenom, $sexe, $date_naissance, $email, $telephone, $adresse, $mdp_hash, $niveau_id, $division_id, $id);
    } else {
        $stmt = $conn->prepare("UPDATE etudiants SET nom=?, prenom=?, sexe=?, date_naissance=?, email=?, telephone=?, adresse=?, niveau_id=?, division_id=? WHERE id=?");
        $stmt->bind_param("sssssssiii", $nom, $prenom, $sexe, $date_naissance, $email, $telephone, $adresse, $niveau_id, $division_id, $id);
    }
    if ($stmt->execute()) {
        echo json_encode(['success'=>true, 'message'=>"Étudiant modifié avec succès."]);
    } else {
        echo json_encode(['success'=>false, 'message'=>"Erreur modification : " . $stmt->error]);
    }
    $stmt->close();
    exit;
}

if ($action === 'supprimer') {
    $id = intval($_POST['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success'=>false, 'message'=>"ID invalide."]);
        exit;
    }
    $stmt = $conn->prepare("DELETE FROM etudiants WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo json_encode(['success'=>true, 'message'=>"Étudiant supprimé avec succès."]);
    } else {
        echo json_encode(['success'=>false, 'message'=>"Erreur suppression : " . $stmt->error]);
    }
    $stmt->close();
    exit;
}

echo json_encode(['success'=>false, 'message'=>"Action inconnue"]);
exit;
