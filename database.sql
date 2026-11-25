-- =============================================================================
-- Base de données : site e-commerce Import-Export
-- Moteur : MySQL / MariaDB — InnoDB — utf8mb4
-- Aucune table de traduction n'est prévue (traduction automatique côté navigateur uniquement)
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `import_export`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `import_export`;

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------
-- Table : admins
-- -----------------------------------------------------
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('superadmin','admin') NOT NULL DEFAULT 'admin',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admins_username` (`username`),
  UNIQUE KEY `uk_admins_email` (`email`),
  KEY `idx_admins_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table : settings (paramètres du site, clés/valeurs)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(100) NOT NULL,
  `value` TEXT DEFAULT NULL,
  `type` ENUM('text','number','boolean','textarea','url','color') NOT NULL DEFAULT 'text',
  `group_name` VARCHAR(50) NOT NULL DEFAULT 'general',
  `label` VARCHAR(255) DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_settings_key` (`key`),
  KEY `idx_settings_group` (`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table : currencies (devises)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `currencies`;
CREATE TABLE `currencies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` CHAR(3) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `symbol` VARCHAR(10) NOT NULL,
  `exchange_rate` DECIMAL(12,6) NOT NULL DEFAULT 1.000000 COMMENT 'Taux par rapport à la devise de base (EUR)',
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_currencies_code` (`code`),
  KEY `idx_currencies_default` (`is_default`),
  KEY `idx_currencies_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table : countries (pays, indicatifs téléphoniques)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `countries`;
