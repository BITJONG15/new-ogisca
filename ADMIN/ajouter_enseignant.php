<?php
session_start();

$conn = new mysqli('localhost', 'root', '', 'gestion_academique');
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

// Fonction pour sécuriser les données
function clean_input($data) {
    return htmlspecialchars(trim($data));
}

// Récupérer les données POST
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$matricule = clean_input($_POST['matricule'] ?? '');
$nom = clean_input($_POST['nom'] ?? '');
$prenom = clean_input($_POST['prenom'] ?? '');
$sexe = clean_input($_POST['sexe'] ?? '');
$titre = clean_input($_POST['titre'] ?? '');
$grade = clean_input($_POST['grade'] ?? '');
$specialite = clean_input($_POST['specialite'] ?? '');
$domaine_expertise = clean_input($_POST['domaine_expertise'] ?? '');
$email = clean_input($_POST['email'] ?? '');
$telephone = clean_input($_POST['telephone'] ?? '');
$mot_de_passe = $_POST['mot_de_passe'] ?? '';

// Gestion photo uploadée
$photoPath = '';
$uploadDir = 'uploads/photos_enseignants/'; // crée ce dossier avec droits écriture

if (!empty($_FILES['photo']['name'])) {
    $fileTmpPath = $_FILES['photo']['tmp_name'];
    $fileName = basename($_FILES['photo']['name']);
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png'];

    if (in_array($ext, $allowed)) {
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $newFileName = uniqid('photo_') . '.' . $ext;
        $destPath = $uploadDir . $newFileName;
        if (move_uploaded_file($fileTmpPath, $destPath)) {
            $photoPath = $destPath;
        }
    }
}

// Hash du mot de passe s'il est fourni
if (!empty($mot_de_passe)) {
    $mot_de_passe_hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
}

if ($id > 0) {
    // MODIFICATION

    // Requête de base
    $sql = "UPDATE enseignants SET
        matricule = ?,
        nom = ?,
        prenom = ?,
        sexe = ?,
        titre = ?,
        grade = ?,
        specialite = ?,
        domaine_expertise = ?,
        email = ?,
        telephone = ?";

    // Ajout conditionnel mot de passe
    if (!empty($mot_de_passe)) {
        $sql .= ", mot_de_passe = ?";
    }

    // Ajout conditionnel photo
    if (!empty($photoPath)) {
        $sql .= ", photo = ?";
    }

    $sql .= " WHERE id = ?";

    $stmt = $conn->prepare($sql);

    if (!empty($mot_de_passe) && !empty($photoPath)) {
        $stmt->bind_param(
            "sssssssssssisi",
            $matricule, $nom, $prenom, $sexe, $titre, $grade, $specialite, $domaine_expertise,
            $email, $telephone, $mot_de_passe_hash, $photoPath, $id
        );
    } elseif (!empty($mot_de_passe)) {
        $stmt->bind_param(
            "sssssssssssi",
            $matricule, $nom, $prenom, $sexe, $titre, $grade, $specialite, $domaine_expertise,
            $email, $telephone, $mot_de_passe_hash, $id
        );
    } elseif (!empty($photoPath)) {
        $stmt->bind_param(
            "sssssssssssi",
            $matricule, $nom, $prenom, $sexe, $titre, $grade, $specialite, $domaine_expertise,
            $email, $telephone, $photoPath, $id
        );
    } else {
        $stmt->bind_param(
            "ssssssssssi",
            $matricule, $nom, $prenom, $sexe, $titre, $grade, $specialite, $domaine_expertise,
            $email, $telephone, $id
        );
    }

    $stmt->execute();

    if ($stmt->affected_rows >= 0) {

        // Mise à jour de la table users (role = enseignant)
        if (!empty($mot_de_passe)) {
            // Met à jour mot de passe si modifié
            $sqlUser = "UPDATE users SET mot_de_passe = ?, role = 'enseignant' WHERE matricule = ?";
            $stmtUser = $conn->prepare($sqlUser);
            $stmtUser->bind_param("ss", $mot_de_passe_hash, $matricule);
            $stmtUser->execute();
            $stmtUser->close();
        } else {
            // Sinon juste update role au cas où
            $sqlUser = "UPDATE users SET role = 'enseignant' WHERE matricule = ?";
            $stmtUser = $conn->prepare($sqlUser);
            $stmtUser->bind_param("s", $matricule);
            $stmtUser->execute();
            $stmtUser->close();
        }

        header("Location: dashboard.php?success=1");
    } else {
        echo "Erreur lors de la mise à jour : " . $stmt->error;
    }

    $stmt->close();

} else {
    // AJOUT NOUVEL ENSEIGNANT

    if (empty($mot_de_passe)) {
        die("Le mot de passe est obligatoire lors de la création.");
    }

    $mot_de_passe_hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);

    $sql = "INSERT INTO enseignants
    (matricule, nom, prenom, sexe, titre, grade, specialite, domaine_expertise, email, telephone, mot_de_passe, photo, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "ssssssssssss",
        $matricule, $nom, $prenom, $sexe, $titre, $grade, $specialite, $domaine_expertise,
        $email, $telephone, $mot_de_passe_hash, $photoPath
    );

    if ($stmt->execute()) {
        // Insérer dans users
       $sqlUser = "INSERT INTO users (matricule, mot_de_passe, nom, prenom, role, date_creation) 
            VALUES (?, ?, ?, ?, 'enseignant', NOW())";
$stmtUser = $conn->prepare($sqlUser);
$stmtUser->bind_param("ssss", $matricule, $mot_de_passe_hash, $nom, $prenom);
$stmtUser->execute();
$stmtUser->close();


        header("Location: dashboard.php?success=1");
    } else {
        echo "Erreur lors de l'insertion : " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();
?>
