-- ----------------------------------------------------------------------------
-- Easy!Appointments Production SQL for Packages (Etapa 1)
-- Run this manually on the Railway MySQL database if migrations are not applied
-- automatically.
-- ----------------------------------------------------------------------------

-- Create packages table
CREATE TABLE IF NOT EXISTS `ea_packages` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `create_datetime` DATETIME DEFAULT NULL,
    `update_datetime` DATETIME DEFAULT NULL,
    `name` VARCHAR(256) DEFAULT NULL,
    `price` DECIMAL(10,2) DEFAULT NULL,
    `id_service_categories` INT(11) DEFAULT NULL,
    `validity_days` INT(11) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `notes` TEXT DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `id_service_categories` (`id_service_categories`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `ea_packages`
    ADD CONSTRAINT `ea_packages_service_categories`
    FOREIGN KEY (`id_service_categories`) REFERENCES `ea_service_categories` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- Create package_items table
CREATE TABLE IF NOT EXISTS `ea_package_items` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `id_packages` INT(11) DEFAULT NULL,
    `id_services` INT(11) DEFAULT NULL,
    `quantity` INT(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `id_packages` (`id_packages`),
    KEY `id_services` (`id_services`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `ea_package_items`
    ADD CONSTRAINT `ea_package_items_packages`
    FOREIGN KEY (`id_packages`) REFERENCES `ea_packages` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `ea_package_items`
    ADD CONSTRAINT `ea_package_items_services`
    FOREIGN KEY (`id_services`) REFERENCES `ea_services` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

-- Add packages permission column to roles table (compatible with MySQL 8 and MariaDB)
SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'ea_roles'
      AND column_name = 'packages'
);

SET @add_column := IF(@column_exists = 0,
    'ALTER TABLE `ea_roles` ADD COLUMN `packages` INT(11) DEFAULT NULL',
    'SELECT 1'
);

PREPARE stmt FROM @add_column;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `ea_roles` SET `packages` = 15 WHERE `slug` = 'admin';
UPDATE `ea_roles` SET `packages` = 0 WHERE `slug` != 'admin';
