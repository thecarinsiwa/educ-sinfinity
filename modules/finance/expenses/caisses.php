<?php
/**
 * Module de gestion financière - Gestion des caisses
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/permissions-pages.php';
require_once '../../../includes/ui-permissions.php';

// Vérifier l'authentification et les permissions
requireLogin(); 

// Vérifier l'accès à cette page
requirePagePermissionFromDB('finance', 'expenses/caisses', 'read', '../../dashboard.php');
    

$page_title = 'Gestion des Caisses';

// Obtenir l'année scolaire actuelle
$current_year = getCurrentAcademicYear();

// Obtenir la devise par défaut
$devise_par_defaut = getDefaultCurrency();

// Vérifier et créer les tables nécessaires
try {
    // Table des caisses
    $table_caisses = $database->query("SHOW TABLES LIKE 'caisses'")->fetch();
    if (!$table_caisses) {
        $create_caisses = "
            CREATE TABLE caisses (
                id INT PRIMARY KEY AUTO_INCREMENT,
                nom VARCHAR(100) NOT NULL,
                description TEXT,
                solde_initial DECIMAL(15,2) DEFAULT 0.00,
                devise_id INT NOT NULL,
                statut ENUM('active', 'inactive') DEFAULT 'active',
                annee_scolaire_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (devise_id) REFERENCES devises(id),
                FOREIGN KEY (annee_scolaire_id) REFERENCES annees_scolaires(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $database->execute($create_caisses);
    }

    // Table des sessions de caisse
    $table_sessions = $database->query("SHOW TABLES LIKE 'sessions_caisse'")->fetch();
    if (!$table_sessions) {
        $create_sessions = "
            CREATE TABLE sessions_caisse (
                id INT PRIMARY KEY AUTO_INCREMENT,
                caisse_id INT NOT NULL,
                user_id INT NOT NULL,
                date_ouverture DATETIME NOT NULL,
                date_fermeture DATETIME NULL,
                solde_ouverture DECIMAL(15,2) NOT NULL,
                solde_fermeture DECIMAL(15,2) NULL,
                statut ENUM('ouverte', 'fermee') DEFAULT 'ouverte',
                observation_ouverture TEXT,
                observation_fermeture TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (caisse_id) REFERENCES caisses(id),
                FOREIGN KEY (user_id) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $database->execute($create_sessions);
    }

    // Table des mouvements de caisse
    $table_mouvements = $database->query("SHOW TABLES LIKE 'mouvements_caisse'")->fetch();
    if (!$table_mouvements) {
        $create_mouvements = "
            CREATE TABLE mouvements_caisse (
                id INT PRIMARY KEY AUTO_INCREMENT,
                session_caisse_id INT NOT NULL,
                type_mouvement ENUM('entree', 'sortie') NOT NULL,
                categorie ENUM('paiement_eleve', 'don', 'subvention', 'depense_ecole', 'retrait', 'versement', 'autre') NOT NULL,
                libelle VARCHAR(255) NOT NULL,
                description TEXT,
                montant DECIMAL(15,2) NOT NULL,
                devise_id INT NOT NULL,
                reference VARCHAR(100),
                date_mouvement DATETIME NOT NULL,
                user_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (session_caisse_id) REFERENCES sessions_caisse(id),
                FOREIGN KEY (devise_id) REFERENCES devises(id),
                FOREIGN KEY (user_id) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $database->execute($create_mouvements);
    }
} catch (Exception $e) {
    showMessage('error', 'Erreur lors de la création des tables : ' . $e->getMessage());
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_caisse':
                $nom = sanitizeInput($_POST['nom']);
                $description = sanitizeInput($_POST['description']);
                $solde_initial = (float)($_POST['solde_initial']);
                $devise_id = (int)($_POST['devise_id']);
                
                if (empty($nom) || !$devise_id) {
                    showMessage('error', 'Le nom et la devise sont obligatoires.');
                } else {
                    try {
                        $database->execute(
                            "INSERT INTO caisses (nom, description, solde_initial, devise_id, annee_scolaire_id) VALUES (?, ?, ?, ?, ?)",
                            [$nom, $description, $solde_initial, $devise_id, $current_year['id']]
                        );
                        showMessage('success', 'Caisse créée avec succès.');
                    } catch (Exception $e) {
                        showMessage('error', 'Erreur lors de la création : ' . $e->getMessage());
                    }
                }
                break;

            case 'open_session':
                $caisse_id = (int)($_POST['caisse_id']);
                $solde_ouverture = (float)($_POST['solde_ouverture']);
                $observation = sanitizeInput($_POST['observation']);
                
                // Vérifier qu'il n'y a pas de session ouverte pour cette caisse
                $session_ouverte = $database->query(
                    "SELECT id FROM sessions_caisse WHERE caisse_id = ? AND statut = 'ouverte'",
                    [$caisse_id]
                )->fetch();
                
                if ($session_ouverte) {
                    showMessage('error', 'Une session est déjà ouverte pour cette caisse.');
                } else {
                    try {
                        $database->execute(
                            "INSERT INTO sessions_caisse (caisse_id, user_id, date_ouverture, solde_ouverture, observation_ouverture) VALUES (?, ?, NOW(), ?, ?)",
                            [$caisse_id, $_SESSION['user_id'], $solde_ouverture, $observation]
                        );
                        showMessage('success', 'Session de caisse ouverte avec succès.');
                    } catch (Exception $e) {
                        showMessage('error', 'Erreur lors de l\'ouverture : ' . $e->getMessage());
                    }
                }
                break;

            case 'close_session':
                $session_id = (int)($_POST['session_id']);
                $solde_fermeture = (float)($_POST['solde_fermeture']);
                $observation = sanitizeInput($_POST['observation']);
                
                try {
                    $database->execute(
                        "UPDATE sessions_caisse SET date_fermeture = NOW(), solde_fermeture = ?, observation_fermeture = ?, statut = 'fermee' WHERE id = ?",
                        [$solde_fermeture, $observation, $session_id]
                    );
                    showMessage('success', 'Session de caisse fermée avec succès.');
                } catch (Exception $e) {
                    showMessage('error', 'Erreur lors de la fermeture : ' . $e->getMessage());
                }
                break;
        }
        
        redirectTo('caisses.php');
    }
}

// Récupérer les caisses
$caisses = $database->query(
    "SELECT c.*, d.code as devise_code, d.symbole as devise_symbole,
            (SELECT COUNT(*) FROM sessions_caisse sc WHERE sc.caisse_id = c.id AND sc.statut = 'ouverte') as sessions_ouvertes
     FROM caisses c
     JOIN devises d ON c.devise_id = d.id
     WHERE c.annee_scolaire_id = ? AND c.statut = 'active'
     ORDER BY c.nom",
    [$current_year['id']]
)->fetchAll();

// Récupérer les sessions ouvertes
$sessions_ouvertes = $database->query(
    "SELECT sc.*, c.nom as caisse_nom, u.username as caissier,
            d.code as devise_code, d.symbole as devise_symbole
     FROM sessions_caisse sc
     JOIN caisses c ON sc.caisse_id = c.id
     JOIN users u ON sc.user_id = u.id
     JOIN devises d ON c.devise_id = d.id
     WHERE sc.statut = 'ouverte'
     ORDER BY sc.date_ouverture DESC"
)->fetchAll();

// Récupérer les devises actives
$devises = getActiveCurrencies();

include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-cash-register me-2"></i>
        Gestion des Caisses
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour aux dépenses
            </a>
        </div>
        <div class="btn-group me-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCaisseModal">
                <i class="fas fa-plus me-1"></i>
                Nouvelle Caisse
            </button>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-cog me-1"></i>
                Outils
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="historique_caisses.php">
                    <i class="fas fa-history me-2"></i>Historique des caisses
                </a></li>
                <li><a class="dropdown-item" href="integration_paiements.php">
                    <i class="fas fa-sync me-2"></i>Intégration paiements
                </a></li>
                <li><a class="dropdown-item" href="maintenance_caisses.php">
                    <i class="fas fa-tools me-2"></i>Maintenance
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Sessions ouvertes -->
<?php if (!empty($sessions_ouvertes)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-warning">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Sessions de Caisse Ouvertes
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Caisse</th>
                                <th>Caissier</th>
                                <th>Ouverte le</th>
                                <th>Solde d'ouverture</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions_ouvertes as $session): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($session['caisse_nom']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($session['caissier']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($session['date_ouverture'])); ?></td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo number_format($session['solde_ouverture'], 0, ',', ' '); ?> 
                                        <?php echo htmlspecialchars($session['devise_symbole']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="journal_caisse.php?session_id=<?php echo $session['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-book me-1"></i>Journal
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="closeSession(<?php echo $session['id']; ?>, '<?php echo htmlspecialchars($session['caisse_nom']); ?>')">
                                        <i class="fas fa-lock me-1"></i>Fermer
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Liste des caisses -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Caisses Disponibles
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($caisses)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-cash-register fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucune caisse configurée.</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCaisseModal">
                            <i class="fas fa-plus me-1"></i>
                            Créer la première caisse
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Description</th>
                                    <th>Solde Initial</th>
                                    <th>Devise</th>
                                    <th>Sessions Ouvertes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($caisses as $caisse): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($caisse['nom']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($caisse['description']); ?></td>
                                    <td>
                                        <span class="badge bg-success">
                                            <?php echo number_format($caisse['solde_initial'], 0, ',', ' '); ?> 
                                            <?php echo htmlspecialchars($caisse['devise_symbole']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <?php echo htmlspecialchars($caisse['devise_code']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($caisse['sessions_ouvertes'] > 0): ?>
                                            <span class="badge bg-warning">
                                                <?php echo $caisse['sessions_ouvertes']; ?> ouverte(s)
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Fermée</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($caisse['sessions_ouvertes'] == 0): ?>
                                            <button type="button" class="btn btn-sm btn-success" 
                                                    onclick="openSession(<?php echo $caisse['id']; ?>, '<?php echo htmlspecialchars($caisse['nom']); ?>')">
                                                <i class="fas fa-unlock me-1"></i>Ouvrir
                                            </button>
                                        <?php endif; ?>
                                        <a href="historique_caisses.php?caisse_id=<?php echo $caisse['id']; ?>" 
                                           class="btn btn-sm btn-outline-info">
                                            <i class="fas fa-history me-1"></i>Historique
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Création Caisse -->
<div class="modal fade" id="createCaisseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create_caisse">
                <div class="modal-header">
                    <h5 class="modal-title">Nouvelle Caisse</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nom" class="form-label">Nom de la caisse <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nom" name="nom" required>
                        <div class="form-text">Ex: Caisse Principale, Caisse Secondaire</div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="solde_initial" class="form-label">Solde initial</label>
                        <input type="number" class="form-control" id="solde_initial" name="solde_initial" 
                               step="0.01" min="0" value="0">
                    </div>
                    <div class="mb-3">
                        <label for="devise_id" class="form-label">Devise <span class="text-danger">*</span></label>
                        <select class="form-select" id="devise_id" name="devise_id" required>
                            <option value="">Sélectionner une devise</option>
                            <?php foreach ($devises as $devise): ?>
                                <option value="<?php echo $devise['id']; ?>">
                                    <?php echo htmlspecialchars($devise['code']); ?> - <?php echo htmlspecialchars($devise['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Créer la caisse</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ouverture Session -->
<div class="modal fade" id="openSessionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="open_session">
                <input type="hidden" name="caisse_id" id="open_caisse_id">
                <div class="modal-header">
                    <h5 class="modal-title">Ouvrir une Session de Caisse</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Caisse</label>
                        <input type="text" class="form-control" id="open_caisse_nom" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="solde_ouverture" class="form-label">Solde d'ouverture <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="solde_ouverture" name="solde_ouverture" 
                               step="0.01" min="0" required>
                        <div class="form-text">Montant en caisse au début de la session</div>
                    </div>
                    <div class="mb-3">
                        <label for="observation" class="form-label">Observation</label>
                        <textarea class="form-control" id="observation" name="observation" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">Ouvrir la session</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Fermeture Session -->
<div class="modal fade" id="closeSessionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="close_session">
                <input type="hidden" name="session_id" id="close_session_id">
                <div class="modal-header">
                    <h5 class="modal-title">Fermer la Session de Caisse</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Caisse</label>
                        <input type="text" class="form-control" id="close_caisse_nom" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="solde_fermeture" class="form-label">Solde de fermeture <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="solde_fermeture" name="solde_fermeture" 
                               step="0.01" min="0" required>
                        <div class="form-text">Montant en caisse à la fin de la session</div>
                    </div>
                    <div class="mb-3">
                        <label for="observation_fermeture" class="form-label">Observation</label>
                        <textarea class="form-control" id="observation_fermeture" name="observation" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">Fermer la session</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openSession(caisseId, caisseNom) {
    document.getElementById('open_caisse_id').value = caisseId;
    document.getElementById('open_caisse_nom').value = caisseNom;
    new bootstrap.Modal(document.getElementById('openSessionModal')).show();
}

function closeSession(sessionId, caisseNom) {
    document.getElementById('close_session_id').value = sessionId;
    document.getElementById('close_caisse_nom').value = caisseNom;
    new bootstrap.Modal(document.getElementById('closeSessionModal')).show();
}
</script>

<?php include '../../../includes/footer.php'; ?>

