<?php
/*-------------------------------------------------------+
| SYSTOPIA bPK Extensio                                  |
| Copyright (C) 2018 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
|         P. Batroff (batroff@systopia.de)               |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

use CRM_Bpk_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Bpk_Upgrader extends CRM_Bpk_Upgrader_Base {

  /**
   * Install data stuctures
   */
  public function install() {
    $this->executeSqlFile('sql/bmfsa_submission_create.sql');
  }

  /**
   * module is enabled.
   */
  public function enable() {
  }

  /**
   * module is disabled.
   */
  public function disable() {
  }

  /**
   * Make sure to add data stuctures
   */
  public function upgrade_0021() {
    $this->ctx->log->info('Creating BMFSA submission data structures.');
    $this->executeSqlFile('sql/bmfsa_submission_create.sql');
    return TRUE;
  }

  /**
   * Make sure to add data stuctures
   */
  public function upgrade_0030() {
    $this->ctx->log->info('Creating BMFSA submission data structures.');
    $this->executeSqlFile('sql/bmfsa_submission_create.sql');
    return TRUE;
  }

  /**
   * Add reference column to bmfsa_record
   */
  public function upgrade_0080() {
    // add column
    $this->ctx->log->info('Updating BMFSA submission data structures.');
    $column_exists = CRM_Core_DAO::executeQuery("SHOW COLUMNS FROM `civicrm_bmfsa_record` LIKE 'reference';");
    if (!$column_exists->fetch()) {
      // doesn't exist -> add column + index
      CRM_Core_DAO::executeQuery("ALTER TABLE `civicrm_bmfsa_record` ADD `reference` VARCHAR(23);");
      CRM_Core_DAO::executeQuery("ALTER TABLE `civicrm_bmfsa_record` ADD INDEX `reference` (`reference`);");
    }

    // fill (new?) column:
    CRM_Core_DAO::executeQuery("
        UPDATE `civicrm_bmfsa_record`
        SET reference = CONCAT(year, '-', contact_id)
        WHERE reference IS NULL;");

    return TRUE;
  }
}
