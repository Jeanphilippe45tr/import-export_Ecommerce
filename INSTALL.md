# EuroImport Export — Guide d'installation (Phase 1)

Site e-commerce d'import-export professionnel, prêt pour hébergement mutualisé (cPanel, PHP 8+, MySQL, sans Node.js).

## 1. Arborescence livrée (Phase 1)

```
import-export/
├── config/
│   └── config.php              # Constantes, identifiants BDD, liste des langues
├── includes/
│   ├── db.php                  # Connexion PDO (requêtes préparées)
│   ├── functions.php           # Fonctions communes (échappement, CSRF, session, devises, TVA, pays…)
│   ├── header.php              # En-tête HTML, barre supérieure, sélecteur de langue, Google Translate
│   └── footer.php              # Pied de page, mentions légales, newsletter
├── public/
│   ├── index.php               # Page d'accueil (Phase 1)
│   ├── assets/
│   │   ├── css/main.css        # Design system (variables CSS, responsive, RTL)
│   │   └── js/main.js          # Sélecteurs langue/devise/pays, menu mobile
│   └── (catalogue.php, produit.php, panier.php… créés aux phases 2 et 3)
├── admin/                      # Espace admin (phases 4 et 5)
├── api/                        # Appels AJAX (phases 2, 3 et 6)
├── uploads/
│   ├── images/                 # Photos produits (à remplir)
│   └── .htaccess               # Interdit l'exécution de scripts
├── database.sql                # Schéma complet + données de démonstration
├── .htaccess                   # Protection dossiers, URLs propres, en-têtes sécurité
├── composer.json               # Dépendance Dompdf (factures PDF, phase 5)
└── INSTALL.md                  # Ce fichier
```

Aucun dossier `lang/` n'est présent (traduction automatique côté navigateur uniquement, conformément au cahier des charges).

## 2. Prérequis serveur

- PHP 8.1 ou supérieur (extensions : `pdo_mysql`, `mbstring`, `gd` pour le redimensionnement d'images aux phases ultérieures)
- MySQL 5.7+ ou MariaDB 10.2+
- Serveur Apache avec `mod_rewrite` (mutualisé cPanel typique)
- Composer (uniquement pour Dompdf, factures PDF — phase 5)

## 3. Installation pas à pas

### 3.1 Transférer les fichiers

Envoyez le contenu du dossier `import-export/` dans la racine de votre site (ou dans un sous-dossier, par exemple `public_html/`).

### 3.2 Créer la base de données

1. Dans cPanel, ouvrez **Bases de données MySQL**.
2. Créez une base de données, par exemple `import_export`.
3. Créez un utilisateur BDD avec un mot de passe fort, et associez-le à la base avec tous les privilèges.
4. Importez le fichier `database.sql` via phpMyAdmin ou en ligne de commande :

   ```bash
   mysql -u utilisateur -p import_export < database.sql
   ```

   Le script crée toutes les tables (clés étrangères, index, utf8mb4) et insère les données de démonstration.

### 3.3 Configurer l'accès à la base de données

Éditez `config/config.php` et adaptez ces lignes :

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'import_export');
define('DB_USER', 'votre_utilisateur');
define('DB_PASS', 'votre_mot_de_passe');
define('DB_PORT', 3306);
```

Si le site est installé dans un sous-dossier, adaptez `APP_URL` (exemple : `define('APP_URL', '/import-export');`) et `RewriteBase` dans `.htaccess`.

### 3.4 Compter pour Dompdf (factures PDF, phase 5)

À la racine du projet :

```bash
composer install
```

Cela installe `dompdf/dompdf` dans le dossier `vendor/`, utilisé plus tard pour générer les factures A4 PDF. Si vous ne pouvez pas utiliser Composer sur votre mutualisé, installez Dompdf en local et envoyez le dossier `vendor/` par FTP.

### 3.5 Droits des dossiers

Sur le mutualisé :

- `uploads/images/` : écriture autorisée (CHMOD 755 ou 777 selon l'hébergeur) pour l'upload des photos produits.
- Les autres dossiers : lecture seule (755).
- Les fichiers `.htaccess` doivent être lisibles (644).

### 3.6 Premier accès et compte admin

Ouvrez le site dans votre navigateur. La page d'accueil s'affiche avec les données de démonstration.

**Espace admin :** `https://votresite.com/admin/` (créé aux phases 4-5).

**Compte administrateur par défaut (données de démo) :**
- Identifiant : `admin`
- Email : `admin@euroimport-export.fr`
- Mot de passe : `password`

> ⚠️ **Sécurité :** connectez-vous immédiatement et changez le mot de passe depuis le profil admin.

