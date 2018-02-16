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

class CRM_Bpk_Page_UpdateBPK extends CRM_Core_Page {

  public function run() {
    $contact_id = CRM_Utils_Request::retrieve('cid', 'Integer');

    // run update
    $params = array('contact_id' => $contact_id);
    try {
      $result = CRM_Bpk_Lookup::doSoapLookup($params);

      // check result
      if (empty($result['success'])) {
        CRM_Core_Session::setStatus(E::ts("BPK update failed."), E::ts('Failed'), 'warn');
      } else {
        CRM_Core_Session::setStatus(E::ts("BPK updated."), E::ts('Success'), 'info');
      }
    } catch (Exception $e) {
      CRM_Core_Session::setStatus(E::ts("BPK update failed: %1", array(1 => $e->getMessage())), E::ts('Failed'), 'warn');
    }

    // in any case: redirect to contact summary
    $redirect_url = CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$contact_id}");
    CRM_Utils_System::redirect($redirect_url);
  }
}
