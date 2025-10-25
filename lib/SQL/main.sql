-- Drop tables in reverse order to avoid foreign key constraint issues
DROP TABLE IF EXISTS `oc_subscriptions_history`;
DROP TABLE IF EXISTS `oc_subscriptions`;
DROP TABLE IF EXISTS `oc_plans`;
DROP TABLE IF EXISTS `oc_organizations`;


-- TABLE: oc_organizations
-- Stores basic information about each organization.
CREATE TABLE IF NOT EXISTS `oc_organizations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `nextcloud_group_id` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `orgs_group_id` (`nextcloud_group_id`),
  CONSTRAINT
    FOREIGN KEY (`nextcloud_group_id`)
    REFERENCES `oc_groups` (`gid`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- TABLE: oc_plans
-- Defines the available subscription plans and their default limits.
CREATE TABLE IF NOT EXISTS `oc_plans` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `max_projects` INT(11) NOT NULL,
    `max_members` INT(11) NOT NULL,
    `shared_storage_per_project` BIGINT NOT NULL,
    `private_storage_per_user` BIGINT NOT NULL,
    `price` FLOAT DEFAULT NULL,
    `currency` VARCHAR(3) DEFAULT 'EUR',
    `is_public` BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (`id`),
    UNIQUE KEY `plans_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- TABLE: oc_subscriptions
