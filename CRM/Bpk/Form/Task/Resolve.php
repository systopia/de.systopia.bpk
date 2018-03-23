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
class CRM_Bpk_Form_Task_Resolve extends CRM_Contact_Form_Task {

  /**
   * Compile task form
   */
  function buildQuickForm() {
    CRM_Utils_System::setTitle(E::ts('Look up BPKs for %1 contacts',
      array(1 => count($this->_contactIds))));

    $config = CRM_Bpk_Config::singleton();

    // YEAR selector
    $this->addElement('checkbox',
                      'postal_code',
                      E::ts('Submit Postal Code'));


    CRM_Core_Form::addDefaultButtons(E::ts("Query BPKs"));
  }


  function postProcess() {
    $values = $this->exportValues();

    // generate parameters
    $total_count  = count($this->_contactIds);
    $failed_count = 0;
    $lookup_params = array(
      'postal_code' => !empty($values['postal_code'])
    );

    // now query each contact individually
    foreach ($this->_contactIds as $contact_id) {
      try {
        $lookup_params['contact_id'] = $contact_id;
        CRM_Bpk_Lookup::doSoapLookup($lookup_params);
      } catch (Exception $e) {
        $failed_count += 1;
      }
    }

    // add status message
    CRM_Core_Session::setStatus(E::ts("%1 of %2 contacts queried.", array(
      1 => ($total_count - $failed_count),
      2 => $total_count)),
      E::ts('Success'), 'info');

    parent::postProcess();
  }
}
