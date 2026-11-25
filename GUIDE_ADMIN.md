# Guide d’utilisation – Administration EuroImport Export

Ce guide explique comment utiliser l’espace administrateur du site **EuroImport Export** (import/export, paiement hors ligne, commande via WhatsApp).

## 1. Accès à l’administration

- **URL admin** : `http://localhost/import-export/admin/` (ou votre domaine + `/admin/`)
- **Identifiants par défaut** :
  - Utilisateur : `admin`
  - Mot de passe : `password`

> **Important** : changez le mot de passe admin après la mise en service.

Après connexion, vous arrivez sur le **Tableau de bord**.

## 2. Tableau de bord

Le tableau de bord affiche une vue globale :
- Nombre de produits, commandes, clients, revenus.
- Commandes récentes.
- Graphiques/statistiques (selon les données disponibles).

Utilisez le menu latéral pour accéder aux modules.

## 3. Produits / Articles

### 3.1. Liste des articles
Menu : **Articles** (`admin/articles.php`)

Vous pouvez :
- Voir tous les articles (référence, nom, prix, stock, statut).
- **Créer** un article : bouton « Nouvel article ».
- **Modifier** un article : bouton d’édition.
- **Supprimer** un article : bouton de suppression (avec confirmation). La suppression nettoie aussi les images associées et les lignes de commande liées.

### 3.2. Créer / modifier un article
Page : `admin/article-edit.php`

Champs principaux :
- **Référence** : identifiant produit unique (ex. `REF-001`).
- **Nom**, **description courte**, **description**.
- **Catégorie**.
- **Prix** (EUR), **poids**, **stock**.
- **Statut** : actif/inactif.
- **Origine**, **unité**, **MOQ** (quantité minimum), etc. selon le formulaire.

#### Images d’un article
Dans la section images :
- Cliquez sur **Choisir des fichiers** / zone d’upload pour ajouter une ou plusieurs images.
- Les formats image acceptés sont gérés par l’upload (JPG/PNG/WebP/GIF selon configuration).
- La première image peut être définie comme **image principale**.
- Vous pouvez **supprimer** une image ou la définir comme principale.
- Les images sont stockées dans `uploads/images/products/` et affichées automatiquement sur le catalogue, la fiche produit, le panier et l’admin.

> Si une image ne s’affiche pas, vérifiez qu’elle a bien été uploadée dans l’article et qu’elle existe dans `uploads/images/products/`.

### 3.3. Import d’articles
Si un module d’import est disponible dans votre version, utilisez-le depuis la section Articles/Import. Préparez un fichier conforme (colonnes attendues : référence, nom, prix, etc.) et vérifiez les erreurs signalées après import.

## 4. Catégories

Menu : **Catégories** (`admin/categories.php`)

Fonctionnalités CRUD :
- **Créer** une catégorie (nom, slug, parent, description, image).
- **Modifier** une catégorie.
- **Supprimer** une catégorie.

Règles :
- Une catégorie **racine** n’a pas de parent.
- Une catégorie contenant des **sous-catégories** ou des **produits** peut être protégée contre la suppression : déplacez/supprimez d’abord les éléments liés.

## 5. Commandes

Menu : **Commandes** (`admin/commandes.php`)

### 5.1. Liste des commandes
Vous pouvez :
- Voir le numéro de commande, client, date, total, statut.
- Ouvrir le détail d’une commande.
- **Supprimer** une commande (avec confirmation). Les lignes liées sont nettoyées.

### 5.2. Détail d’une commande
Page : `admin/commande-detail.php`

Vous y trouvez :
- Informations client (nom, email, téléphone, adresse, pays).
- Articles commandés, quantités, prix, totaux.
- Note client.
- Statut de commande.

Actions possibles :
- **Changer le statut** : nouvelle, confirmée, expédiée, livrée, annulée (selon les statuts disponibles).
- **Générer / télécharger la facture PDF** : bouton « Générer / télécharger la facture PDF ».

### 5.3. Flux de commande côté client
1. Le client ajoute des produits au panier.
2. Il remplit le formulaire de commande.
3. Il clique sur **Continuer vers WhatsApp**.
4. La commande est enregistrée dans l’admin.
5. Le client est redirigé vers la page de confirmation, puis WhatsApp s’ouvre automatiquement avec le message de commande prérempli.
6. Le paiement se fait **hors ligne** (virement/accord commercial), après échange WhatsApp.

## 6. Factures PDF

Menu : **Factures** (`admin/factures.php`)

Fonctionnalités :
- Lister les factures émises.
- **Aperçu** PDF dans le navigateur.
- **Téléchargement** PDF.
- **Annulation** : une facture émise n’est pas modifiée ; l’annulation génère un **avoir**.

Génération :
- Depuis une commande : `Générer / télécharger la facture PDF`.
- Si une facture existe déjà pour la commande, le système réutilise la facture émise (données figées).
- Les PDF sont enregistrés dans `uploads/pdfs/`.

