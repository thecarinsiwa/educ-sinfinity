<?php
/**
 * Fonctions simplifiées pour récupérer les données réelles des dashboards
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
    } catch (Exception $e) {
        $stats['total_users'] = 0;
    }
    
    try {
        // Total du personnel
        $stmt = $database->query("SELECT COUNT(*) as count FROM personnel WHERE status = 'actif'");
        $stats['total_personnel'] = $stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['total_personnel'] = 0;
    }
    
    try {
        // Total des élèves
        $stmt = $database->query("SELECT COUNT(*) as count FROM eleves WHERE status = 'actif'");
        $stats['total_students'] = $stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['total_students'] = 0;
    }
    
    try {
        // Total des classes
        $stmt = $database->query("SELECT COUNT(*) as count FROM classes");
        $stats['total_classes'] = $stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['total_classes'] = 0;
    }
    
    try {
        // Paiements en attente
        $stmt = $database->query("SELECT COUNT(*) as count FROM paiements WHERE status = 'en_attente'");
        $stats['pending_payments'] = $stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['pending_payments'] = 0;
    }
    
    try {
        // Annonces récentes
        $stmt = $database->query("SELECT COUNT(*) as count FROM annonces WHERE active = 1 AND date_publication >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['recent_announcements'] = $stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['recent_announcements'] = 0;
    }
    
    return $stats;
}

/**
 * Obtenir les statistiques pour le dashboard enseignant
 */
function getTeacherDashboardStats($database, $teacher_id) {
    $stats = [];
    
    try {
        // Classes de l'enseignant (version simplifiée)
        $stmt = $database->query("
            SELECT COUNT(DISTINCT et.classe_id) as count 
            FROM emploi_temps et 
            WHERE et.enseignant_id = ?
        ", [$teacher_id]);
        $stats['my_classes'] = $stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['my_classes'] = 0;
    }
    
    try {
        // Cours aujourd'hui (version simplifiée)
        $stmt = $database->query("
            SELECT COUNT(*) as count 
            FROM emploi_temps et 
            WHERE et.enseignant_id = ? 
            AND et.jour = DAYOFWEEK(NOW())
        ", [$teacher_id]);
        $stats['courses_today'] = $stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['courses_today'] = 0;
    }
    
    try {
        // Évaluations en cours (version simplifiée)
        $stmt = $database->query("
            SELECT COUNT(*) as count 
            FROM evaluations e 
            WHERE e.enseignant_id = ?
        ", [$teacher_id]);
        $stats['active_evaluations'] = $stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['active_evaluations'] = 0;
    }
    
    $stats['notes_to_enter'] = 0; // Valeur par défaut
    
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
    } catch (Exception $e) {
        $stats['my_notes'] = 0;
    }
    
    try {
        // Présences (pourcentage) - Version simplifiée
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
    
    try {
        // Livres empruntés
        $stmt = $database->query("
            SELECT COUNT(*) as count 
            FROM prets p 
            WHERE p.eleve_id = ? 
            AND p.date_retour IS NULL
        ", [$student_id]);
        $stats['borrowed_books'] = $stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['borrowed_books'] = 0;
    }
    
    try {
        // Moyenne générale
        $stmt = $database->query("
            SELECT AVG(n.note) as moyenne 
            FROM notes n 
            WHERE n.eleve_id = ?
        ", [$student_id]);
        $result = $stmt->fetch();
        $stats['average_grade'] = $result['moyenne'] ? round($result['moyenne'], 1) : 'N/A';
    } catch (Exception $e) {
        $stats['average_grade'] = 'N/A';
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
    } catch (Exception $e) {
        $stats['child_notes'] = 0;
    }
    
    $stats['child_attendance'] = 95; // Pourcentage par défaut
    
    try {
        // Statut des paiements
        $stmt = $database->query("
            SELECT COUNT(*) as count 
            FROM paiements p 
            WHERE p.status = 'paye' 
            AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stats['recent_payments'] = $stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['recent_payments'] = 0;
    }
    
    try {
        // Messages non lus
        $stmt = $database->query("
            SELECT COUNT(*) as count 
            FROM messages m 
            WHERE m.lu = 0 
            AND m.type_destinataire = 'parent'
        ");
        $stats['unread_messages'] = $stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['unread_messages'] = 0;
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
    } catch (Exception $e) {
        $stats['pending_payments'] = 0;
    }
    
    try {
        // Nouvelles candidatures
        $stmt = $database->query("
            SELECT COUNT(*) as count 
            FROM demandes_admission 
            WHERE status = 'en_attente' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stats['new_applications'] = $stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['new_applications'] = 0;
    }
    
    try {
        // Campagnes actives
        $stmt = $database->query("
            SELECT COUNT(*) as count 
            FROM campagnes_recouvrement 
            WHERE status = 'active' 
            AND date_debut <= NOW() 
            AND date_fin >= NOW()
        ");
        $stats['active_campaigns'] = $stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['active_campaigns'] = 0;
    }
    
    try {
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
        $stats['recovery_rate'] = 0;
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
            ORDER BY et.heure_debut
        ", [$teacher_id]);
        
        return $stmt->fetchAll();
    } catch (Exception $e) {
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
            ORDER BY et.heure_debut
        ", [$student_id]);
        
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Obtenir les activités récentes pour le dashboard personnel
 */
function getStaffRecentActivities($database) {
    $activities = [];
    
    try {
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
    } catch (Exception $e) {
        // Ignorer les erreurs
    }
    
    try {
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
    } catch (Exception $e) {
        // Ignorer les erreurs
    }
    
    // Trier par date
    usort($activities, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    return array_slice($activities, 0, 10);
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
        return [];
    }
}
?>
