# Modèle de Carte d'Élève RDC Officiel

## Vue d'ensemble

Le système de gestion scolaire inclut maintenant un modèle de carte d'élève conforme au modèle officiel de la République Démocratique du Congo, avec un fond blanc comme spécifié.

## Caractéristiques du modèle RDC

### ✅ **En-tête officiel**
- **Titre principal** : "REPUBLIQUE DEMOCRATIQUE DU CONGO"
- **Sous-titre** : "SECRETARIAT GENERAL DE L'ENSEIGNEMENT PRIMAIRE, SECONDAIRE ET PROFESSIONNEL"
- **Année scolaire** : "ANNEE SCOLAIRE [ANNÉE]"
- **Type de carte** : "CARTE D'IDENTIFICATION DE L'ELEVE"

### ✅ **Emblèmes nationaux**
- **Drapeau RDC** : Coin supérieur gauche avec étoile jaune
- **Emblème circulaire** : Coin supérieur droit avec "RDC"

### ✅ **Informations de l'élève**
- **NOM** : Nom de famille
- **POST-NOM** : Prénom
- **SEX** : Sexe (M/F)
- **LIEU & DATE DE N.** : Lieu et date de naissance
- **CLASSE** : Classe de l'élève
- **OPTION** : Option/Spécialisation
- **EMAIL** : Adresse email générée automatiquement
- **NUMERO PERMANENT** : Numéro de matricule

### ✅ **Zone photo**
- **Cadre rectangulaire** avec bordure noire
- **Texte "PHOTO"** centré dans le cadre
- **Support des photos** existantes du système

### ✅ **Signature et QR Code**
- **Signature officielle** : "LE SECRETAIRE GENERAL LUFUNISABO BUNDOKI"
- **Numéro de référence** : "236731"
- **QR Code** : Positionné en bas à droite
- **Numéro sous QR** : Correspondant au numéro de référence

### ✅ **Design et format**
- **Fond blanc** : Conforme au modèle officiel
- **Format** : 85.6 x 54 mm (carte d'identité standard)
- **Bordure** : Fine bordure noire
- **Couleurs RDC** : Bleu, rouge, jaune selon les standards nationaux

## Utilisation

### **Accès au modèle RDC**

1. **Via l'interface principale** :
   - Aller dans le module "Cartes d'élèves"
   - Cliquer sur le bouton avec le drapeau 🇨🇩 pour "Imprimer Modèle RDC"

2. **Via l'URL directe** :
   ```
   modules/cartes_eleves/print-rdc.php?id=[ID_CARTE]
   ```

3. **Via l'impression standard** :
   ```
   modules/cartes_eleves/print.php?id=[ID_CARTE]&modele=rdc
   ```

### **Génération automatique**

Le modèle RDC est automatiquement disponible pour toutes les cartes générées. Aucune configuration supplémentaire n'est requise.

## Fichiers implémentés

### **Nouveaux fichiers**
- `modules/cartes_eleves/print-rdc.php` - Template de carte RDC
- `docs/MODELE-CARTE-RDC.md` - Cette documentation

### **Fichiers modifiés**
- `modules/cartes_eleves/print.php` - Ajout de la redirection vers le modèle RDC
- `modules/cartes_eleves/index.php` - Ajout du bouton modèle RDC

## Configuration

### **Paramètres par défaut**
```php
$parametres = [
    'couleur_principale' => '#1e3a8a', // Bleu foncé RDC
    'modele_rdc' => 1, // Activer le modèle RDC
    'qr_code_size' => 80,
    'include_photo' => 1,
    'include_qr_code' => 1
];
```

### **Personnalisation**

Le modèle peut être personnalisé en modifiant les variables dans `print-rdc.php` :
- Couleurs RDC
- Taille des éléments
- Position des informations
- Texte des signatures

## Compatibilité

- ✅ **PHP 7.4+** : Compatible avec toutes les versions récentes
- ✅ **TCPDF** : Utilise la bibliothèque TCPDF existante
- ✅ **Base de données** : Compatible avec la structure existante
- ✅ **QR Codes** : Utilise le système de QR codes mis à jour

## Exemple de sortie

La carte générée respecte fidèlement le modèle officiel RDC avec :
- En-tête officiel complet
- Emblèmes nationaux
- Informations structurées
- Zone photo dédiée
- Signature et QR code
- Fond blanc conforme

## Support

Pour toute question ou personnalisation du modèle RDC, consultez :
- La documentation du module cartes d'élèves
- Les fichiers source dans `modules/cartes_eleves/`
- Cette documentation pour les détails techniques
