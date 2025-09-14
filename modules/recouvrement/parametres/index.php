<?php
/**
 * Module Recouvrement - Paramètres
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';

// Vérifier l'authentification et les permissions
requireLogin();
requirePagePermissionFromDB('recouvrement', 'parametres/index', 'edit', '../../../dashboard.php');

$errors = [];
$success_message = '';

// Vérifier et créer la table des paramètres si nécessaire
try {
    $database->execute("
        CREATE TABLE IF NOT EXISTS recouvrement_parametres (
            id INT PRIMARY KEY AUTO_INCREMENT,
            cle VARCHAR(100) UNIQUE NOT NULL,
            valeur TEXT,
            description TEXT,
            type ENUM('text', 'number', 'boolean', 'select', 'textarea') DEFAULT 'text',
            options TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    
    // Insérer les paramètres par défaut s'ils n'existent pas
    $default_params = [
        ['qr_code_size', '200', 'Taille des QR codes (en pixels)', 'number', null],
        ['carte_validite_mois', '12', 'Durée de validité des cartes (en mois)', 'number', null],
        ['seuil_solvabilite_critique', '100000', 'Seuil critique de dette (en FC)', 'number', null],
        ['seuil_solvabilite_elevee', '50000', 'Seuil élevé de dette (en FC)', 'number', null],
        ['auto_generate_card_number', '1', 'Génération automatique des numéros de carte', 'boolean', null],
        ['card_number_prefix', 'CARD', 'Préfixe des numéros de carte', 'text', null],
        ['enable_sms_notifications', '0', 'Activer les notifications SMS', 'boolean', null],
        ['enable_email_notifications', '1', 'Activer les notifications email', 'boolean', null],
        ['default_payment_mode', 'especes', 'Mode de paiement par défaut', 'select', 'especes,cheque,virement,mobile_money,carte'],
        ['require_payment_reference', '0', 'Référence obligatoire pour les paiements', 'boolean', null],
        ['auto_update_solvability', '1', 'Mise à jour automatique de la solvabilité', 'boolean', null],
        ['backup_frequency_days', '7', 'Fréquence de sauvegarde (en jours)', 'number', null],
        ['max_debt_amount', '500000', 'Montant maximum de dette autorisé (en FC)', 'number', null],
        ['grace_period_days', '30', 'Période de grâce pour les paiements (en jours)', 'number', null],
        ['school_logo_path', '', 'Chemin vers le logo de l\'école', 'text', null],
        ['receipt_footer_text', 'Merci pour votre paiement', 'Texte de pied de page des reçus', 'textarea', null]
    ];
    
    foreach ($default_params as $param) {
        $existing = $database->query(
            "SELECT id FROM recouvrement_parametres WHERE cle = ?",
            [$param[0]]
        )->fetch();
        
        if (!$existing) {
            $database->execute(
                "INSERT INTO recouvrement_parametres (cle, valeur, description, type, options) VALUES (?, ?, ?, ?, ?)",
                $param
            );
        }
    }
} catch (Exception $e) {
    $errors[] = 'Erreur lors de l\'initialisation des paramètres : ' . $e->getMessage();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database->beginTransaction();
        
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'param_') === 0) {
                $param_key = substr($key, 6); // Enlever le préfixe 'param_'
                
                // Validation selon le type
                $param_info = $database->query(
                    "SELECT type FROM recouvrement_parametres WHERE cle = ?",
                    [$param_key]
                )->fetch();
                
                if ($param_info) {
                    $validated_value = $value;
                    
                    switch ($param_info['type']) {
                        case 'number':
                            $validated_value = is_numeric($value) ? $value : '0';
                            break;
                        case 'boolean':
                            $validated_value = isset($_POST[$key]) ? '1' : '0';
                            break;
                        default:
                            $validated_value = trim($value);
                            break;
                    }
                    
                    $database->execute(
                        "UPDATE recouvrement_parametres SET valeur = ?, updated_at = NOW() WHERE cle = ?",
                        [$validated_value, $param_key]
                    );
                }
            }
        }
        
        $database->commit();
        $success_message = 'Paramètres mis à jour avec succès.';
        
    } catch (Exception $e) {
        $database->rollback();
        $errors[] = 'Erreur lors de la mise à jour : ' . $e->getMessage();
    }
}

// Récupérer tous les paramètres
try {
    $parametres = $database->query(
        "SELECT * FROM recouvrement_parametres ORDER BY cle"
    )->fetchAll();
} catch (Exception $e) {
    $parametres = [];
    $errors[] = 'Erreur lors du chargement des paramètres : ' . $e->getMessage();
}

// Statistiques du système
try {
    $stats_system = [
        'total_cartes' => $database->query("SELECT COUNT(*) as count FROM cartes_eleves")->fetch()['count'] ?? 0,
        'total_presences' => $database->query("SELECT COUNT(*) as count FROM presences_qr")->fetch()['count'] ?? 0,
        'total_paiements' => $database->query("SELECT COUNT(*) as count FROM paiements")->fetch()['count'] ?? 0,
        'total_frais' => $database->query("SELECT COUNT(*) as count FROM frais_scolaires")->fetch()['count'] ?? 0,
        'db_size' => 0 // Sera calculé si possible
    ];
    
    // Tenter de calculer la taille de la base de données
    try {
        $db_info = $database->query("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS db_size_mb
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
            AND table_name IN ('cartes_eleves', 'presences_qr', 'paiements', 'frais_scolaires', 'solvabilite_eleves', 'recouvrement_parametres')
        ")->fetch();
        
        $stats_system['db_size'] = $db_info['db_size_mb'] ?? 0;
    } catch (Exception $e) {
        // Ignorer l'erreur si on ne peut pas calculer la taille
    }
    
} catch (Exception $e) {
    $stats_system = ['total_cartes' => 0, 'total_presences' => 0, 'total_paiements' => 0, 'total_frais' => 0, 'db_size' => 0];
}

$page_title = "Paramètres du Module Recouvrement";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-cogs me-2 text-secondary"></i>
        Paramètres du Module
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-info" onclick="resetToDefaults()">
                <i class="fas fa-undo me-1"></i>
                Valeurs par défaut
            </button>
        </div>
        <div class="btn-group me-2">
            <button type="button" class="btn btn-warning" onclick="exportConfig()">
                <i class="fas fa-download me-1"></i>
                Exporter Config
            </button>
        </div>
        <div class="btn-group">
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour
            </a>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <h6><i class="fas fa-exclamation-circle me-1"></i> Erreurs :</h6>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!empty($success_message)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>

<!-- Statistiques du système -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h6 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Statistiques du Système
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3 mb-3">
                        <h4 class="text-primary"><?php echo number_format($stats_system['total_cartes']); ?></h4>
                        <small class="text-muted">Cartes Générées</small>
                    </div>
                    <div class="col-md-3 mb-3">
                        <h4 class="text-info"><?php echo number_format($stats_system['total_presences']); ?></h4>
                        <small class="text-muted">Scans de Présence</small>
                    </div>
                    <div class="col-md-3 mb-3">
                        <h4 class="text-success"><?php echo number_format($stats_system['total_paiements']); ?></h4>
                        <small class="text-muted">Paiements Enregistrés</small>
                    </div>
                    <div class="col-md-3 mb-3">
                        <h4 class="text-warning"><?php echo number_format($stats_system['total_frais']); ?></h4>
                        <small class="text-muted">Frais Configurés</small>
                    </div>
                </div>
                <?php if ($stats_system['db_size'] > 0): ?>
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            Taille des données du module : <strong><?php echo $stats_system['db_size']; ?> MB</strong>
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Formulaire de paramètres -->
<form method="POST" class="needs-validation" novalidate>
    <div class="row">
        <!-- Paramètres des cartes QR -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-qrcode me-2"></i>
                        Paramètres des Cartes QR
                    </h6>
                </div>
                <div class="card-body">
                    <?php
                    $carte_params = array_filter($parametres, function($p) {
                        return in_array($p['cle'], ['qr_code_size', 'carte_validite_mois', 'auto_generate_card_number', 'card_number_prefix']);
                    });
                    ?>
                    
                    <?php foreach ($carte_params as $param): ?>
                        <div class="mb-3">
                            <label for="param_<?php echo $param['cle']; ?>" class="form-label">
                                <?php echo htmlspecialchars($param['description']); ?>
                            </label>
                            
                            <?php if ($param['type'] === 'boolean'): ?>
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input" 
                                           id="param_<?php echo $param['cle']; ?>" 
                                           name="param_<?php echo $param['cle']; ?>"
                                           <?php echo ($param['valeur'] == '1') ? 'checked' : ''; ?>>
                                </div>
                            <?php elseif ($param['type'] === 'number'): ?>
                                <input type="number" class="form-control" 
                                       id="param_<?php echo $param['cle']; ?>" 
                                       name="param_<?php echo $param['cle']; ?>"
                                       value="<?php echo htmlspecialchars($param['valeur']); ?>" min="0">
                            <?php elseif ($param['type'] === 'select' && $param['options']): ?>
                                <select class="form-select" 
                                        id="param_<?php echo $param['cle']; ?>" 
                                        name="param_<?php echo $param['cle']; ?>">
                                    <?php foreach (explode(',', $param['options']) as $option): ?>
                                        <option value="<?php echo htmlspecialchars($option); ?>"
                                                <?php echo ($param['valeur'] === $option) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(ucfirst($option)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($param['type'] === 'textarea'): ?>
                                <textarea class="form-control" 
                                          id="param_<?php echo $param['cle']; ?>" 
                                          name="param_<?php echo $param['cle']; ?>" rows="3"><?php echo htmlspecialchars($param['valeur']); ?></textarea>
                            <?php else: ?>
                                <input type="text" class="form-control" 
                                       id="param_<?php echo $param['cle']; ?>" 
                                       name="param_<?php echo $param['cle']; ?>"
                                       value="<?php echo htmlspecialchars($param['valeur']); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Paramètres de solvabilité -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0">
                        <i class="fas fa-chart-pie me-2"></i>
                        Paramètres de Solvabilité
                    </h6>
                </div>
                <div class="card-body">
                    <?php
                    $solvabilite_params = array_filter($parametres, function($p) {
                        return in_array($p['cle'], ['seuil_solvabilite_critique', 'seuil_solvabilite_elevee', 'max_debt_amount', 'grace_period_days', 'auto_update_solvability']);
                    });
                    ?>
                    
                    <?php foreach ($solvabilite_params as $param): ?>
                        <div class="mb-3">
                            <label for="param_<?php echo $param['cle']; ?>" class="form-label">
                                <?php echo htmlspecialchars($param['description']); ?>
                            </label>
                            
                            <?php if ($param['type'] === 'boolean'): ?>
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input" 
                                           id="param_<?php echo $param['cle']; ?>" 
                                           name="param_<?php echo $param['cle']; ?>"
                                           <?php echo ($param['valeur'] == '1') ? 'checked' : ''; ?>>
                                </div>
                            <?php elseif ($param['type'] === 'number'): ?>
                                <input type="number" class="form-control" 
                                       id="param_<?php echo $param['cle']; ?>" 
                                       name="param_<?php echo $param['cle']; ?>"
                                       value="<?php echo htmlspecialchars($param['valeur']); ?>" min="0">
                            <?php else: ?>
                                <input type="text" class="form-control" 
                                       id="param_<?php echo $param['cle']; ?>" 
                                       name="param_<?php echo $param['cle']; ?>"
                                       value="<?php echo htmlspecialchars($param['valeur']); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Paramètres de paiement -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-money-bill me-2"></i>
                        Paramètres de Paiement
                    </h6>
                </div>
                <div class="card-body">
                    <?php
                    $paiement_params = array_filter($parametres, function($p) {
                        return in_array($p['cle'], ['default_payment_mode', 'require_payment_reference']);
                    });
                    ?>

                    <?php foreach ($paiement_params as $param): ?>
                        <div class="mb-3">
                            <label for="param_<?php echo $param['cle']; ?>" class="form-label">
                                <?php echo htmlspecialchars($param['description']); ?>
                            </label>

                            <?php if ($param['type'] === 'boolean'): ?>
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input"
                                           id="param_<?php echo $param['cle']; ?>"
                                           name="param_<?php echo $param['cle']; ?>"
                                           <?php echo ($param['valeur'] == '1') ? 'checked' : ''; ?>>
                                </div>
                            <?php elseif ($param['type'] === 'select' && $param['options']): ?>
                                <select class="form-select"
                                        id="param_<?php echo $param['cle']; ?>"
                                        name="param_<?php echo $param['cle']; ?>">
                                    <?php foreach (explode(',', $param['options']) as $option): ?>
                                        <option value="<?php echo htmlspecialchars($option); ?>"
                                                <?php echo ($param['valeur'] === $option) ? 'selected' : ''; ?>>
                                            <?php
                                            echo match($option) {
                                                'especes' => '💵 Espèces',
                                                'cheque' => '📝 Chèque',
                                                'virement' => '🏦 Virement',
                                                'mobile_money' => '📱 Mobile Money',
                                                'carte' => '💳 Carte',
                                                default => ucfirst($option)
                                            };
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" class="form-control"
                                       id="param_<?php echo $param['cle']; ?>"
                                       name="param_<?php echo $param['cle']; ?>"
                                       value="<?php echo htmlspecialchars($param['valeur']); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Paramètres de notifications -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-bell me-2"></i>
                        Paramètres de Notifications
                    </h6>
                </div>
                <div class="card-body">
                    <?php
                    $notification_params = array_filter($parametres, function($p) {
                        return in_array($p['cle'], ['enable_sms_notifications', 'enable_email_notifications']);
                    });
                    ?>

                    <?php foreach ($notification_params as $param): ?>
                        <div class="mb-3">
                            <label for="param_<?php echo $param['cle']; ?>" class="form-label">
                                <?php echo htmlspecialchars($param['description']); ?>
                            </label>

                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input"
                                       id="param_<?php echo $param['cle']; ?>"
                                       name="param_<?php echo $param['cle']; ?>"
                                       <?php echo ($param['valeur'] == '1') ? 'checked' : ''; ?>>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            Les notifications permettent d'informer automatiquement les parents des paiements et de la solvabilité.
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Paramètres système -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-server me-2"></i>
                        Paramètres Système
                    </h6>
                </div>
                <div class="card-body">
                    <?php
                    $system_params = array_filter($parametres, function($p) {
                        return in_array($p['cle'], ['backup_frequency_days', 'school_logo_path']);
                    });
                    ?>

                    <?php foreach ($system_params as $param): ?>
                        <div class="mb-3">
                            <label for="param_<?php echo $param['cle']; ?>" class="form-label">
                                <?php echo htmlspecialchars($param['description']); ?>
                            </label>

                            <?php if ($param['type'] === 'number'): ?>
                                <input type="number" class="form-control"
                                       id="param_<?php echo $param['cle']; ?>"
                                       name="param_<?php echo $param['cle']; ?>"
                                       value="<?php echo htmlspecialchars($param['valeur']); ?>" min="1">
                            <?php else: ?>
                                <input type="text" class="form-control"
                                       id="param_<?php echo $param['cle']; ?>"
                                       name="param_<?php echo $param['cle']; ?>"
                                       value="<?php echo htmlspecialchars($param['valeur']); ?>"
                                       placeholder="<?php echo ($param['cle'] === 'school_logo_path') ? 'uploads/logo.png' : ''; ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Paramètres des reçus -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-receipt me-2"></i>
                        Paramètres des Reçus
                    </h6>
                </div>
                <div class="card-body">
                    <?php
                    $receipt_params = array_filter($parametres, function($p) {
                        return in_array($p['cle'], ['receipt_footer_text']);
                    });
                    ?>

                    <?php foreach ($receipt_params as $param): ?>
                        <div class="mb-3">
                            <label for="param_<?php echo $param['cle']; ?>" class="form-label">
                                <?php echo htmlspecialchars($param['description']); ?>
                            </label>

                            <textarea class="form-control"
                                      id="param_<?php echo $param['cle']; ?>"
                                      name="param_<?php echo $param['cle']; ?>"
                                      rows="3"><?php echo htmlspecialchars($param['valeur']); ?></textarea>
                        </div>
                    <?php endforeach; ?>

                    <div class="alert alert-warning">
                        <small>
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Ce texte apparaîtra en bas de tous les reçus de paiement générés.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Boutons d'action -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <button type="button" class="btn btn-outline-secondary" onclick="window.location.reload()">
                                <i class="fas fa-undo me-1"></i>
                                Annuler
                            </button>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>
                                Enregistrer les Paramètres
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function resetToDefaults() {
    if (confirm('Êtes-vous sûr de vouloir restaurer tous les paramètres aux valeurs par défaut ?')) {
        alert('Fonctionnalité à implémenter : Reset aux valeurs par défaut');
    }
}

function exportConfig() {
    const config = {};
    const inputs = document.querySelectorAll('[name^="param_"]');

    inputs.forEach(input => {
        const key = input.name.replace('param_', '');
        if (input.type === 'checkbox') {
            config[key] = input.checked ? '1' : '0';
        } else {
            config[key] = input.value;
        }
    });

    const dataStr = JSON.stringify(config, null, 2);
    const dataBlob = new Blob([dataStr], {type: 'application/json'});
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'recouvrement_config_' + new Date().toISOString().split('T')[0] + '.json';
    link.click();
    URL.revokeObjectURL(url);
}
</script>

<?php include '../../../includes/footer.php'; ?>

