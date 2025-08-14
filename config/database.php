<?php
/**
 * Configuration de la base de données
 * Application de gestion scolaire - République Démocratique du Congo
 */

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'educ_sinfinity');
define('DB_USER', 'root');
define('DB_PASS', ''); // Votre mot de passe MySQL
define('DB_CHARSET', 'utf8mb4');

class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $charset = DB_CHARSET;
    private $pdo;

    /**
     * Connexion à la base de données
     */
    public function connect() {
        if ($this->pdo === null) {
            try {
                $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                
                $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            } catch (PDOException $e) {
                // En mode développement, afficher l'erreur détaillée
                if (defined('APP_DEBUG') && APP_DEBUG) {
                    throw new Exception("Erreur de connexion à la base de données : " . $e->getMessage());
                } else {
                    throw new Exception("Erreur de connexion à la base de données. Vérifiez la configuration.");
                }
            }
        }
        
        return $this->pdo;
    }

    /**
     * Exécuter une requête SELECT
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connect()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            die("Erreur lors de l'exécution de la requête : " . $e->getMessage());
        }
    }

    /**
     * Exécuter une requête INSERT, UPDATE, DELETE
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->connect()->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            die("Erreur lors de l'exécution de la requête : " . $e->getMessage());
        }
    }

    /**
     * Obtenir le dernier ID inséré
     */
    public function lastInsertId() {
        return $this->connect()->lastInsertId();
    }

    /**
     * Exécuter du SQL brut (pour les migrations)
     */
    public function exec($sql) {
        try {
            return $this->connect()->exec($sql);
        } catch (PDOException $e) {
            throw new Exception("Erreur lors de l'exécution du SQL : " . $e->getMessage());
        }
    }

    /**
     * Commencer une transaction
     */
    public function beginTransaction() {
        return $this->connect()->beginTransaction();
    }

    /**
     * Valider une transaction
     */
    public function commit() {
        return $this->connect()->commit();
    }

    /**
     * Annuler une transaction
     */
    public function rollback() {
        return $this->connect()->rollback();
    }
}

// Instance globale de la base de données
try {
    $database = new Database();
    $pdo = $database->connect();
} catch (Exception $e) {
    // En cas d'erreur, définir les variables comme null
    $database = null;
    $pdo = null;

    // Si on n'est pas dans la page de setup, relancer l'erreur
    if (!defined('SETUP_MODE')) {
        throw $e;
    }
}
?>
