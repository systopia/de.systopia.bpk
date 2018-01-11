-- +--------------------------------------------------------+
-- | SYSTOPIA bPK Extensio                                  |
-- | Copyright (C) 2018 SYSTOPIA                            |
-- | Author: B. Endres (endres@systopia.de)                 |
-- |         P. Batroff (batroff@systopia.de)               |
-- +--------------------------------------------------------+
-- | This program is released as free software under the    |
-- | Affero GPL license. You can redistribute it and/or     |
-- | modify it under the terms of this license which you    |
-- | can read by viewing the included agpl.txt or online    |
-- | at www.gnu.org/licenses/agpl.html. Removal of this     |
-- | copyright header is strictly prohibited without        |
-- | written permission from the original author(s).        |
-- +--------------------------------------------------------+

-- These structures will capture the tax submissions to the
-- BMI on a per-submission and per-contact/year level

CREATE TABLE IF NOT EXISTS `civicrm_bmisa_submission` (
     `id`         int unsigned  NOT NULL AUTO_INCREMENT,
     `year`  smallint unsigned           COMMENT 'submission year',
     `date`           datetime  NOT NULL COMMENT 'submission generation/upload timestamp',
     `reference`  varchar(255)           COMMENT 'public submission reference',
     `amount`    decimal(20,2)  NOT NULL COMMENT 'total submitted amount (verification only)',
     `created_by` int unsigned           COMMENT 'contact who created this submission',
     PRIMARY KEY (`id`),
     UNIQUE INDEX `reference` (`reference`),
     INDEX `year` (`year`),
     INDEX `date` (`date`),
     CONSTRAINT FK_civicrm_bmisa_submission_created_by FOREIGN KEY (`created_by`) REFERENCES `civicrm_contact`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;


CREATE TABLE IF NOT EXISTS `civicrm_bmisa_record` (
     `id`            int unsigned  NOT NULL AUTO_INCREMENT,
     `submission_id` int unsigned  NOT NULL COMMENT 'the submission this record belongs to',
     `type`               tinyint  NOT NULL COMMENT 'record type: 1=initial, 2=correction, 3=cancellation',
     `contact_id`    int unsigned           COMMENT 'contact this submission is for',
     `year`     smallint unsigned  NOT NULL COMMENT 'submission year',
     `amount`       decimal(20,2)  NOT NULL COMMENT 'submitted amount for this contact',
     PRIMARY KEY (`id`),
     UNIQUE INDEX `contact_id` (`contact_id`),
     UNIQUE INDEX `year` (`year`),
     UNIQUE INDEX `submission_id` (`submission_id`),
     CONSTRAINT FK_civicrm_bmisa_record_submission FOREIGN KEY (`submission_id`) REFERENCES `civicrm_bmisa_submission`(`id`) ON DELETE CASCADE,
     CONSTRAINT FK_civicrm_bmisa_record_contact    FOREIGN KEY (`contact_id`)    REFERENCES `civicrm_contact`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;
