# EuroImport Export — Guide de déploiement (cPanel mutualisé PHP 8+)

Ce guide résume toutes les étapes pour mettre le site en production sur un hébergement mutualisé classique (cPanel, PHP 8+, MySQL, Apache, sans Node.js).

---

## 1. Structure livrée (prêt à copier-coller)

```
import-export/
├── config/config.php               # Constantes, identifiants BDD, liste des langues
├── includes/
│   ├── config_bootstrap.php        # Chargement léger (config + DB + fonctions + session)
│   ├── db.php                      # Connexion PDO singleton (utf8mb4, requêtes préparées)
│   ├── functions.php               # Échappement e()/nt(), session, CSRF, flash, devises, TVA, pays…
│   ├── header.php                  # <html>, <head>, Open Graph, barre supérieure, sélecteur langue
│   └── footer.php                  # Pied de page, bandeau cookies, chat, scripts
├── public/
│   ├── index.php                   # Page d'accueil
│   ├── catalogue.php               # Catalogue + filtres + tri + pagination
│   ├── produit.php                 # Fiche produit (galerie, prix devise, livraison estimée)
│   ├── recherche.php               # Résultats de recherche + pagination
│   ├── panier.php                  # Panier (session) + estimation livraison/TVA
│   ├── commande.php                # Tunnel de commande + case CGV
│   ├── commande-confirmation.php   # Confirmation + bouton WhatsApp + fallback
│   ├── connexion.php               # Connexion client (5 tentatives max)
│   ├── inscription.php             # Création de compte (password_hash, RGPD)
│   ├── compte.php                  # Historique + suppression RGPD
│   ├── mot-de-passe-oublie.php     # Réinitialisation (token 1h)
│   ├── reset-password.php          # Formulaire de réinitialisation
│   ├── newsletter.php              # Abonnement (consentement explicite)
│   ├── pages.php                   # Pages légales (slug : mentions-legales, cgv, etc.)
│   └── assets/
│       ├── css/main.css            # Design system (variables CSS, responsive, RTL)
│       ├── css/admin.css           # Style espace admin
│       ├── css/chat.css            # Widget chat
│       ├── js/main.js              # Sélecteurs langue/devise/pays, menu mobile
│       ├── js/catalogue.js         # Suggestions AJAX, ajout panier AJAX
│       └── js/chat.js              # Widget chat (long polling 3s)
├── admin/
│   ├── login.php                   # Connexion admin
│   ├── dashboard.php               # Stats + graphiques Chart.js
│   ├── articles.php / article-edit.php   # Gestion articles + upload images
│   ├── categories.php              # Arborescence multi-niveaux
│   ├── currencies.php              # Devises + taux de change
│   ├── commandes.php / commande-detail.php # Liste + détail + changement statut
│   ├── clients.php / client-detail.php     # Liste clients + fiche + export CSV + RGPD
│   ├── newsletter.php              # Liste abonnés + export CSV
│   ├── messagerie.php              # Conversations admin + réponse + blocage
│   ├── livraison.php               # Zones, tarifs, pays rattachés
│   ├── tva.php                     # Taux TVA + règles
│   ├── factures.php                # Liste factures + PDF + avoir
│   ├── settings.php                # Tous les paramètres du site
│   ├── profil.php                  # Changement mot de passe admin
│   └── admins.php                  # Gestion administrateurs (superadmin)
├── api/
│   ├── auth.php                    # Déconnexion client
│   ├── commande.php                # Enregistrement commande + message WhatsApp
│   ├── messagerie.php              # Chat public (create, send, poll, history, status)
│   ├── newsletter.php              # Désabonnement par token
│   ├── panier.php                  # Ajout / maj / suppression panier
│   ├── search.php                  # Suggestions recherche en direct
│   └── admin/
│       ├── commandes.php           # Changement statut commande
│       ├── facture.php             # Génération PDF facture (Dompdf) + avoir
│       ├── livraison.php           # CRUD zones / tarifs / pays
│       ├── messagerie.php          # API chat admin (list, detail, send, block…)
│       ├── newsletter.php          # Export CSV / suppression abonné
│       ├── tva.php                 # CRUD taux TVA + règles
│       ├── upload.php              # Upload images produits (MIME vérifié)
│       └── clients.php             # Export CSV clients / anonymisation RGPD
├── uploads/
│   ├── images/products/            # Photos produits (à remplir)
│   ├── chat/images/                # Images échangées dans le chat
│   └── pdfs/                       # Factures PDF générées
├── .htaccess                       # Protection dossiers, URLs propres, sitemap, sécurité
├── robots.txt                      # Interdiction d'indexation des dossiers sensibles
├── sitemap.xml.php                 # Sitemap dynamique (réécrit en /sitemap.xml)
├── database.sql                    # Schéma complet + données de démonstration
├── composer.json                   # Dépendance Dompdf (factures PDF)
└── INSTALL.md / GUIDE_DEPLOIEMENT.md
```

