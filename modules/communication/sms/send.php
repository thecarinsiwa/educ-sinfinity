<?php
/**
 * Module Communication - Envoyer un SMS
 * Application de gestion scolaire - République Démocratique du Congo
 */

require_once '../../../config/config.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

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
        
        if ($action === 'send_sms') {
            $destinataires = $_POST['destinataires'] ?? '';
            $message = trim($_POST['message'] ?? '');
            $type_sms = $_POST['type_sms'] ?? 'info';
            $envoyer_maintenant = isset($_POST['envoyer_maintenant']);
            
            // Validation
            if (empty($destinataires) || empty($message)) {
                throw new Exception('Les destinataires et le message sont obligatoires.');
            }
            
            if (strlen($message) > 160) {
                throw new Exception('Le message ne peut pas dépasser 160 caractères.');
            }
            
            // Traiter les destinataires
            $telephones = [];
            $destinataires_array = explode(',', $destinataires);
            
            foreach ($destinataires_array as $dest) {
                $dest = trim($dest);
                
                // Vérifier si c'est un numéro de téléphone
                if (preg_match('/^[\+]?[0-9\s\-\(\)]+$/', $dest)) {
                    // Nettoyer le numéro
                    $numero = preg_replace('/[^\+0-9]/', '', $dest);
                    if (strlen($numero) >= 9) {
                        $telephones[] = ['numero' => $numero, 'nom' => ''];
                    }
                } else {
                    // Rechercher dans la base de données (nom, email, etc.)
                    $users = $database->query(
                        "SELECT nom, prenom, telephone FROM users 
                         WHERE (nom LIKE ? OR prenom LIKE ? OR email LIKE ?) AND telephone IS NOT NULL",
                        ["%$dest%", "%$dest%", "%$dest%"]
                    )->fetchAll();
                    
                    foreach ($users as $user) {
                        if ($user['telephone']) {
                            $numero = preg_replace('/[^\+0-9]/', '', $user['telephone']);
                            if (strlen($numero) >= 9) {
                                $telephones[] = [
                                    'numero' => $numero, 
                                    'nom' => $user['nom'] . ' ' . $user['prenom']
                                ];
                            }
                        }
                    }
                }
            }
            
            if (empty($telephones)) {
                throw new Exception('Aucun numéro de téléphone valide trouvé.');
            }
            
            // Calculer le coût (exemple: 50 FC par SMS)
            $cout_unitaire = 50;
            $cout_total = count($telephones) * $cout_unitaire;
            
            // Enregistrer les SMS
            $sms_envoyes = 0;
            foreach ($telephones as $tel) {
                $status = $envoyer_maintenant ? 'envoye' : 'en_attente';
                $date_envoi = $envoyer_maintenant ? date('Y-m-d H:i:s') : null;
                
                $database->execute(
                    "INSERT INTO sms_logs (
                        expediteur_id, destinataire_telephone, destinataire_nom, message,
                        type_sms, cout, status, date_envoi, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $_SESSION['user_id'], $tel['numero'], $tel['nom'], $message,
                        $type_sms, $cout_unitaire, $status, $date_envoi
                    ]
                );
                $sms_envoyes++;
            }
            
            if ($envoyer_maintenant) {
                showMessage('success', "$sms_envoyes SMS envoyé(s) avec succès. Coût total: " . number_format($cout_total ?? 0) . " FC");
            } else {
                showMessage('success', "$sms_envoyes SMS mis en file d'attente. Coût estimé: " . number_format($cout_total ?? 0) . " FC");
            }
            
            redirectTo('index.php');
        }
        
    } catch (Exception $e) {
        showMessage('error', 'Erreur : ' . $e->getMessage());
    }
}

// Récupérer les templates SMS
try {
    $templates = $database->query(
        "SELECT id, nom, contenu FROM templates_messages WHERE type = 'sms' AND actif = 1 ORDER BY nom"
    )->fetchAll();
} catch (Exception $e) {
    $templates = [];
}

// Récupérer les contacts récents
try {
    $contacts_recents = $database->query(
        "SELECT destinataire_telephone, destinataire_nom, MAX(created_at) as derniere_utilisation
         FROM sms_logs
         WHERE expediteur_id = ? AND destinataire_nom != ''
         GROUP BY destinataire_telephone, destinataire_nom
         ORDER BY derniere_utilisation DESC
         LIMIT 10",
        [$_SESSION['user_id']]
    )->fetchAll();
} catch (Exception $e) {
    $contacts_recents = [];
}

// Type prédéfini depuis l'URL
$type_predefini = $_GET['type'] ?? '';

