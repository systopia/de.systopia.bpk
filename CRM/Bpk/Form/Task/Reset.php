<?php
/*-------------------------------------------------------+
| SYSTOPIA bPK Extension                                 |
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
require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Bpk_Form_Task_Reset extends CRM_Contact_Form_Task {

  /**
   * Compile task form
   */
  function buildQuickForm() {
    CRM_Utils_System::setTitle(E::ts('Reset BPKs for %1 contacts',
      array(1 => count($this->_contactIds))));

    $config = CRM_Bpk_Config::singleton();

    CRM_Core_Form::addDefaultButtons(E::ts("Reset BPKs"));
  }


  function postProcess() {
    // reset all individually
    foreach ($this->_contactIds as $contact_id) {
      CRM_Bpk_Lookup::resetBPK($contact_id);
    }

    // add status message
    CRM_Core_Session::setStatus(E::ts("BPKs for %1 contacts reset", array(1 => count($this->_contactIds))), E::ts('Success'), 'info');

    parent::postProcess();
  }
}