Aucun dossier `lang/` n'est présent (traduction automatique côté navigateur uniquement, conformément au cahier des charges).

---

## 2. Prérequis serveur

- PHP 8.1 ou supérieur avec extensions : `pdo_mysql`, `mbstring`, `gd`, `openssl`, `fileinfo` (finfo pour la vérification MIME des uploads)
- MySQL 5.7+ ou MariaDB 10.2+
- Serveur Apache avec `mod_rewrite` activé (mutualisé cPanel typique)
- Composer (uniquement pour installer `dompdf/dompdf` — factures PDF)
- Fonction `mail()` ou SMTP configuré si vous souhaitez envoyer des emails réels (sinon, mode démo pour mot de passe oublié)

---

## 3. Installation pas à pas

### 3.1 Transférer les fichiers
Envoyez le contenu du dossier `import-export/` dans la racine de votre site (ex. `public_html/`).

### 3.2 Créer la base de données
1. Dans cPanel, ouvrez **Bases de données MySQL**.
2. Créez une base (ex. `import_export`) et un utilisateur avec un mot de passe fort.
3. Associez l'utilisateur à la base avec tous les privilèges.
4. Importez `database.sql` via phpMyAdmin ou en ligne de commande :
   ```bash
   mysql -u utilisateur -p import_export < database.sql
   ```

### 3.3 Configurer l'accès BDD
Éditez `config/config.php` :
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'import_export');
define('DB_USER', 'votre_utilisateur');
define('DB_PASS', 'votre_mot_de_passe');
define('DB_PORT', 3306);
```
Si le site est dans un sous-dossier, adaptez `APP_URL` et ajoutez `RewriteBase /sous-dossier/` dans `.htaccess`.

### 3.4 Installer Dompdf (factures PDF)
À la racine du projet :
```bash
composer install
```
Cela installe `dompdf/dompdf` dans `vendor/`. Si vous ne pouvez pas lancer Composer sur le mutualisé, installez-le en local et envoyez le dossier `vendor/` par FTP.

### 3.5 Droits des dossiers
- `uploads/images/products/` : écriture (755 ou 777 selon l'hébergeur)
- `uploads/chat/images/` : écriture
- `uploads/pdfs/` : écriture (créé automatiquement, sinon à créer)
- Autres dossiers : lecture seule (755)
- Fichiers `.htaccess` : 644

### 3.6 Premier accès
Ouvrez `https://votresite.com/`. La page d'accueil s'affiche avec les données de démonstration.

---

## 4. Comptes par défaut (à changer immédiatement)

**Espace admin :** `https://votresite.com/admin/login.php`
- Identifiant : `admin`
- Mot de passe : `password` (à changer depuis le profil admin)

**Générer un nouveau hash de mot de passe sur le serveur :**
```bash
php -r "echo password_hash('VOTRE_MOT_DE_PASSE', PASSWORD_DEFAULT);"
```
Puis mettre à jour la table `admins` via phpMyAdmin.

