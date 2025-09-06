<?php
/**
 * Script d'installation du module Carte d'Élève
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Vérifier que l'utilisateur est administrateur
if (!isLoggedIn() || getCurrentUser()['role'] !== 'admin') {
    die('Accès refusé. Seuls les administrateurs peuvent installer des modules.');
}

$page_title = "Installation - Module Carte d'Élève";
$current_module = 'cartes_eleves';

$errors = [];
$success = [];

// Traitement de l'installation
if ($_POST && isset($_POST['install'])) {
    try {
        $database->beginTransaction();
        
        // 1. Créer les tables
        $sql_file = '../../database/migrations/create_cartes_eleves_table.sql';
        if (file_exists($sql_file)) {
            $sql_content = file_get_contents($sql_file);
            $statements = explode(';', $sql_content);
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    $database->exec($statement);
                }
            }
            $success[] = "Tables créées avec succès";
        } else {
            throw new Exception("Fichier de migration non trouvé");
        }
        
        // 2. Créer les permissions
        $permissions = [
            'cartes_eleves.view' => 'Voir les cartes d\'élèves',
            'cartes_eleves.manage' => 'Gérer les cartes d\'élèves',
            'cartes_eleves.print' => 'Imprimer les cartes d\'élèves',
            'cartes_eleves.scan' => 'Scanner les QR Codes',
            'cartes_eleves.settings' => 'Modifier les paramètres des cartes'
        ];
        
        foreach ($permissions as $permission => $description) {
            $database->execute(
                "INSERT IGNORE INTO permissions (nom, description, module) VALUES (?, ?, 'cartes_eleves')",
                [$permission, $description]
            );
        }
        $success[] = "Permissions créées avec succès";
        
        // 3. Assigner les permissions aux rôles
        $role_permissions = [
            'admin' => ['cartes_eleves.view', 'cartes_eleves.manage', 'cartes_eleves.print', 'cartes_eleves.scan', 'cartes_eleves.settings'],
            'secretaire' => ['cartes_eleves.view', 'cartes_eleves.manage', 'cartes_eleves.print', 'cartes_eleves.scan'],
            'enseignant' => ['cartes_eleves.view', 'cartes_eleves.scan'],
            'comptable' => ['cartes_eleves.view', 'cartes_eleves.scan']
        ];
        
        foreach ($role_permissions as $role => $perms) {
            foreach ($perms as $permission) {
                $database->execute(
                    "INSERT IGNORE INTO role_permissions (role, permission) VALUES (?, ?)",
                    [$role, $permission]
                );
            }
        }
        $success[] = "Permissions assignées aux rôles";
        
        // 4. Créer le répertoire pour les logos
        $logo_dir = '../../uploads/logos';
        if (!is_dir($logo_dir)) {
            mkdir($logo_dir, 0755, true);
            $success[] = "Répertoire logos créé";
        }
        
        // 5. Générer les cartes pour les élèves existants
        $current_year = $database->query("SELECT * FROM annees_scolaires WHERE status = 'active'")->fetch();
        if ($current_year) {
            $students = $database->query(
                "SELECT e.id FROM eleves e 
                 JOIN inscriptions i ON e.id = i.eleve_id 
                 WHERE i.annee_scolaire_id = ? AND i.status = 'inscrit'",
                [$current_year['id']]
            )->fetchAll();
            
            $generated_count = 0;
            foreach ($students as $student) {
                require_once 'auto-generate.php';
                $carte_id = autoGenerateStudentCard($student['id'], $current_year['id']);
                if ($carte_id) {
                    $generated_count++;
                }
            }
            $success[] = "$generated_count carte(s) générée(s) pour les élèves existants";
        }
        
        $database->commit();
        
        // Log de l'installation
        logAction('system', 'Installation du module Carte d\'Élève');
        
        $success[] = "Module installé avec succès !";
        
    } catch (Exception $e) {
        $database->rollback();
        $errors[] = "Erreur lors de l'installation : " . $e->getMessage();
    }
}

// Vérifier si le module est déjà installé
$is_installed = false;
try {
    $result = $database->query("SHOW TABLES LIKE 'cartes_eleves'")->fetch();
    $is_installed = !empty($result);
} catch (Exception $e) {
    // Table n'existe pas
}

include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="../dashboard.php">Tableau de bord</a></li>
                        <li class="breadcrumb-item active">Installation - Carte d'Élève</li>
                    </ol>
                </div>
                <h4 class="page-title">Installation du Module Carte d'Élève</h4>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-danger">
                <h5><i class="mdi mdi-alert-circle me-2"></i>Erreurs</h5>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-success">
                <h5><i class="mdi mdi-check-circle me-2"></i>Succès</h5>
                <ul class="mb-0">
                    <?php foreach ($success as $msg): ?>
                    <li><?= htmlspecialchars($msg) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Installation du Module</h5>
                </div>
                <div class="card-body">
                    <?php if ($is_installed): ?>
                    <div class="alert alert-info">
                        <h5><i class="mdi mdi-information me-2"></i>Module déjà installé</h5>
                        <p>Le module Carte d'Élève est déjà installé sur ce système.</p>
                        <a href="index.php" class="btn btn-primary">
                            <i class="mdi mdi-arrow-right me-1"></i> Accéder au module
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="mb-4">
                        <h6>Ce module va installer :</h6>
                        <ul>
                            <li><strong>Tables de base de données</strong> : cartes_eleves, cartes_eleves_historique, parametres_cartes, logs_scan_cartes</li>
                            <li><strong>Permissions</strong> : cartes_eleves.view, cartes_eleves.manage, cartes_eleves.print, cartes_eleves.scan, cartes_eleves.settings</li>
                            <li><strong>Intégration</strong> : Avec les modules d'inscription, présences et paiements</li>
                            <li><strong>Génération automatique</strong> : Cartes pour tous les élèves existants</li>
                        </ul>
                    </div>

                    <div class="mb-4">
                        <h6>Fonctionnalités du module :</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <ul>
                                    <li>Génération automatique de cartes d'élèves</li>
                                    <li>QR Code pour pointage de présence</li>
                                    <li>Consultation du solde des frais</li>
                                    <li>Impression PDF et PVC</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul>
                                    <li>Design personnalisable</li>
                                    <li>Scanner QR Code intégré</li>
                                    <li>Gestion des statuts des cartes</li>
                                    <li>Archivage automatique</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <form method="POST">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="confirm_install" required>
                            <label class="form-check-label" for="confirm_install">
                                Je confirme que je veux installer le module Carte d'Élève
                            </label>
                        </div>
                        
                        <button type="submit" name="install" class="btn btn-primary">
                            <i class="mdi mdi-download me-1"></i> Installer le module
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Prérequis</h5>
                </div>
                <div class="card-body">
                    <h6>Base de données</h6>
                    <ul class="list-unstyled">
                        <li><i class="mdi mdi-check text-success me-1"></i> MySQL 5.7+</li>
                        <li><i class="mdi mdi-check text-success me-1"></i> Tables élèves, classes, inscriptions</li>
                    </ul>

                    <h6 class="mt-3">Serveur web</h6>
                    <ul class="list-unstyled">
                        <li><i class="mdi mdi-check text-success me-1"></i> PHP 7.4+</li>
                        <li><i class="mdi mdi-check text-success me-1"></i> Extension GD</li>
                        <li><i class="mdi mdi-check text-success me-1"></i> Extension PDO</li>
                    </ul>

                    <h6 class="mt-3">Bibliothèques</h6>
                    <ul class="list-unstyled">
                        <li><i class="mdi mdi-check text-success me-1"></i> TCPDF (inclus)</li>
                        <li><i class="mdi mdi-alert text-warning me-1"></i> phpqrcode (recommandé)</li>
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Support</h5>
                </div>
                <div class="card-body">
                    <p>Pour toute question ou problème :</p>
                    <ul class="list-unstyled">
                        <li><i class="mdi mdi-file-document me-1"></i> <a href="../docs/CARTES-ELEVES.md" target="_blank">Documentation</a></li>
                        <li><i class="mdi mdi-help-circle me-1"></i> <a href="../support.php">Support technique</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
