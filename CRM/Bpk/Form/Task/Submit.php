<?php
/*-------------------------------------------------------+
| SYSTOPIA bPK Extension                                 |
| Copyright (C) 2017 SYSTOPIA                            |
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
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Bpk_Form_Task_Submit extends CRM_Contact_Form_Task {

  /**
   * Compile task form
   */
  function buildQuickForm() {
    CRM_Utils_System::setTitle(E::ts('Generate XML Submission %1 Contacts',
      array(1 => count($this->_contactIds))));

    // YEAR selector
    $this->addElement('select',
                      'year',
                      E::ts('Year'),
                      array(2017 => 2017, 2018 => 2018),
                      array());

    // TODO: update?
    $this->addElement('select',
                      'type',
                      E::ts('Type'),
                      array('E' => E::ts('First submission')),
                      // array('E' => E::ts('First submission'), 'A' => E::ts('Adjustment'), 'S' => E::ts('Withdrawal')),
                      array());

    CRM_Core_Form::addDefaultButtons(E::ts("Generate XML"));
  }


  function postProcess() {
    $values = $this->exportValues();
    CRM_Bpk_Submission::generateForContactIDs($values['year'], $this->_contactIds);
  }
}
