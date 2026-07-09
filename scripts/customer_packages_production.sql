-- ----------------------------------------------------------------------------
-- Easy!Appointments Production SQL for Customer Packages (Etapa 2A)
-- Run this manually on the Railway MySQL 8 database.
-- Each statement ends with ';' and can be executed block by block.
-- ----------------------------------------------------------------------------

-- Create customer_packages table
CREATE TABLE IF NOT EXISTS `ea_customer_packages` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `create_datetime` DATETIME DEFAULT NULL,
    `update_datetime` DATETIME DEFAULT NULL,
    `id_users_customer` INT(11) DEFAULT NULL,
    `id_packages` INT(11) DEFAULT NULL,
    `purchase_date` DATETIME DEFAULT NULL,
    `expiry_date` DATETIME DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `notes` TEXT DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `id_users_customer` (`id_users_customer`),
    KEY `id_packages` (`id_packages`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `ea_customer_packages`
    ADD CONSTRAINT `ea_customer_packages_users`
    FOREIGN KEY (`id_users_customer`) REFERENCES `ea_users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `ea_customer_packages`
    ADD CONSTRAINT `ea_customer_packages_packages`
    FOREIGN KEY (`id_packages`) REFERENCES `ea_packages` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE;

-- Create customer_package_items table
CREATE TABLE IF NOT EXISTS `ea_customer_package_items` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `id_customer_packages` INT(11) DEFAULT NULL,
    `id_services` INT(11) DEFAULT NULL,
    `quantity_total` INT(11) DEFAULT NULL,
    `quantity_remaining` INT(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `id_customer_packages` (`id_customer_packages`),
    KEY `id_services` (`id_services`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `ea_customer_package_items`
    ADD CONSTRAINT `ea_customer_package_items_customer_packages`
    FOREIGN KEY (`id_customer_packages`) REFERENCES `ea_customer_packages` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `ea_customer_package_items`
    ADD CONSTRAINT `ea_customer_package_items_services`
    FOREIGN KEY (`id_services`) REFERENCES `ea_services` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

-- Create customer_package_item_adjustments table
CREATE TABLE IF NOT EXISTS `ea_customer_package_item_adjustments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `create_datetime` DATETIME DEFAULT NULL,
    `id_customer_package_items` INT(11) DEFAULT NULL,
    `old_quantity_remaining` INT(11) DEFAULT NULL,
    `new_quantity_remaining` INT(11) DEFAULT NULL,
    `id_users_modified_by` INT(11) DEFAULT NULL,
    `reason` TEXT DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `id_customer_package_items` (`id_customer_package_items`),
    KEY `id_users_modified_by` (`id_users_modified_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `ea_customer_package_item_adjustments`
    ADD CONSTRAINT `ea_customer_package_item_adjustments_items`
    FOREIGN KEY (`id_customer_package_items`) REFERENCES `ea_customer_package_items` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `ea_customer_package_item_adjustments`
    ADD CONSTRAINT `ea_customer_package_item_adjustments_users`
    FOREIGN KEY (`id_users_modified_by`) REFERENCES `ea_users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- Add customer_packages permission column to roles table (plain ALTER for MySQL 8)
ALTER TABLE `ea_roles` ADD COLUMN `customer_packages` INT(11) DEFAULT NULL;

UPDATE `ea_roles` SET `customer_packages` = 15 WHERE `slug` = 'admin';

UPDATE `ea_roles` SET `customer_packages` = 7 WHERE `slug` = 'secretary';

UPDATE `ea_roles` SET `customer_packages` = 0 WHERE `slug` = 'provider';

UPDATE `ea_roles` SET `customer_packages` = 0 WHERE `slug` = 'customer';