Contenu de la facture :
- Entreprise (nom, adresse, téléphone, email, SIRET, TVA intracom, EORI, IBAN/BIC).
- Client et adresse de facturation.
- Références, désignations, quantités, prix, remises, totaux HT/TVA/TTC.
- Mentions légales et référence de virement.

> Les informations entreprise proviennent des **Paramètres**. Vérifiez-les avant d’émettre des factures.

## 7. Clients

Menu : **Clients** (`admin/clients.php`)

Fonctionnalités :
- Consulter la liste des clients.
- Ouvrir la fiche client (`admin/client-detail.php`) : coordonnées, commandes, factures.
- **Supprimer/anonymiser** un client depuis l’admin. L’anonymisation permet de conserver la cohérence comptable tout en retirant les données personnelles.

## 8. Chat / Messages

Menu : **Chat** (`admin/chat.php`)

Fonctionnalités :
- Voir les conversations clients.
- Lire les messages.
- Répondre aux clients.
- Suivre les conversations non lues.

Le chat côté public permet au visiteur/client d’envoyer un message ; l’admin répond depuis cet écran.

## 9. Newsletter

Menu : **Newsletter** (`admin/newsletter.php`)

Fonctionnalités :
- Voir les abonnés inscrits.
- **Supprimer** un abonné si nécessaire (demande RGPD/désinscription manuelle).

## 10. Pages de contenu

Menu : **Pages** (`admin/pages.php`)

Fonctionnalités :
- Modifier les pages de contenu du site (ex. À propos, Mentions légales, FAQ, conditions).
- Mettre à jour le titre, le contenu HTML/texte, le SEO (titre/meta description selon champs disponibles).
- Activer/désactiver une page si l’option existe.

## 11. Paramètres

Menu : **Paramètres** (`admin/settings.php`)

C’est l’écran central pour la configuration du site. Vous pouvez modifier notamment :
- **Identité du site** : nom, slogan.
- **SEO** : meta title, meta description.
- **Contact** : téléphone public, email, adresse.
- **WhatsApp commande** : numéro utilisé pour le bouton WhatsApp et les messages de commande.
- **Réseaux sociaux** : Facebook, Instagram, LinkedIn.
- **Informations légales/fiscales** : SIRET, TVA intracommunautaire, EORI.
- **Informations bancaires** : IBAN, BIC, banque (affichées sur facture).

Après modification, cliquez sur **Enregistrer**. Un message de confirmation s’affiche.

> Si vous modifiez le numéro WhatsApp, testez une commande pour vérifier l’ouverture du bon contact.

## 12. Administrateurs

Menu : **Administrateurs** (`admin/admins.php`)

Fonctionnalités CRUD :
- **Créer** un administrateur.
- **Modifier** les informations selon les champs disponibles.
- **Supprimer** un administrateur.

Protections :
- Vous ne pouvez pas supprimer votre propre compte admin connecté.
- Le dernier **superadmin** ne peut pas être supprimé.

## 13. Médias et fichiers

Répertoires importants :
- `uploads/images/products/` : images produits.
- `uploads/pdfs/` : factures PDF générées.

Bonnes pratiques :
- Utilisez des images optimisées (taille raisonnable, nom propre).
- Ne supprimez pas manuellement des fichiers référencés en base sans vérifier l’impact.
- Sauvegardez régulièrement le dossier `uploads/` et la base de données.

## 14. Sauvegarde et maintenance

### Sauvegarde recommandée
- **Base de données** : export MySQL régulier (phpMyAdmin ou `mysqldump`).
- **Fichiers** : sauvegarde du dossier projet, surtout `uploads/`, `config/`, et les fichiers personnalisés.

### En cas de problème
- **Image produit invisible** : vérifier l’upload dans l’article et la présence du fichier dans `uploads/images/products/`.
- **WhatsApp ne s’ouvre pas** : vérifier le numéro dans **Paramètres > WhatsApp commande** au format international (ex. `+33 6 12 34 56 78`).
- **Facture PDF** : vérifier que la commande existe, que les paramètres entreprise sont remplis, et que `uploads/pdfs/` est accessible en écriture.
- **Erreur après modification** : vérifier les logs PHP Apache/XAMPP et la configuration `config/config.php`.

## 15. Sécurité

- Changez le mot de passe admin par défaut.
- Utilisez des mots de passe forts pour tous les comptes admin.
- Limitez l’accès au dossier `/admin/` aux personnes autorisées.
- Gardez XAMPP/PHP/MySQL à jour en environnement de production.
- Effectuez des sauvegardes avant toute modification importante.

---

**Fichiers utiles** :
- Configuration : `config/config.php`
- Fonctions globales : `includes/functions.php`
- Bootstrap/config DB : `includes/config_bootstrap.php`
- API facture : `api/admin/facture.php`
- Guide : `GUIDE_ADMIN.md`
