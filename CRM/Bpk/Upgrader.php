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
    $this->executeSqlFile('sql/bmisa_submission_create.sql');
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
    $this->executeSqlFile('sql/bmisa_submission_create.sql');
    return TRUE;
  }
}