-- Tracks the current and past plans for each organization.
-- UPDATED: Status is now an ENUM that includes 'paused' for more robust state management.
CREATE TABLE IF NOT EXISTS `oc_subscriptions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `organization_id` INT(11) NOT NULL,
    `plan_id` INT(11) NOT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'active',
    `started_at` DATETIME NOT NULL,
    `ended_at` DATETIME DEFAULT NULL COMMENT 'NULL indicates the subscription is currently active.',
    `paused_at` DATETIME DEFAULT NULL,
    `cancelled_at` DATETIME DEFAULT NULL,
    
    PRIMARY KEY (`id`),
    INDEX `subs_org_id_idx` (`organization_id`),
    
    CONSTRAINT `chk_subscriptions_status`
        CHECK (`status` IN ('active', 'paused', 'expired', 'cancelled')),
    CONSTRAINT
        FOREIGN KEY (`organization_id`) 
        REFERENCES `oc_organizations` (`id`) 
        ON DELETE CASCADE,
    CONSTRAINT
        FOREIGN KEY (`plan_id`) 
        REFERENCES `oc_plans` (`id`) 
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- TABLE: oc_subscriptions_history
-- This table explicitly logs a complete snapshot of every change made to a subscription.
-- It captures the state before and after the modification for a full audit trail.
-- TABLE: oc_subscriptions_history
CREATE TABLE IF NOT EXISTS `oc_subscriptions_history` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `subscription_id` INT(11) NOT NULL,
    `changed_by_user_id` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    `change_timestamp` DATETIME NOT NULL,
    
    -- State before the change
    `previous_plan_id` INT(11) DEFAULT NULL,
    `previous_status` VARCHAR(50) DEFAULT NULL,
    `previous_started_at` DATETIME DEFAULT NULL,
    `previous_ended_at` DATETIME DEFAULT NULL,
    `previous_paused_at` DATETIME DEFAULT NULL,
    `previous_cancelled_at` DATETIME DEFAULT NULL,
    
    -- State after the change
    `new_plan_id` INT(11) NOT NULL,
    `new_status` VARCHAR(50) NOT NULL,
    `new_started_at` DATETIME NOT NULL,
    `new_ended_at` DATETIME DEFAULT NULL,
    `new_paused_at` DATETIME DEFAULT NULL,
    `new_cancelled_at` DATETIME DEFAULT NULL,
    
    `notes` TEXT DEFAULT NULL COMMENT 'For any manual notes about the change.',
    
    PRIMARY KEY (`id`),
    INDEX `history_sub_id_idx` (`subscription_id`),
    
    CONSTRAINT `chk_history_previous_status` 
        CHECK (`previous_status` IS NULL OR `previous_status` IN ('active', 'paused', 'expired', 'cancelled')),
    CONSTRAINT `chk_history_new_status` 
        CHECK (`new_status` IN ('active', 'paused', 'expired', 'cancelled')),
    CONSTRAINT 
        FOREIGN KEY (`subscription_id`)
        REFERENCES `oc_subscriptions` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT 
        FOREIGN KEY (`changed_by_user_id`)
        REFERENCES `oc_users` (`uid`)
        ON DELETE RESTRICT,
    CONSTRAINT 
        FOREIGN KEY (`previous_plan_id`)
        REFERENCES `oc_plans` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT
        FOREIGN KEY (`new_plan_id`)
        REFERENCES `oc_plans` (`id`)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `oc_custom_projects` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `number` VARCHAR(255) NOT NULL,
  `type` INT(11) NOT NULL,
  `address` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `owner_id` VARCHAR(64) NOT NULL,
  `circle_id` VARCHAR(31) NOT NULL,
  `board_id` INT(11) NOT NULL,
  `folder_id` BIGINT NOT NULL,
  `folder_path` VARCHAR(4000) NOT NULL,
  `status` INT(11) NOT NULL DEFAULT 1,
  `organization_id` INT(11) DEFAULT NULL, -- Temporary it should become not null
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  
  PRIMARY KEY (`id`),
  INDEX `projectNameIndex` (`name`),
  INDEX `projectOwnerIdIndex` (`owner_id`),
  UNIQUE INDEX `projectCircleIdUnique` (`circle_id`),
  CONSTRAINT `fk_projects_owner`
    FOREIGN KEY (`owner_id`)
    REFERENCES `oc_users` (`uid`)
    ON DELETE RESTRICT,
  CONSTRAINT `fk_projects_circle`
    FOREIGN KEY (`circle_id`)
    REFERENCES `oc_circles_circle` (`unique_id`)
    ON DELETE RESTRICT,
  CONSTRAINT `fk_projects_board`
    FOREIGN KEY (`board_id`)
    REFERENCES `oc_deck_boards` (`id`)
    ON DELETE RESTRICT,
  CONSTRAINT `fk_projects_folder`
    FOREIGN KEY (`folder_id`)
    REFERENCES `oc_filecache` (`fileid`)
    ON DELETE RESTRICT,
  CONSTRAINT `fk_projects_organization`
    FOREIGN KEY (`organization_id`)
    REFERENCES `oc_organizations` (`id`)
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

ALTER TABLE `oc_users`
    ADD COLUMN `organization_id` INT(11) NULL DEFAULT NULL AFTER `uid_lower`;

ALTER TABLE `oc_users`
    ADD CONSTRAINT `fk_user_organization`
    FOREIGN KEY (`organization_id`)
    REFERENCES `oc_organizations` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE;

ALTER TABLE `oc_users` 
    ADD INDEX `user_org_id_idx` (`organization_id`);


CREATE TABLE `oc_proj_private_folders` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `project_id` INT(11) NOT NULL,
    `user_id` VARCHAR(64) NOT NULL,
    `folder_id` INT(11) NOT NULL,
    `folder_path` VARCHAR(4000) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `proj_user_unique` (`project_id`, `user_id`) 
);

--###############################################################
--###                                                         ###--
--### THE FOLLOWING INSTRUCTIONS SHOULD BE EXECUTED WITH CARE ###--
--###                                                         ###--
--###############################################################--

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

-- 3. Modify the oc_subscriptions to register cancel and pause datetime
ALTER TABLE `oc_subscriptions`
    ADD COLUMN `paused_at` DATETIME DEFAULT NULL COMMENT 'Timestamp when the subscription was paused.' AFTER `ended_at`,
    ADD COLUMN `cancelled_at` DATETIME DEFAULT NULL COMMENT 'Timestamp when the subscription was cancelled.' AFTER `paused_at`;

ALTER TABLE `oc_subscriptions_history`
    ADD COLUMN `previous_paused_at` DATETIME DEFAULT NULL COMMENT 'The paused_at timestamp before the change.' AFTER `previous_ended_at`,
    ADD COLUMN `previous_cancelled_at` DATETIME DEFAULT NULL COMMENT 'The cancelled_at timestamp before the change.' AFTER `previous_paused_at`,
    ADD COLUMN `new_paused_at` DATETIME DEFAULT NULL COMMENT 'The paused_at timestamp after the change.' AFTER `new_ended_at`,
    ADD COLUMN `new_cancelled_at` DATETIME DEFAULT NULL COMMENT 'The cancelled_at timestamp after the change.' AFTER `new_paused_at`;

-- 4. fixing not being able to delete orphan entreprise plans because if subscriptions history
-- First, drop the existing restrictive foreign key constraints.
ALTER TABLE `oc_subscriptions_history` DROP FOREIGN KEY `fk_history_prev_plan_id`;
ALTER TABLE `oc_subscriptions_history` DROP FOREIGN KEY `fk_history_new_plan_id`;

-- allow new_plan_id to be null
ALTER TABLE `oc_subscriptions_history` MODIFY COLUMN `new_plan_id` INT(11) DEFAULT NULL;


-- Removing foreign key constraint name conflict with circles
ALTER TABLE `oc_subscriptions` DROP FOREIGN KEY `fk_subs_plan_id`;
ALTER TABLE `oc_subscriptions_history` DROP FOREIGN KEY `fk_history_new_plan_id`;
ALTER TABLE `oc_subscriptions_history` DROP FOREIGN KEY `fk_history_prev_plan_id`;
ALTER TABLE `oc_subscriptions_history` DROP FOREIGN KEY `fk_history_user_id`;

ALTER TABLE `oc_subscriptions`
ADD CONSTRAINT 
    FOREIGN KEY (`plan_id`)
    REFERENCES `oc_plans` (`id`)
    ON DELETE RESTRICT;

ALTER TABLE `oc_subscriptions_history`
ADD CONSTRAINT 
    FOREIGN KEY (`new_plan_id`)
    REFERENCES `oc_plans` (`id`)
    ON DELETE RESTRICT;

ALTER TABLE `oc_subscriptions_history`
ADD CONSTRAINT
    FOREIGN KEY (`previous_plan_id`)
    REFERENCES `oc_plans` (`id`)
    ON DELETE SET NULL;

ALTER TABLE `oc_subscriptions_history`
ADD CONSTRAINT 
    FOREIGN KEY (`changed_by_user_id`)
    REFERENCES `oc_users` (`uid`)
    ON DELETE RESTRICT;

ALTER TABLE `oc_subscriptions_history`
ADD CONSTRAINT `fk_history_prev_plan_id`
    FOREIGN KEY (`previous_plan_id`)
    REFERENCES `oc_plans` (`id`)
    ON DELETE SET NULL;

ALTER TABLE `oc_subscriptions_history`
ADD CONSTRAINT `fk_history_new_plan_id`
    FOREIGN KEY (`new_plan_id`)
    REFERENCES `oc_plans` (`id`)
    ON DELETE SET NULL;

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