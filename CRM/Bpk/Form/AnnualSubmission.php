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

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Bpk_Form_AnnualSubmission extends CRM_Core_Form {

  public function buildQuickForm() {
    CRM_Utils_System::setTitle(E::ts('Generate Annual Tax Submission'));

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

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Generate XML'),
        'isDefault' => TRUE,
      ),
    ));

    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();
    CRM_Bpk_Submission::generateYear($values['year'], $values['type']);
  }
}