$page_title = "Envoyer un SMS";
include '../../../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-mobile-alt me-2"></i>
        Envoyer un SMS
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Retour à la communication
            </a>
        </div>
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-outline-info">
                <i class="fas fa-list me-1"></i>
                Historique SMS
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="action" value="send_sms">
            
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-paper-plane me-2"></i>
                        Nouveau SMS
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Destinataires -->
                    <div class="mb-3">
                        <label for="destinataires" class="form-label">
                            Destinataires <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="destinataires" name="destinataires" rows="3" required
                                  placeholder="Entrez les numéros de téléphone ou noms séparés par des virgules&#10;Exemple: +243123456789, Jean Dupont, marie@email.com"></textarea>
                        <div class="form-text">
                            Numéros de téléphone, noms ou emails séparés par des virgules
                        </div>
                    </div>
                    
                    <!-- Contacts récents -->
                    <?php if (!empty($contacts_recents)): ?>
                    <div class="mb-3">
                        <label class="form-label">Contacts récents</label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($contacts_recents as $contact): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary contact-btn"
                                        data-numero="<?php echo htmlspecialchars($contact['destinataire_telephone']); ?>"
                                        data-nom="<?php echo htmlspecialchars($contact['destinataire_nom']); ?>">
                                    <i class="fas fa-user me-1"></i>
                                    <?php echo htmlspecialchars($contact['destinataire_nom'] ?: $contact['destinataire_telephone']); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Template -->
                    <?php if (!empty($templates)): ?>
                    <div class="mb-3">
                        <label for="template_id" class="form-label">Utiliser un template (optionnel)</label>
                        <select class="form-select" id="template_id" name="template_id">
                            <option value="">-- Sélectionner un template --</option>
                            <?php foreach ($templates as $template): ?>
                                <option value="<?php echo $template['id']; ?>" 
                                        data-contenu="<?php echo htmlspecialchars($template['contenu']); ?>">
                                    <?php echo htmlspecialchars($template['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Message -->
                    <div class="mb-3">
                        <label for="message" class="form-label">
                            Message <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" id="message" name="message" rows="4" required
                                  maxlength="160" placeholder="Tapez votre message SMS ici..."></textarea>
                        <div class="form-text">
                            <span id="char_count">0</span>/160 caractères
                            <span id="sms_count" class="ms-3">1 SMS</span>
                        </div>
                    </div>
                    
                    <!-- Type de SMS -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="type_sms" class="form-label">Type de SMS</label>
                            <select class="form-select" id="type_sms" name="type_sms">
                                <option value="info" <?php echo $type_predefini === 'info' ? 'selected' : ''; ?>>Information</option>
                                <option value="urgent" <?php echo $type_predefini === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                <option value="rappel" <?php echo $type_predefini === 'rappel' ? 'selected' : ''; ?>>Rappel</option>
                                <option value="absence" <?php echo $type_predefini === 'absence' ? 'selected' : ''; ?>>Absence</option>
                                <option value="retard" <?php echo $type_predefini === 'retard' ? 'selected' : ''; ?>>Retard</option>
                                <option value="discipline" <?php echo $type_predefini === 'discipline' ? 'selected' : ''; ?>>Discipline</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Options d'envoi</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="envoyer_maintenant" name="envoyer_maintenant" checked>
                                <label class="form-check-label" for="envoyer_maintenant">
                                    Envoyer immédiatement
                                </label>
                            </div>
                            <div class="form-text">Décochez pour mettre en file d'attente</div>
                        </div>
                    </div>
                    
                    <!-- Estimation du coût -->
                    <div class="alert alert-info">
                        <h6><i class="fas fa-calculator me-2"></i>Estimation du coût</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <strong>Destinataires:</strong> <span id="dest_count">0</span>
                            </div>
                            <div class="col-md-4">
                                <strong>Coût unitaire:</strong> 50 FC
                            </div>
                            <div class="col-md-4">
                                <strong>Total estimé:</strong> <span id="cout_total">0 FC</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card-footer bg-light">
                    <div class="d-flex justify-content-between">
                        <a href="../" class="btn btn-secondary">
                            <i class="fas fa-times me-1"></i>
                            Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i>
                            <span id="send_button_text">Envoyer SMS</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Aide et informations -->
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Guide SMS
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-success">
                    <h6><i class="fas fa-mobile-alt me-2"></i>Formats acceptés</h6>
                    <ul class="mb-0 small">
                        <li>+243123456789 (international)</li>
                        <li>0123456789 (national)</li>
                        <li>123 456 789 (avec espaces)</li>
                        <li>Noms d'utilisateurs</li>
                        <li>Adresses email (si téléphone associé)</li>
                    </ul>
                </div>
                
                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Limites SMS</h6>
                    <ul class="mb-0 small">
                        <li>160 caractères maximum</li>
                        <li>Évitez les caractères spéciaux</li>
                        <li>Les accents comptent double</li>
                        <li>Soyez concis et clair</li>
                    </ul>
                </div>
                
                <div class="alert alert-info">
                    <h6><i class="fas fa-coins me-2"></i>Tarification</h6>
                    <ul class="mb-0 small">
                        <li>50 FC par SMS standard</li>
                        <li>Facturation par destinataire</li>
                        <li>Pas de frais cachés</li>
                        <li>Rapport de livraison inclus</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Statistiques personnelles -->
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i>
                    Mes SMS ce mois
                </h5>
            </div>
            <div class="card-body">
                <?php
                try {
                    $mes_stats = $database->query(
                        "SELECT 
                            COUNT(*) as total,
                            COUNT(CASE WHEN status = 'envoye' THEN 1 END) as envoyes,
                            COUNT(CASE WHEN status = 'echec' THEN 1 END) as echecs,
                            SUM(CASE WHEN cout IS NOT NULL THEN cout ELSE 0 END) as cout_total
                         FROM sms_logs 
                         WHERE expediteur_id = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())",
                        [$_SESSION['user_id']]
                    )->fetch();
                } catch (Exception $e) {
                    $mes_stats = ['total' => 0, 'envoyes' => 0, 'echecs' => 0, 'cout_total' => 0];
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
                        <h5 class="text-danger"><?php echo $mes_stats['echecs']; ?></h5>
                        <small class="text-muted">Échecs</small>
                    </div>
                    <div class="col-6">
                        <h5 class="text-warning"><?php echo number_format($mes_stats['cout_total'] ?? 0); ?></h5>
                        <small class="text-muted">FC dépensés</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Actions rapides -->
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>
                    Messages rapides
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary quick-msg" 
                            data-msg="Rappel: Réunion des parents demain à 9h00. Merci de confirmer votre présence.">
                        <i class="fas fa-bell me-1"></i>
                        Rappel réunion
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-warning quick-msg" 
                            data-msg="Votre enfant est absent aujourd'hui. Merci de justifier cette absence.">
                        <i class="fas fa-user-times me-1"></i>
                        Absence élève
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success quick-msg" 
                            data-msg="Félicitations! Votre enfant a obtenu d'excellents résultats ce trimestre.">
                        <i class="fas fa-trophy me-1"></i>
                        Félicitations
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger quick-msg" 
                            data-msg="Convocation urgente. Merci de vous présenter à l'école demain matin.">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Convocation urgente
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Compteur de caractères et SMS
function updateCharCount() {
    const message = document.getElementById('message').value;
    const charCount = message.length;
    const smsCount = Math.ceil(charCount / 160) || 1;
    
    document.getElementById('char_count').textContent = charCount;
    document.getElementById('sms_count').textContent = smsCount + ' SMS';
    
    // Changer la couleur selon la limite
    const charCountElement = document.getElementById('char_count');
    if (charCount > 160) {
        charCountElement.className = 'text-danger';
    } else if (charCount > 140) {
        charCountElement.className = 'text-warning';
    } else {
        charCountElement.className = 'text-success';
    }
}

// Estimation du coût
function updateCostEstimate() {
    const destinataires = document.getElementById('destinataires').value;
    const destCount = destinataires.split(',').filter(d => d.trim().length > 0).length;
    const coutTotal = destCount * 50;
    
    document.getElementById('dest_count').textContent = destCount;
    document.getElementById('cout_total').textContent = coutTotal.toLocaleString() + ' FC';
}

// Gestion des templates
document.getElementById('template_id').addEventListener('change', function() {
    const option = this.options[this.selectedIndex];
    if (option.value) {
        document.getElementById('message').value = option.dataset.contenu || '';
        updateCharCount();
    }
});

// Contacts récents
document.querySelectorAll('.contact-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const destinataires = document.getElementById('destinataires');
        const currentValue = destinataires.value;
        const newContact = this.dataset.nom || this.dataset.numero;
        
        if (currentValue) {
            destinataires.value = currentValue + ', ' + newContact;
        } else {
            destinataires.value = newContact;
        }
        updateCostEstimate();
    });
});

// Messages rapides
document.querySelectorAll('.quick-msg').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('message').value = this.dataset.msg;
        updateCharCount();
    });
});

// Option d'envoi
document.getElementById('envoyer_maintenant').addEventListener('change', function() {
    const sendButton = document.getElementById('send_button_text');
    if (this.checked) {
        sendButton.textContent = 'Envoyer SMS';
    } else {
        sendButton.textContent = 'Mettre en file';
    }
});

// Événements
document.getElementById('message').addEventListener('input', updateCharCount);
document.getElementById('destinataires').addEventListener('input', updateCostEstimate);

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

// Initialiser
updateCharCount();
updateCostEstimate();
</script>

<?php include '../../../includes/footer.php'; ?>