---

## 5. Configuration recommandée en production

1. Connectez-vous en admin et changez le mot de passe par défaut.
2. Dans **Paramètres du site**, renseignez :
   - Nom du site, slogan, adresse, téléphone, email
   - Numéro WhatsApp pour les commandes (servira pour la redirection `wa.me`)
   - SIRET, TVA intracommunautaire, EORI
   - IBAN / BIC / nom de la banque (figurant sur les factures)
   - Fuseau horaire de l'admin, horaires du chat
   - Devise par défaut, activation/désactivation TVA, sélecteur de langue
3. Dans **Devises**, vérifiez/ajustez les taux de change (par rapport à l'EUR).
4. Dans **TVA**, vérifiez les taux par pays et la règle UE (destination vs vendeur).
5. Dans **Livraison**, vérifiez les zones, tarifs et pays rattachés ; bloquez les pays non desservis.
6. Personnalisez le contenu des pages légales dans la table `pages` (ou via phpMyAdmin).
7. Ajoutez vos propres photos produits dans `uploads/images/products/`.

---

## 6. Sécurité (vérifier avant mise en production)

- [ ] `.htaccess` bloque l'accès à `config/`, `includes/`, `api/`, `vendor/`
- [ ] `.htaccess` interdit l'exécution de scripts dans `uploads/`
- [ ] En-têtes de sécurité présents (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`)
- [ ] Mot de passe admin par défaut changé
- [ ] Sessions sécurisées (`httponly`, `secure` en HTTPS, `regenerate_id`)
- [ ] Jeton CSRF présent sur tous les formulaires
- [ ] Toutes les sorties échappées avec `htmlspecialchars` (fonction `e()`)
- [ ] Requêtes préparées PDO partout (aucune concaténation de variables dans les SQL)
- [ ] Limitation des tentatives de connexion (admin : 5 max, client : 5 max)
- [ ] HTTPS activé (le cookie de session est `secure` uniquement en HTTPS)

---

## 7. SEO et pages légales

- **URLs propres** : `/catalogue`, `/produit/REF-1001`, `/panier`, `/cgv`, `/mentions-legales`, etc.
- **Sitemap** : accessible à `/sitemap.xml` (généré dynamiquement par `sitemap.xml.php`)
- **Robots** : `/robots.txt` interdit l'indexation des dossiers sensibles et des tunnels de commande/paiement
- **Open Graph** : balises `og:` et Twitter Card sur chaque page
- **Langue** : site en français uniquement, pas de `hreflang` ni d'URLs par langue
- **Traduction** : widget Google Website Translator chargé uniquement après consentement cookie ; mention « La version française fait foi » sous le sélecteur et dans les CGV

---

## 8. Fonctionnement du flux commande → WhatsApp

1. Le visiteur remplit le formulaire de commande (`commande.php`).
2. À la validation, la commande est enregistrée en base (statut `nouvelle`) avec un numéro unique `CMD-YYYYMMDD-XXXX`.
3. Le visiteur est redirigé vers `https://wa.me/NUMERO?text=MESSAGE` avec un message prérempli **toujours en français** (infos client, détail des articles, frais de livraison, TVA, total, devise, numéro de commande).
4. Le paiement se fait hors ligne (virement, etc.).
5. Page de confirmation avec bouton pour rouvrir WhatsApp si la redirection a échoué.

---

## 9. Maintenance

- Sauvegardez régulièrement la base de données et le dossier `uploads/`.
- Surveillez les commandes en statut `nouvelle` dans l'admin pour les confirmer rapidement.
- Les conversations du chat sont conservées 12 mois (durée configurable dans la politique de confidentialité).
- Les commandes et factures sont conservées 10 ans (obligation comptable/fiscale, indiqué dans la politique de confidentialité RGPD).
