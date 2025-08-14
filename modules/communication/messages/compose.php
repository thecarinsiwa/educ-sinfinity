<?php
/**
 * Module Communication - Composer un message
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Vérifier et créer la table messages si nécessaire
try {
    $tables = $database->query("SHOW TABLES LIKE 'messages'")->fetch();
    if (!$tables) {
        $database->execute("
            CREATE TABLE IF NOT EXISTS messages (
                id INT PRIMARY KEY AUTO_INCREMENT,
                expediteur_id INT NOT NULL,
                destinataire_id INT NULL,
                destinataire_type ENUM('personnel', 'eleve', 'parent', 'classe', 'niveau', 'tous', 'custom') NOT NULL,
                destinataires_custom TEXT NULL,
                sujet VARCHAR(255) NOT NULL,
                contenu TEXT NOT NULL,
                type_message ENUM('info', 'urgent', 'rappel', 'felicitation') DEFAULT 'info',
                priorite ENUM('basse', 'normale', 'haute', 'urgente') DEFAULT 'normale',
                date_envoi DATETIME NULL,
                programme TINYINT(1) DEFAULT 0,
                date_programmee DATETIME NULL,
                status ENUM('brouillon', 'programme', 'envoye', 'lu', 'archive') DEFAULT 'brouillon',
                accuse_reception TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
    } else {
        // Vérifier si la colonne date_programmee existe et peut accepter NULL
        $columns = $database->query("DESCRIBE messages")->fetchAll();
        $date_programmee_exists = false;
        foreach ($columns as $column) {
            if ($column['Field'] === 'date_programmee') {
                $date_programmee_exists = true;
                break;
            }
        }

        if (!$date_programmee_exists) {
            $database->execute("ALTER TABLE messages ADD COLUMN date_programmee DATETIME NULL");
        }
    }
} catch (Exception $e) {
    // Ignorer les erreurs de création de table en mode silencieux
}

// Vérifier l'authentification et les permissions
requireLogin();
if (!checkPermission('communication')) {
    showMessage('error', 'Accès refusé à cette page.');
    redirectTo('../index.php');
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'send_message') {
            $destinataire_type = $_POST['destinataire_type'] ?? '';
            $destinataire_id = intval($_POST['destinataire_id'] ?? 0);
            $destinataires_custom = $_POST['destinataires_custom'] ?? '';
            $sujet = trim($_POST['sujet'] ?? '');
            $contenu = trim($_POST['contenu'] ?? '');
            $type_message = $_POST['type_message'] ?? 'info';
            $priorite = $_POST['priorite'] ?? 'normale';
            $programme = isset($_POST['programme']) ? 1 : 0;
            $date_programmee = !empty($_POST['date_programmee']) ? $_POST['date_programmee'] : null;
            $accuse_reception = isset($_POST['accuse_reception']) ? 1 : 0;
            
            // Validation
            if (empty($sujet) || empty($contenu)) {
                throw new Exception('Le sujet et le contenu sont obligatoires.');
            }
            
            if (!in_array($destinataire_type, ['user', 'classe', 'all_students', 'all_teachers', 'all_parents', 'custom'])) {
                throw new Exception('Type de destinataire invalide.');
            }
            
            if ($destinataire_type === 'user' && !$destinataire_id) {
                throw new Exception('Veuillez sélectionner un destinataire.');
            }
            
            if ($destinataire_type === 'custom' && empty($destinataires_custom)) {
                throw new Exception('Veuillez spécifier les destinataires personnalisés.');
            }
            
            if ($programme && empty($date_programmee)) {
                throw new Exception('Veuillez spécifier la date de programmation.');
            }
            
            if ($programme && strtotime($date_programmee) <= time()) {
                throw new Exception('La date de programmation doit être dans le futur.');
            }
            
            // Déterminer le statut
            $status = $programme ? 'programme' : 'envoye';
            $date_envoi = $programme ? null : date('Y-m-d H:i:s');

            // S'assurer que date_programmee est NULL si pas programmé
            if (!$programme) {
                $date_programmee = null;
            }

            // Créer le message
            $database->execute(
                "INSERT INTO messages (
                    expediteur_id, destinataire_id, destinataire_type, destinataires_custom,
                    sujet, contenu, type_message, priorite, date_envoi, programme,
                    date_programmee, status, accuse_reception, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $_SESSION['user_id'], $destinataire_id, $destinataire_type, $destinataires_custom,
                    $sujet, $contenu, $type_message, $priorite, $date_envoi, $programme,
                    $date_programmee, $status, $accuse_reception
                ]
            );
            
            if ($programme) {
                showMessage('success', 'Message programmé avec succès pour le ' . formatDate($date_programmee));
            } else {
                showMessage('success', 'Message envoyé avec succès.');
            }
            
            redirectTo('index.php');
        }
        
    } catch (Exception $e) {
        showMessage('error', 'Erreur : ' . $e->getMessage());
    }
}

// Récupérer les utilisateurs pour les destinataires
try {
    $users = $database->query(
        "SELECT id, username, nom, prenom, role FROM users ORDER BY nom, prenom"
    )->fetchAll();
} catch (Exception $e) {
    $users = [];
}

// Récupérer les classes
try {
    $classes = $database->query(
        "SELECT id, nom, niveau FROM classes ORDER BY niveau, nom"
    )->fetchAll();
} catch (Exception $e) {
    $classes = [];
}

// Récupérer les templates
try {
    $templates = $database->query(
        "SELECT id, nom, sujet, contenu FROM templates_messages WHERE type = 'email' AND actif = 1 ORDER BY nom"
    )->fetchAll();
} catch (Exception $e) {
    $templates = [];
}

$page_title = "Composer un Message";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-pen me-2"></i>
        Composer un Message
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la communication
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="action" value="send_message">
            
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-envelope me-2"></i>
                        Nouveau message
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Destinataires -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="destinataire_type" class="form-label">
                                Type de destinataire <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="destinataire_type" name="destinataire_type" required>
                                <option value="">-- Sélectionner --</option>
                                <option value="user">Utilisateur spécifique</option>
                                <option value="classe">Classe entière</option>
                                <option value="all_students">Tous les élèves</option>
                                <option value="all_teachers">Tout le personnel</option>
                                <option value="all_parents">Tous les parents</option>
                                <option value="custom">Destinataires personnalisés</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="destinataire_specifique" style="display: none;">
                            <label for="destinataire_id" class="form-label">Destinataire</label>
                            <select class="form-select" id="destinataire_id" name="destinataire_id">
                                <option value="">-- Sélectionner un utilisateur --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['nom'] . ' ' . $user['prenom'] . ' (' . $user['role'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Classe spécifique -->
                    <div class="mb-3" id="classe_specifique" style="display: none;">
                        <label for="classe_id" class="form-label">Classe</label>
                        <select class="form-select" id="classe_id" name="classe_id">
                            <option value="">-- Sélectionner une classe --</option>
                            <?php foreach ($classes as $classe): ?>
                                <option value="<?php echo $classe['id']; ?>">
                                    <?php echo htmlspecialchars($classe['nom'] . ' (' . $classe['niveau'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Destinataires personnalisés -->
                    <div class="mb-3" id="destinataires_custom_div" style="display: none;">
                        <label for="destinataires_custom" class="form-label">
                            Destinataires personnalisés
                        </label>
                        <textarea class="form-control" id="destinataires_custom" name="destinataires_custom" rows="3"
                                  placeholder="Entrez les IDs des utilisateurs séparés par des virgules (ex: 1,2,3)"></textarea>
                        <div class="form-text">Entrez les IDs des utilisateurs séparés par des virgules</div>
                    </div>
                    
                    <!-- Template -->
                    <?php if (!empty($templates)): ?>
                    <div class="mb-3">
                        <label for="template_id" class="form-label">Utiliser un template (optionnel)</label>
                        <select class="form-select" id="template_id" name="template_id">
                            <option value="">-- Sélectionner un template --</option>
                            <?php foreach ($templates as $template): ?>
                                <option value="<?php echo $template['id']; ?>" 
                                        data-sujet="<?php echo htmlspecialchars($template['sujet']); ?>"
                                        data-contenu="<?php echo htmlspecialchars($template['contenu']); ?>">
                                    <?php echo htmlspecialchars($template['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Sujet -->
                    <div class="mb-3">
                        <label for="sujet" class="form-label">
                            Sujet <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="sujet" name="sujet" 
                               value="<?php echo htmlspecialchars($_POST['sujet'] ?? ''); ?>" 
                               maxlength="255" required>
                    </div>
                    
                    <!-- Contenu -->
                    <div class="mb-3">
                        <label for="contenu" class="form-label">
                            Message <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="contenu" name="contenu" rows="8" required
                                  placeholder="Tapez votre message ici..."><?php echo htmlspecialchars($_POST['contenu'] ?? ''); ?></textarea>
                        <div class="form-text">
                            <span id="char_count">0</span> caractère(s)
                        </div>
                    </div>
                    
                    <!-- Options du message -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="type_message" class="form-label">Type de message</label>
                            <select class="form-select" id="type_message" name="type_message">
                                <option value="info">Information</option>
                                <option value="urgent">Urgent</option>
                                <option value="rappel">Rappel</option>
                                <option value="felicitation">Félicitation</option>
                                <option value="convocation">Convocation</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="priorite" class="form-label">Priorité</label>
                            <select class="form-select" id="priorite" name="priorite">
                                <option value="basse">Basse</option>
                                <option value="normale" selected>Normale</option>
                                <option value="haute">Haute</option>
                                <option value="critique">Critique</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Options avancées -->
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="programme" name="programme">
                            <label class="form-check-label" for="programme">
                                Programmer l'envoi
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="date_programmee_div" style="display: none;">
                        <label for="date_programmee" class="form-label">Date et heure d'envoi</label>
                        <input type="datetime-local" class="form-control" id="date_programmee" name="date_programmee">
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="accuse_reception" name="accuse_reception">
                            <label class="form-check-label" for="accuse_reception">
                                Demander un accusé de réception
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="card-footer bg-light">
                    <div class="d-flex justify-content-between">
                        <a href="../" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <div>
                            <button type="button" class="btn btn-outline-primary me-2" onclick="saveDraft()">
                                <i class="fas fa-save me-1"></i>
                                Sauvegarder brouillon
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-1"></i>
                                <span id="send_button_text">Envoyer</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Aide -->
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Aide
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6><i class="fas fa-lightbulb me-2"></i>Conseils</h6>
                    <ul class="mb-0 small">
                        <li>Utilisez un sujet clair et descriptif</li>
                        <li>Adaptez le ton selon le type de message</li>
                        <li>Vérifiez les destinataires avant l'envoi</li>
                        <li>Les messages urgents sont prioritaires</li>
                    </ul>
                </div>
                
                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Types de destinataires</h6>
                    <ul class="mb-0 small">
                        <li><strong>Utilisateur spécifique :</strong> Une personne</li>
                        <li><strong>Classe entière :</strong> Tous les élèves d'une classe</li>
                        <li><strong>Tous les élèves :</strong> Tous les élèves de l'école</li>
                        <li><strong>Tout le personnel :</strong> Enseignants et administration</li>
                        <li><strong>Tous les parents :</strong> Parents d'élèves</li>
                        <li><strong>Personnalisés :</strong> Liste d'IDs spécifiques</li>
                    </ul>
                </div>
                
                <?php if (!empty($templates)): ?>
                <div class="alert alert-success">
                    <h6><i class="fas fa-file-alt me-2"></i>Templates disponibles</h6>
                    <p class="mb-0 small">
                        <?php echo count($templates); ?> template(s) disponible(s) pour vous faire gagner du temps.
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Statistiques rapides -->
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Mes messages
                </h5>
            </div>
            <div class="card-body">
                <?php
                try {
                    $mes_stats = $database->query(
                        "SELECT 
                            COUNT(*) as total,
                            COUNT(CASE WHEN status = 'envoye' THEN 1 END) as envoyes,
                            COUNT(CASE WHEN status = 'brouillon' THEN 1 END) as brouillons,
                            COUNT(CASE WHEN status = 'programme' THEN 1 END) as programmes
                         FROM messages WHERE expediteur_id = ?",
                        [$_SESSION['user_id']]
                    )->fetch();
                } catch (Exception $e) {
                    $mes_stats = ['total' => 0, 'envoyes' => 0, 'brouillons' => 0, 'programmes' => 0];
                }
                ?>
                <div class="row text-center">
                    <div class="col-6 mb-2">
                        <h5 class="text-primary"><?php echo $mes_stats['total']; ?></h5>
                        <small class="text-muted">Total</small>
                    </div>
                    <div class="col-6 mb-2">
                        <h5 class="text-success"><?php echo $mes_stats['envoyes']; ?></h5>
                        <small class="text-muted">Envoyés</small>
                    </div>
                    <div class="col-6">
                        <h5 class="text-warning"><?php echo $mes_stats['brouillons']; ?></h5>
                        <small class="text-muted">Brouillons</small>
                    </div>
                    <div class="col-6">
                        <h5 class="text-info"><?php echo $mes_stats['programmes']; ?></h5>
                        <small class="text-muted">Programmés</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Gestion des destinataires
document.getElementById('destinataire_type').addEventListener('change', function() {
    const type = this.value;
    const userDiv = document.getElementById('destinataire_specifique');
    const classeDiv = document.getElementById('classe_specifique');
    const customDiv = document.getElementById('destinataires_custom_div');
    
    // Cacher tous les divs
    userDiv.style.display = 'none';
    classeDiv.style.display = 'none';
    customDiv.style.display = 'none';
    
    // Afficher le div approprié
    if (type === 'user') {
        userDiv.style.display = 'block';
    } else if (type === 'classe') {
        classeDiv.style.display = 'block';
    } else if (type === 'custom') {
        customDiv.style.display = 'block';
    }
});

// Gestion de la programmation
document.getElementById('programme').addEventListener('change', function() {
    const dateDiv = document.getElementById('date_programmee_div');
    const sendButton = document.getElementById('send_button_text');
    
    if (this.checked) {
        dateDiv.style.display = 'block';
        sendButton.textContent = 'Programmer';
        
        // Définir la date minimum à maintenant + 1 heure
        const now = new Date();
        now.setHours(now.getHours() + 1);
        document.getElementById('date_programmee').min = now.toISOString().slice(0, 16);
    } else {
        dateDiv.style.display = 'none';
        sendButton.textContent = 'Envoyer';
    }
});

// Gestion des templates
document.getElementById('template_id').addEventListener('change', function() {
    const option = this.options[this.selectedIndex];
    if (option.value) {
        document.getElementById('sujet').value = option.dataset.sujet || '';
        document.getElementById('contenu').value = option.dataset.contenu || '';
        updateCharCount();
    }
});

// Compteur de caractères
function updateCharCount() {
    const contenu = document.getElementById('contenu').value;
    document.getElementById('char_count').textContent = contenu.length;
}

document.getElementById('contenu').addEventListener('input', updateCharCount);

// Sauvegarder brouillon
function saveDraft() {
    // Implémenter la sauvegarde en brouillon
    alert('Fonctionnalité de brouillon à implémenter');
}

// Validation Bootstrap
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Initialiser le compteur
updateCharCount();
</script>

<?php include '../../../includes/footer.php'; ?>
