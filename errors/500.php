<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erreur 500 - Educ-Sinfinity</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            text-align: center;
            max-width: 600px;
        }
        .error-icon {
            font-size: 5rem;
            color: #dc3545;
            margin-bottom: 20px;
        }
        .error-code {
            font-size: 4rem;
            font-weight: bold;
            color: #dc3545;
            margin-bottom: 10px;
        }
        .btn-home {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            color: white;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
            transition: transform 0.3s ease;
        }
        .btn-home:hover {
            transform: translateY(-2px);
            color: white;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="error-code">500</div>
        <h2 class="mb-3">Erreur Interne du Serveur</h2>
        <p class="text-muted mb-4">
            Désolé, une erreur s'est produite sur le serveur. Notre équipe technique a été notifiée 
            et travaille à résoudre ce problème.
        </p>
        
        <div class="alert alert-info">
            <h6><i class="fas fa-info-circle me-2"></i>Que faire ?</h6>
            <ul class="text-start mb-0">
                <li>Actualisez la page dans quelques minutes</li>
                <li>Vérifiez que la base de données est accessible</li>
                <li>Consultez les logs d'erreur du serveur</li>
                <li>Contactez l'administrateur système si le problème persiste</li>
            </ul>
        </div>
        
        <div class="mt-4">
            <a href="../index.php" class="btn-home">
                <i class="fas fa-home me-2"></i>
                Retour à l'accueil
            </a>
            <a href="../setup.php" class="btn btn-outline-primary ms-2">
                <i class="fas fa-tools me-2"></i>
                Configuration
            </a>
        </div>
        
        <div class="mt-4">
            <small class="text-muted">
                Educ-Sinfinity v1.0.0 - Gestion Scolaire RDC
            </small>
        </div>
    </div>
</body>
</html>