CREATE TABLE `countries` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `iso_alpha2` CHAR(2) NOT NULL,
  `iso_alpha3` CHAR(3) DEFAULT NULL,
  `phone_code` VARCHAR(10) NOT NULL,
  `continent` VARCHAR(50) DEFAULT NULL,
  `is_eu` TINYINT(1) NOT NULL DEFAULT 0,
  `is_blocked` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Pays non desservi / bloqué par l admin',
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_countries_iso2` (`iso_alpha2`),
  UNIQUE KEY `uk_countries_iso3` (`iso_alpha3`),
  KEY `idx_countries_eu` (`is_eu`),
  KEY `idx_countries_blocked` (`is_blocked`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table : categories (arborescence multi-niveaux)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` INT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_categories_slug` (`slug`),
  KEY `idx_categories_parent` (`parent_id`),
  KEY `idx_categories_active` (`is_active`),
  CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table : products (articles)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED DEFAULT NULL,
  `reference` VARCHAR(100) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `short_description` TEXT DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `price` DECIMAL(12,4) NOT NULL DEFAULT 0.0000 COMMENT 'Prix de base en devise de base (EUR)',
  `weight` DECIMAL(10,3) NOT NULL DEFAULT 0.000 COMMENT 'Poids en kg',
  `stock` INT NOT NULL DEFAULT 0,
  `min_stock` INT DEFAULT NULL,
  `rating` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Note attribuée par l admin (1 à 5)',
  `sku` VARCHAR(100) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `is_bestseller` TINYINT(1) NOT NULL DEFAULT 0,
  `is_on_sale` TINYINT(1) NOT NULL DEFAULT 0,
  `sale_price` DECIMAL(12,4) DEFAULT NULL,
  `meta_title` VARCHAR(255) DEFAULT NULL,
  `meta_description` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_products_reference` (`reference`),
  KEY `idx_products_category` (`category_id`),
  KEY `idx_products_active` (`is_active`),
  KEY `idx_products_featured` (`is_featured`),
  KEY `idx_products_bestseller` (`is_bestseller`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table : product_images (plusieurs photos par article)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `product_images`;
CREATE TABLE `product_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `image` VARCHAR(255) NOT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `alt_text` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_images_product` (`product_id`),
  CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table : shipping_zones (zones de livraison)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `shipping_zones`;
CREATE TABLE `shipping_zones` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shipping_zones_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table : shipping_zone_countries (pays rattachés aux zones)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `shipping_zone_countries`;
CREATE TABLE `shipping_zone_countries` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `zone_id` INT UNSIGNED NOT NULL,
  `country_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_shipping_zone_countries` (`zone_id`,`country_id`),
  KEY `idx_shipping_zone_countries_zone` (`zone_id`),
  KEY `idx_shipping_zone_countries_country` (`country_id`),
  CONSTRAINT `fk_szc_zone` FOREIGN KEY (`zone_id`) REFERENCES `shipping_zones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_szc_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table : shipping_rates (tarifs de livraison par zone)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `shipping_rates`;
CREATE TABLE `shipping_rates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `zone_id` INT UNSIGNED NOT NULL,
  `type` ENUM('fixed','per_weight','per_amount_tier') NOT NULL DEFAULT 'fixed',
  `min_weight` DECIMAL(10,3) DEFAULT NULL,
  `max_weight` DECIMAL(10,3) DEFAULT NULL,
  `min_amount` DECIMAL(12,4) DEFAULT NULL,
  `max_amount` DECIMAL(12,4) DEFAULT NULL,
  `price` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `currency_code` CHAR(3) DEFAULT NULL,
  `delivery_days_min` INT UNSIGNED DEFAULT NULL,
  `delivery_days_max` INT UNSIGNED DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shipping_rates_zone` (`zone_id`),
  CONSTRAINT `fk_shipping_rates_zone` FOREIGN KEY (`zone_id`) REFERENCES `shipping_zones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table : vat_rates (taux de TVA par pays)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `vat_rates`;
CREATE TABLE `vat_rates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `country_iso_alpha2` CHAR(2) NOT NULL,
  `country_name` VARCHAR(150) DEFAULT NULL,
  `rate` DECIMAL(5,4) NOT NULL DEFAULT 0.0000 COMMENT 'Taux en pourcentage (ex. 20.0000)',
  `type` ENUM('standard','reduced','zero','exempt') NOT NULL DEFAULT 'standard',
  `is_eu` TINYINT(1) NOT NULL DEFAULT 0,
  `notes` VARCHAR(255) DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vat_rates_country` (`country_iso_alpha2`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table : customers (clients)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) DEFAULT NULL COMMENT 'NULL pour les commandes invitées',
  `firstname` VARCHAR(100) NOT NULL,
  `lastname` VARCHAR(100) NOT NULL,
  `company` VARCHAR(255) DEFAULT NULL,
  `vat_number` VARCHAR(50) DEFAULT NULL COMMENT 'N° de TVA intracommunautaire',
  `phone` VARCHAR(50) DEFAULT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `postcode` VARCHAR(20) DEFAULT NULL,
  `country_id` INT UNSIGNED DEFAULT NULL,
  `timezone` VARCHAR(100) NOT NULL DEFAULT 'UTC',
  `email_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `email_unsubscribed` TINYINT(1) NOT NULL DEFAULT 0,
  `reset_token` VARCHAR(255) DEFAULT NULL,
  `reset_expires` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_customers_email` (`email`),
  KEY `idx_customers_country` (`country_id`),
  CONSTRAINT `fk_customers_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table : orders (commandes, statut nouvelle à la création)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_number` VARCHAR(50) NOT NULL,
  `customer_id` INT UNSIGNED DEFAULT NULL,
  `guest_email` VARCHAR(255) DEFAULT NULL,
  `currency_code` CHAR(3) NOT NULL,
  `exchange_rate` DECIMAL(12,6) NOT NULL DEFAULT 1.000000,
  `subtotal` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `shipping_amount` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `vat_amount` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `discount_amount` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `total` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `status` ENUM('nouvelle','confirmée','expédiée','livrée','annulée') NOT NULL DEFAULT 'nouvelle',
  `payment_method` VARCHAR(50) DEFAULT NULL COMMENT 'virement, autre...',
  `payment_status` ENUM('en_attente','reçu','échoué','remboursé') NOT NULL DEFAULT 'en_attente',
  `tracking_number` VARCHAR(100) DEFAULT NULL,
  `tracking_carrier` VARCHAR(100) DEFAULT NULL,
  `customer_note` TEXT DEFAULT NULL,
  `admin_note` TEXT DEFAULT NULL,
  `firstname` VARCHAR(100) NOT NULL,
  `lastname` VARCHAR(100) NOT NULL,
  `company` VARCHAR(255) DEFAULT NULL,
  `vat_number` VARCHAR(50) DEFAULT NULL,
  `phone` VARCHAR(50) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `address` VARCHAR(255) NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `postcode` VARCHAR(20) NOT NULL,
  `country_id` INT UNSIGNED NOT NULL,
  `country_name` VARCHAR(150) NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_orders_number` (`order_number`),
  KEY `idx_orders_customer` (`customer_id`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_orders_created` (`created_at`),
  KEY `idx_orders_country` (`country_id`),
  CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table : order_items (lignes de commande figées)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED DEFAULT NULL,
  `reference` VARCHAR(100) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `sku` VARCHAR(100) DEFAULT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `unit_price_currency` CHAR(3) NOT NULL,
  `discount` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `total` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `weight` DECIMAL(10,3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order` (`order_id`),
  KEY `idx_order_items_product` (`product_id`),
  KEY `idx_order_items_reference` (`reference`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table : invoices (factures, données figées à l émission)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_number` VARCHAR(50) NOT NULL,
  `order_id` INT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED DEFAULT NULL,
  `invoice_date` DATETIME NOT NULL,
  `due_date` DATETIME DEFAULT NULL,
  `delivery_date` DATETIME DEFAULT NULL,
  `subtotal_ht` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `vat_amount` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `vat_rate` DECIMAL(5,4) DEFAULT NULL,
  `vat_label` VARCHAR(100) DEFAULT NULL,
  `shipping_ht` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `discount_ht` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `total_ttc` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `currency_code` CHAR(3) NOT NULL,
  `status` ENUM('émise','annulée') NOT NULL DEFAULT 'émise',
  `credit_note_id` INT UNSIGNED DEFAULT NULL,
  `pdf_path` VARCHAR(255) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_invoices_number` (`invoice_number`),
  UNIQUE KEY `uk_invoices_order` (`order_id`),
  KEY `idx_invoices_customer` (`customer_id`),
  KEY `idx_invoices_date` (`invoice_date`),
  CONSTRAINT `fk_invoices_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  CONSTRAINT `fk_invoices_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_invoices_credit_note` FOREIGN KEY (`credit_note_id`) REFERENCES `credit_notes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table : invoice_items (lignes de facture)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `invoice_items`;
CREATE TABLE `invoice_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` INT UNSIGNED NOT NULL,
  `reference` VARCHAR(100) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price_ht` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `discount` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `total_ht` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `vat_rate` DECIMAL(5,4) DEFAULT NULL,
  `total_ttc` DECIMAL(12,4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_invoice_items_invoice` (`invoice_id`),
  CONSTRAINT `fk_invoice_items_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table : credit_notes (avoirs, jamais de modification de facture émise)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `credit_notes`;
CREATE TABLE `credit_notes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `credit_number` VARCHAR(50) NOT NULL,
  `invoice_id` INT UNSIGNED NOT NULL,
  `credit_date` DATETIME NOT NULL,
  `amount_ht` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `vat_amount` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `total_ttc` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `reason` TEXT DEFAULT NULL,
  `status` ENUM('émis','utilisé') NOT NULL DEFAULT 'émis',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_credit_notes_number` (`credit_number`),
  KEY `idx_credit_notes_invoice` (`invoice_id`),
  CONSTRAINT `fk_credit_notes_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table : newsletter_subscribers (abonnement newsletter)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `newsletter_subscribers`;
CREATE TABLE `newsletter_subscribers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `subscribed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `unsubscribed_at` DATETIME DEFAULT NULL,
  `source` VARCHAR(50) NOT NULL DEFAULT 'website',
  `consent_ip` VARCHAR(45) DEFAULT NULL,
  `token` VARCHAR(100) NOT NULL COMMENT 'Jeton de désabonnement unique',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_newsletter_email` (`email`),
  UNIQUE KEY `uk_newsletter_token` (`token`),
  KEY `idx_newsletter_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table : conversations (messagerie d'assistance)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `conversations`;
CREATE TABLE `conversations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED DEFAULT NULL,
  `visitor_name` VARCHAR(100) DEFAULT NULL,
  `visitor_email` VARCHAR(255) DEFAULT NULL,
  `visitor_ip` VARCHAR(45) DEFAULT NULL,
  `status` ENUM('ouverte','fermée','bloquée') NOT NULL DEFAULT 'ouverte',
  `unread_by_admin` TINYINT(1) NOT NULL DEFAULT 1,
  `last_message_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conversations_status` (`status`),
  KEY `idx_conversations_customer` (`customer_id`),
  KEY `idx_conversations_last_message` (`last_message_at`),
  CONSTRAINT `fk_conversations_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table : messages (messages de conversation, non traduits)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` INT UNSIGNED NOT NULL,
  `sender` ENUM('visitor','admin') NOT NULL,
  `message` TEXT NOT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_messages_conversation` (`conversation_id`),
  KEY `idx_messages_created` (`created_at`),
  KEY `idx_messages_read` (`is_read`),
  CONSTRAINT `fk_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table : banners (bannières de la page d'accueil)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `banners`;
CREATE TABLE `banners` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) DEFAULT NULL,
  `subtitle` VARCHAR(255) DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `link_url` VARCHAR(255) DEFAULT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_banners_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Table : pages (pages statiques : CGV, mentions légales, etc.)
-- -----------------------------------------------------
DROP TABLE IF EXISTS `pages`;
CREATE TABLE `pages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(100) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `content` LONGTEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `in_footer` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pages_slug` (`slug`),
  KEY `idx_pages_footer` (`in_footer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- DONNÉES DE DÉMONSTRATION (en français)
-- =============================================================================

-- Devise par défaut : euro
INSERT INTO `currencies` (`code`,`name`,`symbol`,`exchange_rate`,`is_default`,`is_active`,`sort_order`) VALUES
('EUR','Euro','€',1.000000,1,1,1),
('USD','Dollar américain','$',1.085000,0,1,2),
('GBP','Livre sterling','£',0.845000,0,1,3);

-- Pays (codes ISO, indicatifs téléphoniques, UE)
INSERT INTO `countries` (`name`,`iso_alpha2`,`iso_alpha3`,`phone_code`,`continent`,`is_eu`,`is_blocked`,`sort_order`) VALUES
('France','FR','FRA','+33','Europe',1,0,1),
('Allemagne','DE','DEU','+49','Europe',1,0,2),
('Belgique','BE','BEL','+32','Europe',1,0,3),
('Italie','IT','ITA','+39','Europe',1,0,4),
('Espagne','ES','ESP','+34','Europe',1,0,5),
('Pays-Bas','NL','NLD','+31','Europe',1,0,6),
('Portugal','PT','PRT','+351','Europe',1,0,7),
('Suisse','CH','CHE','+41','Europe',0,0,8),
('Royaume-Uni','GB','GBR','+44','Europe',0,0,9),
('Turquie','TR','TUR','+90','Europe / Asie',0,0,10),
('États-Unis','US','USA','+1','Amérique du Nord',0,0,11),
('Canada','CA','CAN','+1','Amérique du Nord',0,0,12),
('Mexique','MX','MEX','+52','Amérique du Nord',0,0,13),
('Brésil','BR','BRA','+55','Amérique du Sud',0,0,14),
('Chine','CN','CHN','+86','Asie',0,0,15),
('Japon','JP','JPN','+81','Asie',0,0,16),
('Inde','IN','IND','+91','Asie',0,0,17),
('Émirats arabes unis','AE','ARE','+971','Asie',0,0,18),
('Arabie saoudite','SA','SAU','+966','Asie',0,0,19),
('Singapour','SG','SGP','+65','Asie',0,0,20),
('Maroc','MA','MAR','+212','Afrique',0,0,21),
('Tunisie','TN','TUN','+216','Afrique',0,0,22),
('Algérie','DZ','DZA','+213','Afrique',0,0,23),
('Côte d’Ivoire','CI','CIV','+225','Afrique',0,0,24),
('Sénégal','SN','SEN','+221','Afrique',0,0,25),
('Australie','AU','AUS','+61','Océanie',0,0,26),
('Nouvelle-Zélande','NZ','NZL','+64','Océanie',0,0,27);

-- Zones de livraison
INSERT INTO `shipping_zones` (`name`,`description`,`is_active`,`sort_order`) VALUES
('France','Livraison en France métropolitaine',1,1),
('Union européenne','Livraison dans les pays de l Union européenne',1,2),
('Europe hors UE','Livraison en Europe hors Union européenne (Suisse, Royaume-Uni, Turquie...)',1,3),
('Amérique du Nord','Livraison aux États-Unis, Canada, Mexique',1,4),
('Amérique du Sud','Livraison en Amérique du Sud (Brésil...)',1,5),
('Afrique','Livraison en Afrique (Maroc, Tunisie, Algérie, Côte d’Ivoire, Sénégal...)',1,6),
('Asie','Livraison en Asie (Chine, Japon, Inde, Émirats... )',1,7),
('Océanie','Livraison en Océanie (Australie, Nouvelle-Zélande)',1,8);

-- Rattachement pays → zones
INSERT INTO `shipping_zone_countries` (`zone_id`,`country_id`) VALUES
(1,1),
(2,2),(2,3),(2,4),(2,5),(2,6),(2,7),
(3,8),(3,9),(3,10),
(4,11),(4,12),(4,13),
(5,14),
(6,21),(6,22),(6,23),(6,24),(6,25),
(7,15),(7,16),(7,17),(7,18),(7,19),(7,20),
(8,26),(8,27);

-- Tarifs de livraison (fixe par zone, devise EUR)
INSERT INTO `shipping_rates` (`zone_id`,`type`,`price`,`currency_code`,`delivery_days_min`,`delivery_days_max`,`is_active`) VALUES
(1,'fixed',5.9000,'EUR',2,4,1),
(2,'fixed',9.9000,'EUR',3,6,1),
(3,'fixed',14.9000,'EUR',5,8,1),
(4,'fixed',24.9000,'EUR',6,10,1),
(5,'fixed',29.9000,'EUR',8,14,1),
(6,'fixed',19.9000,'EUR',7,12,1),
(7,'fixed',24.9000,'EUR',8,14,1),
(8,'fixed',34.9000,'EUR',10,16,1);

-- Taux de TVA par pays (en %)
INSERT INTO `vat_rates` (`country_iso_alpha2`,`country_name`,`rate`,`type`,`is_eu`,`notes`,`updated_at`) VALUES
('FR','France',20.0000,'standard',1,'Taux normal','2026-01-01 00:00:00'),
('DE','Allemagne',19.0000,'standard',1,'Taux normal','2026-01-01 00:00:00'),
('BE','Belgique',21.0000,'standard',1,'Taux normal','2026-01-01 00:00:00'),
('IT','Italie',22.0000,'standard',1,'Taux normal','2026-01-01 00:00:00'),
('ES','Espagne',21.0000,'standard',1,'Taux normal','2026-01-01 00:00:00'),
('NL','Pays-Bas',21.0000,'standard',1,'Taux normal','2026-01-01 00:00:00'),
('PT','Portugal',23.0000,'standard',1,'Taux normal','2026-01-01 00:00:00'),
('CH','Suisse',0.0000,'zero',0,'Exportation, TVA non applicable','2026-01-01 00:00:00'),
('GB','Royaume-Uni',0.0000,'zero',0,'Exportation, TVA non applicable','2026-01-01 00:00:00'),
('TR','Turquie',0.0000,'zero',0,'Exportation, TVA non applicable','2026-01-01 00:00:00'),
('US','États-Unis',0.0000,'zero',0,'Exportation, TVA non applicable','2026-01-01 00:00:00'),
('CA','Canada',0.0000,'zero',0,'Exportation, TVA non applicable','2026-01-01 00:00:00'),
('MX','Mexique',0.0000,'zero',0,'Exportation, TVA non applicable','2026-01-01 00:00:00'),
('BR','Brésil',0.0000,'zero',0,'Exportation, TVA non applicable','2026-01-01 00:00:00'),
('CN','Chine',0.0000,'zero',0,'Exportation, TVA non applicable','2026-01-01 00:00:00'),
('JP','Japon',0.0000,'zero',0,'Exportation, TVA non applicable','2026-01-01 00:00:00'),
('IN','Inde',0.0000,'zero',0,'Exportation, TVA non applicable','2026-01-01 00:00:00'),
('AE','Émirats arabes unis',0.0000,'zero',0,'Exportation, TVA non applicable','2026-01-01 00:00:00'),
('SA','Arabie saoudite',0.0000,'zero',0,'Exportation, TVA non applicable','2026-01-01 00:00:00'),
('SG','Singapour',0.0000,'zero',0,'Exportation, TVA non applicable','2026-01-01 00:00:00'),
('MA','Maroc',0.0000,'zero',0,'Exportation, TVA non applicable','2026-01-01 00:00:00'),
('TN','Tunisie',0.0000,'zero',0,'Exportation, TVA non applicable','2026-01-01 00:00:00'),
('DZ','Algérie',0.0000,'zero',0,'Exportation, TVA non applicable','2026-01-01 00:00:00'),
('CI','Côte d’Ivoire',0.0000,'zero',0,'Exportation, TVA non applicable','2026-01-01 00:00:00'),
('SN','Sénégal',0.0000,'zero',0,'Exportation, TVA non applicable','2026-01-01 00:00:00'),
('AU','Australie',0.0000,'zero',0,'Exportation, TVA non applicable','2026-01-01 00:00:00'),
('NZ','Nouvelle-Zélande',0.0000,'zero',0,'Exportation, TVA non applicable','2026-01-01 00:00:00');

-- Catégories et sous-catégories (8 niveaux 1, 32 niveaux 2)
INSERT INTO `categories` (`parent_id`,`name`,`slug`,`description`,`sort_order`,`is_active`) VALUES
(NULL,'Électronique','electronique','Produits électroniques et high-tech',1,1),
(NULL,'Vêtement','vetement','Vêtements et accessoires de mode',2,1),
(NULL,'Maison & Jardin','maison-jardin','Équipement de la maison et du jardin',3,1),
(NULL,'Alimentation','alimentation','Produits alimentaires et boissons',4,1),
(NULL,'Sport & Loisirs','sport-loisirs','Matériel de sport et loisirs',5,1),
(NULL,'Beauté & Santé','beaute-sante','Cosmétique, parfums et hygiène',6,1),
(NULL,'Bricolage & Outillage','bricolage-outillage','Outils et quincaillerie',7,1),
(NULL,'Auto & Moto','auto-moto','Pièces et accessoires automobiles',8,1),
(1,'Ordinateurs','ordinateurs','Ordinateurs portables et de bureau',1,1),
(1,'Téléphones','telephones','Smartphones et téléphones mobiles',2,1),
(1,'Audio & Casques','audio-casques','Casques, écouteurs et enceintes',3,1),
(1,'Appareils photo','appareils-photo','Appareils photo et optique',4,1),
(2,'Homme','homme','Vêtements homme',1,1),
(2,'Femme','femme','Vêtements femme',2,1),
(2,'Enfants','enfants','Vêtements enfants',3,1),
(2,'Chaussures','chaussures','Chaussures',4,1),
(3,'Cuisine','cuisine','Électroménager et ustensiles de cuisine',1,1),
(3,'Éclairage','eclairage','Lampes et luminaires',2,1),
(3,'Meubles','meubles','Meubles et rangements',3,1),
(3,'Décoration','decoration','Objets de décoration',4,1),
(4,'Épicerie sucrée','epicerie-sucree','Produits d epicerie sucrée',1,1),
(4,'Épicerie salée','epicerie-salee','Produits d epicerie salée',2,1),
(4,'Boissons','boissons','Boissons',3,1),
(4,'Produits frais','produits-frais','Produits frais',4,1),
(5,'Cyclisme','cyclisme','Vélos et accessoires cyclisme',1,1),
(5,'Fitness','fitness','Matériel fitness',2,1),
(5,'Camping','camping','Équipement camping',3,1),
(5,'Sports d équipe','sports-equipe','Sports collectifs',4,1),
(6,'Soins du visage','soins-visage','Soins visage',1,1),
(6,'Cheveux','cheveux','Soins cheveux',2,1),
(6,'Parfums','parfums','Parfums et fragrances',3,1),
(6,'Hygiène','hygiene','Hygiène corporelle',4,1),
(7,'Outils électriques','outils-electriques','Outils électroportatifs',1,1),
(7,'Outils à main','outils-main','Outils manuels',2,1),
(7,'Quincaillerie','quincaillerie','Visserie et quincaillerie',3,1),
(7,'Jardinage','jardinage','Outils de jardinage',4,1),
(8,'Pièces auto','pieces-auto','Pièces détachées auto',1,1),
(8,'Accessoires','accessoires-auto','Accessoires auto',2,1),
(8,'Pneumatiques','pneumatiques','Pneus et roues',3,1),
(8,'Équipement moto','equipement-moto','Équipement moto',4,1);

-- Articles de démonstration (prix en EUR, stock, poids kg, note admin 1-5)
INSERT INTO `products` (`category_id`,`reference`,`name`,`short_description`,`description`,`price`,`weight`,`stock`,`min_stock`,`rating`,`sku`,`is_active`,`is_featured`,`is_bestseller`,`is_on_sale`,`sale_price`,`meta_title`,`meta_description`,`created_at`) VALUES
(9,'REF-1001','Ordinateur portable ProBook 15″','Ordinateur professionnel 15 pouces, processeur récent, SSD rapide.','Ordinateur portable ProBook 15 pouces destiné aux professionnels. Écran Full HD, processeur dernière génération, mémoire vive 16 Go, SSD 512 Go. Idéal pour la bureautique et le déplacements internationaux.',899.0000,1.850,12,2,5,'PB-15-001',1,1,0,0,NULL,'Ordinateur portable ProBook 15 pouces','Ordinateur portable professionnel 15 pouces, livraison mondiale.','2026-10-01 10:00:00'),
(10,'REF-1002','Smartphone Titan X','Smartphone dernier cri, écran bord à bord, 128 Go.','Smartphone Titan X avec écran OLED 6,7 pouces, 128 Go de stockage, triple capteur photo et autonomie longue durée.',649.0000,0.195,34,5,4,'TX-128',1,0,1,0,NULL,'Smartphone Titan X','Smartphone dernier cri en promotion, expédition mondiale.','2026-10-02 10:00:00'),
(11,'REF-1003','Casque sans fil Sonic','Casque audio Bluetooth avec réduction de bruit active.','Casque sans fil Sonic, réduction de bruit active, autonomie 30 heures, pliable pour le voyage.',149.0000,0.260,56,10,5,'SON-HP-01',1,0,0,0,NULL,'Casque sans fil Sonic','Casque audio Bluetooth avec réduction de bruit.','2026-10-03 10:00:00'),
(11,'REF-1004','Enceinte portable Boom','Enceinte Bluetooth étanche, son puissant.','Enceinte portable Boom, étanche IPX7, autonomie 15 heures, son stéréo puissant.',79.0000,0.580,41,5,4,'BOOM-SP',1,0,0,1,59.0000,'Enceinte portable Boom','Enceinte Bluetooth étanche en promotion.','2026-10-04 10:00:00'),
(13,'REF-2001','T-shirt coton bio','T-shirt en coton biologique, coupe classique.','T-shirt homme en coton biologique certifié, coupe confortable, plusieurs coloris.',24.9000,0.200,120,20,4,'TS-BIO-H',1,0,0,0,NULL,'T-shirt coton bio homme','T-shirt en coton biologique, livraison internationale.','2026-10-05 10:00:00'),
(13,'REF-2002','Jean slim denim','Jean slim pour homme, denim lavé.','Jean slim denim pour homme, coupe ajustée, finition lavé.',59.0000,0.620,45,10,4,'JE-SLIM-H',1,0,0,0,NULL,'Jean slim denim homme','Jean slim en denim, expédition dans le monde entier.','2026-10-06 10:00:00'),
(14,'REF-2003','Robe d’été légère','Robe femme légère pour l été, imprimée.','Robe d été légère pour femme, tissu fluide, motif imprimé.',45.0000,0.280,33,5,5,'RO-ETE-F',1,0,0,0,NULL,'Robe d été légère femme','Robe femme légère pour la saison estivale.','2026-10-07 10:00:00'),
(17,'REF-3001','Cafetière expresso','Cafetière expresso à grains, broyeur intégré.','Cafetière expresso automatique avec broyeur à grains intégré, réservoir 1,8 L.',129.0000,3.700,18,3,5,'CF-EXP-01',1,1,0,0,NULL,'Cafetière expresso à grains','Cafetière expresso professionnelle pour la maison.','2026-10-08 10:00:00'),
(18,'REF-3002','Lampe de bureau LED','Lampe de bureau LED avec variateur d intensité.','Lampe de bureau LED, bras articulé, variateur de luminosité, température de couleur réglable.',34.9000,0.950,62,10,4,'LP-LED',1,0,0,0,NULL,'Lampe de bureau LED','Lampe de bureau LED design et fonctionnelle.','2026-10-09 10:00:00'),
(17,'REF-3003','Couteau de chef','Couteau de chef en acier inoxydable.','Couteau de chef 20 cm, acier inoxydable allemand, manche ergonomique.',48.0000,0.240,29,5,5,'CK-CHEF',1,0,0,0,NULL,'Couteau de chef acier inoxydable','Couteau de chef pour cuisine professionnelle.','2026-10-10 10:00:00'),
(22,'REF-4001','Huile d’olive extra vierge 500 ml','Huile d’olive extra vierge pressée à froid.','Huile d’olive extra vierge, origine Méditerranée, bouteille 500 ml.',11.5000,0.560,200,30,5,'OLIVE-500',1,0,0,0,NULL,'Huile d olive extra vierge','Huile d olive extra vierge 500 ml, expédiée dans le monde.','2026-10-11 10:00:00'),
(23,'REF-4002','Café en grains 1 kg','Café en grains d origine, torréfaction artisanale.','Sachet de café en grains 1 kg, torréfaction artisanale, arôme équilibré.',16.9000,1.050,85,15,4,'COF-1KG',1,0,0,0,NULL,'Café en grains 1 kg','Café en grains d origine, livraison internationale.','2026-10-12 10:00:00'),
(25,'REF-5001','Vélo tout chemin','Vélo tout chemin 28 pouces, cadre aluminium.','Vélo tout chemin pour ville et chemins, cadre aluminium, 21 vitesses, freins à disque.',449.0000,16.500,7,2,4,'VTC-28',1,0,1,0,NULL,'Vélo tout chemin 28 pouces','Vélo tout chemin robuste, expédition mondiale.','2026-10-13 10:00:00'),
(30,'REF-6001','Parfum intense','Parfum intenso, vaporisateur 100 ml.','Parfum intense aux notes boisées et épicées, flacon 100 ml.',79.0000,0.350,40,8,4,'PARF-INT',1,0,0,0,NULL,'Parfum intense 100 ml','Parfum homme intense, livraison dans le monde entier.','2026-10-14 10:00:00'),
(33,'REF-7001','Perceuse-visseuse sans fil','Perceuse-visseuse 18 V, 2 batteries.','Perceuse-visseuse sans fil 18 V, couple 50 Nm, 2 batteries Lithium, chargeur rapide.',89.0000,1.400,22,4,4,'DRILL-18V',1,0,0,0,NULL,'Perceuse-visseuse sans fil 18 V','Perceuse-visseuse sans fil pour bricolage.','2026-10-15 10:00:00');

-- Photos des articles (première image = principale)
INSERT INTO `product_images` (`product_id`,`image`,`is_primary`,`sort_order`,`alt_text`) VALUES
(1,'uploads/images/products/ref-1001-1.jpg',1,1,'Ordinateur portable ProBook 15 pouces'),
(1,'uploads/images/products/ref-1001-2.jpg',0,2,'Ordinateur portable ProBook - clavier'),
(2,'uploads/images/products/ref-1002-1.jpg',1,1,'Smartphone Titan X face avant'),
(2,'uploads/images/products/ref-1002-2.jpg',0,2,'Smartphone Titan X face arrière'),
(3,'uploads/images/products/ref-1003-1.jpg',1,1,'Casque sans fil Sonic'),
(4,'uploads/images/products/ref-1004-1.jpg',1,1,'Enceinte portable Boom'),
(5,'uploads/images/products/ref-2001-1.jpg',1,1,'T-shirt coton bio'),
(6,'uploads/images/products/ref-2002-1.jpg',1,1,'Jean slim denim'),
(7,'uploads/images/products/ref-2003-1.jpg',1,1,'Robe d été légère'),
(8,'uploads/images/products/ref-3001-1.jpg',1,1,'Cafetière expresso'),
(9,'uploads/images/products/ref-3002-1.jpg',1,1,'Lampe de bureau LED'),
(10,'uploads/images/products/ref-3003-1.jpg',1,1,'Couteau de chef'),
(11,'uploads/images/products/ref-4001-1.jpg',1,1,'Huile d olive extra vierge'),
(12,'uploads/images/products/ref-4002-1.jpg',1,1,'Café en grains'),
(13,'uploads/images/products/ref-5001-1.jpg',1,1,'Vélo tout chemin'),
(14,'uploads/images/products/ref-6001-1.jpg',1,1,'Parfum intense'),
(15,'uploads/images/products/ref-7001-1.jpg',1,1,'Perceuse-visseuse sans fil');

-- Paramètres du site
INSERT INTO `settings` (`key`,`value`,`type`,`group_name`,`label`) VALUES
('site_name','EuroImport Export','text','general','Nom du site'),
('site_slogan','Import-export professionnel partout dans le monde','text','general','Slogan'),
('meta_title','EuroImport Export — Import-export international, commande via WhatsApp','text','seo','Titre méta'),
('meta_description','Vente en gros et détail de produits importés. Livraison dans le monde entier, commande enregistrée et paiement hors ligne via WhatsApp.','textarea','seo','Description méta'),
('public_phone','+33 1 40 20 30 40','text','contact','Numéro de téléphone public'),
('contact_email','contact@euroimport-export.fr','text','contact','Email de contact'),
('whatsapp_order','+33 6 12 34 56 78','text','contact','Numéro WhatsApp pour les commandes'),
('address','12 Rue du Commerce, 75001 Paris, France','text','contact','Adresse de l entreprise'),
('timezone','Europe/Paris','text','localisation','Fuseau horaire de l administrateur'),
('chat_hours','Lundi à vendredi, 9h00 à 18h00 (heure de Paris)','text','chat','Horaires d ouverture du chat'),
('facebook_url','https://www.facebook.com/euroimportexport','url','reseaux','Facebook'),
('instagram_url','https://www.instagram.com/euroimportexport','url','reseaux','Instagram'),
('linkedin_url','https://www.linkedin.com/company/euroimportexport','url','reseaux','LinkedIn'),
('siret','123 456 789 00012','text','legal','N° SIRET / immatriculation'),
('tva_intracommunautaire','FR 12 345 678 901','text','legal','N° de TVA intracommunautaire'),
('eori','FR12345678900012','text','legal','N° EORI'),
('iban','FR76 3000 6000 0112 3456 7890 189','text','bank','IBAN'),
('bic','BNPAFRPPXXX','text','bank','BIC'),
('bank_name','BNP Paribas','text','bank','Nom de la banque'),
('currency_default','EUR','text','currencies','Devise par défaut'),
('language_selector_enabled','1','boolean','localisation','Afficher le sélecteur de langue'),
('vat_enabled','1','boolean','tax','TVA activée'),
('shop_country','FR','text','tax','Pays du vendeur'),
('order_terms','Les droits de douane, taxes d importation et TVA locale éventuels sont à la charge de l acheteur hors Union européenne.','textarea','legal','Mention douane');

-- Administrateur par défaut (mot de passe : password — À CHANGER immédiatement après installation)
INSERT INTO `admins` (`username`,`email`,`password_hash`,`role`,`is_active`,`last_login_at`,`created_at`,`updated_at`) VALUES
('admin','admin@euroimport-export.fr','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','superadmin',1,NULL,'2026-11-01 00:00:00','2026-11-01 00:00:00');

-- Bannières page d accueil
INSERT INTO `banners` (`title`,`subtitle`,`image`,`link_url`,`sort_order`,`is_active`) VALUES
('Livraison internationale','Expédiez vos commandes partout dans le monde, zones et tarifs calculés automatiquement.','uploads/images/banners/banner-livraison.jpg','catalogue.php',1,1),
('Commandez via WhatsApp','Passez votre commande en ligne, nous vous recontactons par WhatsApp pour le paiement.','uploads/images/banners/banner-whatsapp.jpg','catalogue.php',2,1),
('Promotions de la semaine','Jusqu à -30 % sur une sélection d articles.','uploads/images/banners/banner-promo.jpg','catalogue.php',3,1);

-- Pages légales et institutionnelles
INSERT INTO `pages` (`slug`,`title`,`content`,`is_active`,`in_footer`,`sort_order`) VALUES
('mentions-legales','Mentions légales','<h1>Mentions légales</h1><p>Éditeur du site : EuroImport Export, 12 Rue du Commerce, 75001 Paris, France.</p><p>SIRET : 123 456 789 00012 — TVA intracommunautaire : FR 12 345 678 901 — EORI : FR12345678900012.</p><p>Hébergeur : hébergement mutualisé (à préciser selon le prestataire).</p><p>Contact : contact@euroimport-export.fr — +33 1 40 20 30 40</p>',1,1,1),
('cgv','Conditions générales de vente (CGV)','<h1>Conditions générales de vente</h1><p>Les présentes CGV régissent les ventes conclues sur le site EuroImport Export.</p><p><strong>Paiement :</strong> aucune transaction en ligne n est effectuée sur le site. La commande est enregistrée et le paiement se fait hors ligne (virement bancaire ou autre moyen convenu).</p><p><strong>Traduction :</strong> le site peut être traduit automatiquement via Google Website Translator. Cette traduction n a aucune valeur juridique ; la version française fait foi.</p><p><strong>Droits de douane :</strong> hors Union européenne, les droits de douane, taxes d importation et TVA locale sont à la charge de l acheteur.</p><p><strong>Délai de rétractation :</strong> 14 jours pour les consommateurs de l Union européenne (voir page Retours et rétractation).</p>',1,1,2),
('politique-confidentialite','Politique de confidentialité (RGPD)','<h1>Politique de confidentialité</h1><p>Responsable du traitement : EuroImport Export.</p><p>Les données collectées (formulaire de commande, compte client, newsletter, messages du chat) sont conservées pour la gestion des commandes, la facturation et la relation client.</p><p>Durée de conservation : commandes et factures 10 ans (obligation comptable/fiscale), conversations du chat 12 mois, abonnés newsletter jusqu à désabonnement.</p><p>Conformément au RGPD, vous disposez d un droit d accès, de rectification, d export et de suppression de vos données. Contact : contact@euroimport-export.fr</p><p>Cookies : un bandeau de consentement informe du dépôt de cookies, notamment ceux du widget de traduction Google.</p>',1,1,3),
('livraison-douane','Livraison et douane','<h1>Livraison et douane</h1><p>EuroImport Export livre dans le monde entier. Les frais de livraison sont calculés selon la zone de destination et le poids de la commande.</p><p><strong>Hors Union européenne :</strong> les droits de douane, taxes d importation et TVA locale éventuels sont à la charge de l acheteur.</p><p>Délais indicatifs : France 2-4 jours, UE 3-6 jours, Europe hors UE 5-8 jours, Amériques 6-14 jours, Afrique 7-12 jours, Asie 8-14 jours, Océanie 10-16 jours.</p>',1,1,4),
('retours-retractation','Retours et rétractation (14 jours)','<h1>Retours et rétractation</h1><p>Conformément à la directive européenne, les consommateurs de l Union européenne disposent d un délai de 14 jours pour se rétracter, sans justification.</p><p>Les produits doivent être retournés dans leur état et emballage d origine. Les frais de retour sont à la charge du client, sauf en cas de non-conformité.</p><p>Cette garantie ne s applique pas aux commandes hors Union européenne.</p>',1,1,5),
('faq','FAQ','<h1>Foire aux questions</h1><p><strong>Comment passer commande ?</strong> Ajoutez des articles au panier, validez votre commande, vous serez redirigé vers WhatsApp avec un message prérempli.</p><p><strong>Quel moyen de paiement ?</strong> Virement bancaire ou autre moyen hors ligne convenu avec nous.</p><p><strong>Puis-je suivre ma commande ?</strong> Oui, un numéro de suivi vous est communiqué après expédition.</p>',1,1,6),
('a-propos','À propos','<h1>À propos</h1><p>EuroImport Export est un importateur-exportateur basé en France, vendant ses produits partout dans le monde.</p><p>Nous publions nous-mêmes nos articles et assurons une livraison internationale avec calcul des frais de port et de la TVA selon votre pays.</p>',1,0,7),
('contact','Contact','<h1>Contact</h1><p>EuroImport Export — 12 Rue du Commerce, 75001 Paris, France.</p><p>Email : contact@euroimport-export.fr — Téléphone : +33 1 40 20 30 40.</p><p>Commandes : envoyez-nous votre panier via WhatsApp au +33 6 12 34 56 78.</p>',1,1,8);

-- =============================================================================
-- Fin du script
-- =============================================================================
