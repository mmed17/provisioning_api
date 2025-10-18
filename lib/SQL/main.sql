-- This schema has been revised based on the new requirements:
-- 1. ENUM constraints are added to status fields for data integrity.
-- 2. A new, more detailed history table explicitly tracks all subscription changes.
-- 3. The main subscriptions table is correctly named oc_subscriptions.

-- Drop tables in reverse order to avoid foreign key constraint issues
DROP TABLE IF EXISTS `oc_subscriptions_history`;
DROP TABLE IF EXISTS `oc_subscriptions`;
DROP TABLE IF EXISTS `oc_plans`;
DROP TABLE IF EXISTS `oc_organizations`;


-- TABLE: oc_organizations
-- Stores basic information about each organization.
CREATE TABLE `oc_organizations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `nextcloud_group_id` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `orgs_group_id` (`nextcloud_group_id`),
  CONSTRAINT `fk_org_group_id`
    FOREIGN KEY (`nextcloud_group_id`)
    REFERENCES `oc_groups` (`gid`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- TABLE: oc_plans
-- Defines the available subscription plans and their default limits.
CREATE TABLE `oc_plans` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `max_projects` INT(11) NOT NULL,
    `max_members` INT(11) NOT NULL,
    `shared_storage_per_project` BIGINT NOT NULL COMMENT 'Shared storage for each project in bytes.',
    `private_storage_per_user` BIGINT NOT NULL COMMENT 'Private storage for each user in the organization in bytes.',
    `price` FLOAT DEFAULT NULL,
    `currency` VARCHAR(3) DEFAULT 'EUR',
    `is_public` BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (`id`),
    UNIQUE KEY `plans_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- TABLE: oc_subscriptions
-- Tracks the current and past plans for each organization.
-- UPDATED: Status is now an ENUM that includes 'paused' for more robust state management.
CREATE TABLE `oc_subscriptions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `organization_id` INT(11) NOT NULL,
    `plan_id` INT(11) NOT NULL,
    `status` ENUM('active', 'paused', 'expired', 'cancelled') NOT NULL DEFAULT 'active',
    `started_at` DATETIME NOT NULL,
    `ended_at` DATETIME DEFAULT NULL COMMENT 'NULL indicates the subscription is currently active.',
    PRIMARY KEY (`id`),
    INDEX `subs_org_id_idx` (`organization_id`),
    CONSTRAINT `fk_subs_org_id` 
        FOREIGN KEY (`organization_id`) 
        REFERENCES `oc_organizations` (`id`) 
        ON DELETE CASCADE,
    CONSTRAINT `fk_subs_plan_id` 
        FOREIGN KEY (`plan_id`) 
        REFERENCES `oc_plans` (`id`) 
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- TABLE: oc_subscriptions_history
-- This table explicitly logs a complete snapshot of every change made to a subscription.
-- It captures the state before and after the modification for a full audit trail.
CREATE TABLE `oc_subscriptions_history` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `subscription_id` INT(11) NOT NULL,
    `changed_by_user_id` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    `change_timestamp` DATETIME NOT NULL,
    
    -- State before the change
    `previous_plan_id` INT(11) DEFAULT NULL,
    `previous_status` ENUM('active', 'paused', 'expired', 'cancelled') DEFAULT NULL,
    `previous_started_at` DATETIME DEFAULT NULL,
    `previous_ended_at` DATETIME DEFAULT NULL,
    
    -- State after the change
    `new_plan_id` INT(11) NOT NULL,
    `new_status` ENUM('active', 'paused', 'expired', 'cancelled') NOT NULL,
    `new_started_at` DATETIME NOT NULL,
    `new_ended_at` DATETIME DEFAULT NULL,
    
    `notes` TEXT DEFAULT NULL COMMENT 'For any manual notes about the change.',
    PRIMARY KEY (`id`),
    INDEX `history_sub_id_idx` (`subscription_id`),
    CONSTRAINT `fk_history_sub_id`
        FOREIGN KEY (`subscription_id`)
        REFERENCES `oc_subscriptions` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_history_user_id`
        FOREIGN KEY (`changed_by_user_id`)
        REFERENCES `oc_users` (`uid`)
        ON DELETE RESTRICT,
    CONSTRAINT `fk_history_prev_plan_id`
        FOREIGN KEY (`previous_plan_id`)
        REFERENCES `oc_plans` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_history_new_plan_id`
        FOREIGN KEY (`new_plan_id`)
        REFERENCES `oc_plans` (`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- 1. Modify the oc_subscriptions table
ALTER TABLE `oc_subscriptions` 
    MODIFY `status` VARCHAR(50) NOT NULL DEFAULT 'active',
    ADD CONSTRAINT `chk_subscriptions_status` 
        CHECK (`status` IN ('active', 'paused', 'expired', 'cancelled'));

-- 2. Modify the oc_subscriptions_history table
ALTER TABLE `oc_subscriptions_history` 
    MODIFY `previous_status` VARCHAR(50) DEFAULT NULL,
    MODIFY `new_status` VARCHAR(50) NOT NULL,
    ADD CONSTRAINT `chk_history_previous_status` 
        CHECK (`previous_status` IS NULL OR `previous_status` IN ('active', 'paused', 'expired', 'cancelled')),
    ADD CONSTRAINT `chk_history_new_status` 
        CHECK (`new_status` IN ('active', 'paused', 'expired', 'cancelled'));


-- DEFAULT PLANS:
-- Values remain the same as they align with the storage logic.
INSERT INTO `oc_plans` 
(`name`, `max_projects`, `max_members`, `shared_storage_per_project`, `private_storage_per_user`, `price`, `currency`, `is_public`)
VALUES 
-- Free Plan: 50 MB shared per project, 1 GB private per user
('Free', 1, 1, 52428800, 1073741824, 0, 'EUR', TRUE),
-- Pro Plan: 100 MB shared per project, 5 GB private per user
('Pro', 2, 5, 104857600, 5368709120, 10, 'EUR', TRUE),
-- Gold Plan: 1 GB shared per project, 20 GB private per user
('Gold', 5, 20, 1073741824, 21474836480, 25, 'EUR', TRUE);