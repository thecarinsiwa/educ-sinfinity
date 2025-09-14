<?php
/**
 * Fonctions pour récupérer les données réelles des dashboards
 * Système de gestion scolaire - République Démocratique du Congo
 */

/**
 * Obtenir les statistiques pour le dashboard administrateur
 */
function getAdminDashboardStats($database) {
    $stats = [];
    
    try {
        // Total des utilisateurs
        $stmt = $database->query("SELECT COUNT(*) as count FROM users WHERE status = 'actif'");
        $stats['total_users'] = $stmt->fetch()['count'];
        
        // Total du personnel
        $stmt = $database->query("SELECT COUNT(*) as count FROM personnel WHERE status = 'actif'");
        $stats['total_personnel'] = $stmt->fetch()['count'];
        
        // Total des élèves
        $stmt = $database->query("SELECT COUNT(*) as count FROM eleves WHERE status = 'actif'");
        $stats['total_students'] = $stmt->fetch()['count'];
        
        // Total des classes
        try {
            $stmt = $database->query("SELECT COUNT(*) as count FROM classes WHERE status = 'actif'");
            $stats['total_classes'] = $stmt->fetch()['count'];
        } catch (Exception $e) {
            // Si la colonne status n'existe pas dans classes, utiliser une requête simple
            $stmt = $database->query("SELECT COUNT(*) as count FROM classes");
            $stats['total_classes'] = $stmt->fetch()['count'];
        }
        
        // Paiements en attente
        $stmt = $database->query("SELECT COUNT(*) as count FROM paiements WHERE status = 'en_attente'");
        $stats['pending_payments'] = $stmt->fetch()['count'];
        
        // Annonces récentes
        $stmt = $database->query("SELECT COUNT(*) as count FROM annonces WHERE active = 1 AND date_publication >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['recent_announcements'] = $stmt->fetch()['count'];
        
    } catch (Exception $e) {
        error_log("Erreur getAdminDashboardStats: " . $e->getMessage());
    }
    
    return $stats;
}

/**
 * Obtenir les statistiques pour le dashboard enseignant
 */
function getTeacherDashboardStats($database, $teacher_id) {
    $stats = [];
    
    try {
        // Classes de l'enseignant
        $stmt = $database->query("
            SELECT COUNT(DISTINCT et.classe_id) as count 
            FROM emploi_temps et 
            WHERE et.enseignant_id = ? AND et.actif = 1
        ", [$teacher_id]);
        $stats['my_classes'] = $stmt->fetch()['count'];
        
        // Cours aujourd'hui
        $stmt = $database->query("
            SELECT COUNT(*) as count 
            FROM emploi_temps et 
            WHERE et.enseignant_id = ? 
            AND et.jour = DAYOFWEEK(NOW()) 
            AND et.actif = 1
        ", [$teacher_id]);
        $stats['courses_today'] = $stmt->fetch()['count'];
        
        // Évaluations en cours
        $stmt = $database->query("
            SELECT COUNT(*) as count 
            FROM evaluations e 
            WHERE e.enseignant_id = ? 
            AND e.date_debut <= NOW() 
            AND e.date_fin >= NOW()
        ", [$teacher_id]);
        $stats['active_evaluations'] = $stmt->fetch()['count'];
        
        // Notes à saisir (estimation)
        $stmt = $database->query("
            SELECT COUNT(DISTINCT e.id) * 
            (SELECT COUNT(*) FROM eleves WHERE statut = 'actif') / 
            (SELECT COUNT(*) FROM classes WHERE statut = 'actif') as count
            FROM evaluations e 
            WHERE e.enseignant_id = ? 
            AND e.date_fin >= NOW()
        ", [$teacher_id]);
        $stats['notes_to_enter'] = round($stmt->fetch()['count']);
        
    } catch (Exception $e) {
        error_log("Erreur getTeacherDashboardStats: " . $e->getMessage());
    }
    
    return $stats;
}

/**
 * Obtenir les statistiques pour le dashboard élève
 */
function getStudentDashboardStats($database, $student_id) {
    $stats = [];
    
    try {
        // Notes de l'élève
        $stmt = $database->query("
            SELECT COUNT(*) as count 
            FROM notes n 
            WHERE n.eleve_id = ?
        ", [$student_id]);
        $stats['my_notes'] = $stmt->fetch()['count'];
        
        // Présences (pourcentage) - Version simplifiée
        try {
            $stmt = $database->query("
                SELECT COUNT(*) as total_absences
                FROM absences 
                WHERE eleve_id = ? 
                AND date_absence >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ", [$student_id]);
            $total_absences = $stmt->fetch()['total_absences'];
            // Estimation basée sur 22 jours ouvrés par mois
            $stats['attendance_percentage'] = max(0, 100 - round(($total_absences / 22) * 100));
        } catch (Exception $e) {
            $stats['attendance_percentage'] = 95; // Valeur par défaut
        }
        
        // Livres empruntés
        $stmt = $database->query("
            SELECT COUNT(*) as count 
            FROM prets p 
            WHERE p.eleve_id = ? 
            AND p.date_retour IS NULL
        ", [$student_id]);
        $stats['borrowed_books'] = $stmt->fetch()['count'];
        
        // Moyenne générale
        $stmt = $database->query("
            SELECT AVG(n.note) as moyenne 
            FROM notes n 
            WHERE n.eleve_id = ?
        ", [$student_id]);
        $stats['average_grade'] = round($stmt->fetch()['moyenne'], 1);
        
    } catch (Exception $e) {
        error_log("Erreur getStudentDashboardStats: " . $e->getMessage());
    }
    
    return $stats;
}

/**
 * Obtenir les statistiques pour le dashboard parent
 */
function getParentDashboardStats($database, $parent_id = null) {
    $stats = [];
    
    try {
        // Notes récentes de l'enfant (simulation - à adapter selon la logique métier)
        $stmt = $database->query("
            SELECT COUNT(*) as count 
            FROM notes n 
            WHERE n.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stats['child_notes'] = $stmt->fetch()['count'];
        
        // Présences de l'enfant (simulation)
        $stats['child_attendance'] = 95; // Pourcentage
        
        // Statut des paiements
        $stmt = $database->query("
            SELECT COUNT(*) as count 
            FROM paiements p 
            WHERE p.status = 'paye' 
            AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stats['recent_payments'] = $stmt->fetch()['count'];
        
        // Messages non lus
        $stmt = $database->query("
            SELECT COUNT(*) as count 
            FROM messages m 
            WHERE m.lu = 0 
            AND m.type_destinataire = 'parent'
        ");
        $stats['unread_messages'] = $stmt->fetch()['count'];
        
    } catch (Exception $e) {
        error_log("Erreur getParentDashboardStats: " . $e->getMessage());
    }
    
    return $stats;
}

/**
 * Obtenir les statistiques pour le dashboard personnel administratif
 */
function getStaffDashboardStats($database) {
    $stats = [];
    
    try {
        // Paiements en attente
        $stmt = $database->query("SELECT COUNT(*) as count FROM paiements WHERE status = 'en_attente'");
        $stats['pending_payments'] = $stmt->fetch()['count'];
        
        // Nouvelles candidatures
        $stmt = $database->query("
            SELECT COUNT(*) as count 
            FROM demandes_admission 
            WHERE status = 'en_attente' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stats['new_applications'] = $stmt->fetch()['count'];
        
        // Campagnes actives
        $stmt = $database->query("
            SELECT COUNT(*) as count 
            FROM campagnes_recouvrement 
            WHERE status = 'active' 
            AND date_debut <= NOW() 
            AND date_fin >= NOW()
        ");
        $stats['active_campaigns'] = $stmt->fetch()['count'];
        
        // Taux de recouvrement (estimation)
        $stmt = $database->query("
            SELECT 
                COUNT(*) as total_payments,
                SUM(CASE WHEN status = 'paye' THEN 1 ELSE 0 END) as paid_payments
            FROM paiements 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $result = $stmt->fetch();
        $stats['recovery_rate'] = $result['total_payments'] > 0 ? 
            round(($result['paid_payments'] / $result['total_payments']) * 100) : 0;
        
    } catch (Exception $e) {
        error_log("Erreur getStaffDashboardStats: " . $e->getMessage());
    }
    
    return $stats;
}

/**
 * Obtenir les cours d'aujourd'hui pour un enseignant
 */
function getTeacherTodaySchedule($database, $teacher_id) {
    try {
        $stmt = $database->query("
            SELECT 
                et.heure_debut,
                et.heure_fin,
                c.nom as classe_nom,
                m.nom as matiere_nom,
                s.nom as salle_nom
            FROM emploi_temps et
            LEFT JOIN classes c ON et.classe_id = c.id
            LEFT JOIN matieres m ON et.matiere_id = m.id
            LEFT JOIN salles s ON et.salle_id = s.id
            WHERE et.enseignant_id = ?
            AND et.jour = DAYOFWEEK(NOW())
            AND et.actif = 1
            ORDER BY et.heure_debut
        ", [$teacher_id]);
        
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Erreur getTeacherTodaySchedule: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir l'emploi du temps d'aujourd'hui pour un élève
 */
function getStudentTodaySchedule($database, $student_id) {
    try {
        $stmt = $database->query("
            SELECT 
                et.heure_debut,
                et.heure_fin,
                m.nom as matiere_nom,
                p.nom as enseignant_nom,
                s.nom as salle_nom
            FROM emploi_temps et
            LEFT JOIN matieres m ON et.matiere_id = m.id
            LEFT JOIN personnel p ON et.enseignant_id = p.id
            LEFT JOIN salles s ON et.salle_id = s.id
            LEFT JOIN eleves e ON e.classe_id = et.classe_id
            WHERE e.id = ?
            AND et.jour = DAYOFWEEK(NOW())
            AND et.actif = 1
            ORDER BY et.heure_debut
        ", [$student_id]);
        
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Erreur getStudentTodaySchedule: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir les activités récentes pour le dashboard personnel
 */
function getStaffRecentActivities($database) {
    try {
        $activities = [];
        
        // Paiements récents
        $stmt = $database->query("
            SELECT 
                'paiement' as type,
                CONCAT('Nouveau paiement - ', e.nom, ' ', e.prenom) as description,
                p.montant,
                p.created_at
            FROM paiements p
            LEFT JOIN eleves e ON p.eleve_id = e.id
            WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY p.created_at DESC
            LIMIT 5
        ");
        
        while ($row = $stmt->fetch()) {
            $activities[] = $row;
        }
        
        // Candidatures récentes
        $stmt = $database->query("
            SELECT 
                'candidature' as type,
                CONCAT('Nouvelle candidature - ', nom_eleve, ' ', prenom_eleve) as description,
                NULL as montant,
                created_at
            FROM demandes_admission
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY created_at DESC
            LIMIT 5
        ");
        
        while ($row = $stmt->fetch()) {
            $activities[] = $row;
        }
        
        // Trier par date
        usort($activities, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return array_slice($activities, 0, 10);
        
    } catch (Exception $e) {
        error_log("Erreur getStaffRecentActivities: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir les messages récents pour le dashboard parent
 */
function getParentRecentMessages($database) {
    try {
        $stmt = $database->query("
            SELECT 
                m.id,
                m.titre,
                m.contenu,
                m.type_message,
                m.created_at,
                u.nom as auteur_nom
            FROM messages m
            LEFT JOIN users u ON m.auteur_id = u.id
            WHERE m.type_destinataire = 'parent'
            AND m.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY m.created_at DESC
            LIMIT 5
        ");
        
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Erreur getParentRecentMessages: " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir les données pour les graphiques
 */
function getDashboardChartData($database, $type) {
    try {
        switch ($type) {
            case 'payments':
                $stmt = $database->query("
                    SELECT 
                        CASE 
                            WHEN status = 'paye' THEN 'Payés'
                            WHEN status = 'en_attente' THEN 'En attente'
                            WHEN status = 'annule' THEN 'Annulés'
                            ELSE 'Autres'
                        END as status,
                        COUNT(*) as count
                    FROM paiements 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY status
                ");
                return $stmt->fetchAll();
                
            case 'student_grades':
                $stmt = $database->query("
                    SELECT 
                        MONTH(created_at) as mois,
                        AVG(note) as moyenne
                    FROM notes 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    GROUP BY MONTH(created_at)
                    ORDER BY mois
                ");
                return $stmt->fetchAll();
                
            default:
                return [];
        }
    } catch (Exception $e) {
        error_log("Erreur getDashboardChartData: " . $e->getMessage());
        return [];
    }
}
?>
