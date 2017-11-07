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
class CRM_Bpk_Form_Settings extends CRM_Core_Form {

  public function buildQuickForm() {

    // add form elements
    $this->add(
      'text',
      'limit',
      E::ts('Default Limit'),
      TRUE
    );

    $this->add(
      'text',
      'key',
      E::ts('Access Key'),
      FALSE
    );


    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Save'),
        'isDefault' => TRUE,
      ),
    ));

    parent::buildQuickForm();
  }

  /**
   * set the default (=current) values in the form
   */
  public function setDefaultValues() {
    $config = CRM_Bpk_Config::singleton();
    return $config->getSettings();
  }

  public function postProcess() {
    $config = CRM_Bpk_Config::singleton();
    $values = $this->exportValues();

    $settings = $config->getSettings();
    $settings_in_form = array('limit', 'key');
    foreach ($settings_in_form as $name) {
      if (isset($values[$name])) {
        $settings[$name] = $values[$name];
      }
    }
    $config->setSettings($settings);
    parent::postProcess();
  }

}