Si vous souhaitez générer un nouveau hash de mot de passe sur le serveur :

```bash
php -r "echo password_hash('VOTRE_MOT_DE_PASSE', PASSWORD_DEFAULT);"
```

Puis mettez à jour la table `admins` via phpMyAdmin.

## 4. Fonctionnalités Phase 1 (déjà opérationnelles)

- Connexion PDO sécurisée, requêtes préparées, utf8mb4.
- Session sécurisée (cookie `httponly`, `secure` en HTTPS, `regenerate_id`).
- Jeton CSRF généré et utilisable dans tous les formulaires (`csrfField()`).
- Système de devises : euro par défaut, dollar américain, livre sterling ; taux de change modifiables par l'admin (table `currencies`).
- Sélecteur de langue personnalisé avec drapeaux, cookie `googtrans` mémorisé, choix « Français (original) » qui supprime le cookie.
- Widget Google Website Translator intégré, conteneur masqué hors écran, barre et bulles Google masquées (`.skiptranslate`, `body { top: 0 !important }`).
- Attribut `dir="rtl"` automatique sur `<html>` quand l'arabe est actif.
- Classe `notranslate` + `translate="no"` sur marque, références, prix, devises, téléphones, emails.
- Mention « Traduction automatique. La version française fait foi. » sous le sélecteur et dans le pied de page.
- Design system responsive (mobile-first), variables CSS, palette orange/noir/blanc, lazy-loading des images.
- Pages protégées par `.htaccess` (dossiers `config/`, `includes/`, `api/`, `uploads/` interdits).

## 5. Phases suivantes (à livrer après validation de la Phase 1)

2. Site public : catalogue, fiche produit, recherche AJAX.
3. Panier, livraison/TVA, tunnel de commande, redirection WhatsApp, comptes clients, newsletter.
4. Espace admin : tableau de bord, articles, catégories, devises, paramètres.
5. Livraison, TVA, commandes, clients, factures A4 PDF (Dompdf).
6. Messagerie d'assistance intégrée (chat + admin).
7. Pages légales, SEO, sécurité finale, déploiement.

## 6. Données de démonstration incluses dans `database.sql`

- 8 catégories et 32 sous-catégories (arborescence).
- 15 articles avec références, prix, poids, stock, notes (1-5).
- 3 devises (EUR par défaut, USD, GBP).
- 27 pays avec indicatifs téléphoniques (E.164).
- 8 zones de livraison avec tarifs fixes.
- Taux de TVA par pays (UE et hors UE).
- Paramètres du site (SIRET, TVA intracommunautaire, EORI, IBAN, horaires chat, etc.).
- 3 bannières d'accueil et 8 pages légales institutionnelles.

## 7. Phase 7 — Pages légales, SEO et déploiement

### Pages légales (via `public/pages.php?slug=...`)
- `mentions-legales`, `cgv`, `politique-confidentialite`, `livraison-douane`,
  `retours-retractation`, `faq`, `a-propos`, `contact`
Ces pages sont accessibles via des URLs propres (ex. `/cgv`, `/mentions-legales`) grâce au `.htaccess`.
Le contenu est administrable depuis la table `pages` (en français uniquement).

### Bandeau cookies
Un bandeau en pied de page demande le consentement avant de charger le widget Google Translate.
Il mentionne explicitement que le widget de traduction dépose des cookies. Le consentement est mémorisé
dans le cookie `ie_cookie_consent`.

### SEO
- Balises `title` / `meta description` dynamiques sur chaque page.
- Balises Open Graph (`og:title`, `og:description`, `og:image`, `og:url`, `og:locale`, `og:site_name`) et Twitter Card.
- `sitemap.xml` dynamique (généré par `sitemap.xml.php`) incluant accueil, catalogue, catégories, produits et pages légales.
- `robots.txt` à la racine (interdit l'indexation des dossiers sensibles et des tunnels de commande/paiement).
- URLs propres via `.htaccess`. Pas de `hreflang` ni d'URLs par langue (SEO en français uniquement).

### Sécurité finale
- `.htaccess` protège `config/`, `includes/`, `api/`, `vendor/` et interdit l'exécution de scripts dans `uploads/`.
- En-têtes de sécurité : `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`.
- Sessions sécurisées (`httponly`, `secure`, `SameSite=Lax`, `regenerate_id`).
- Jetons CSRF sur tous les formulaires, requêtes préparées PDO partout, échappement `htmlspecialchars` de toutes les sorties.
- Upload d'images sécurisé (MIME réel vérifié, renommage, taille max 8 Mo).
- Limitation des tentatives de connexion (admin et client).
- Mot de passe admin par défaut : `password` (à changer immédiatement).
