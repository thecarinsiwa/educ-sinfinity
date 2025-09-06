<?php
/**
 * Générateur de QR Code PNG pour les cartes d'élèves
 * Format: ECOLE_ID|ANNEE|MATRICULE
 */

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once __DIR__ . '/simple-qr-generator.php';

class QRCodeGenerator extends SimpleQRCodeGenerator {
    
    public function __construct($database, $ecoleId = 'SINF') {
        parent::__construct($database, $ecoleId);
    }
    
}

?>
