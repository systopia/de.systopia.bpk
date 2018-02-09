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

/**
 * BPK Lookup
 */
function civicrm_api3_bpk_lookup($params) {
  // process time based limits
  //  format is 'limit_x-y' (x and y should be times)
  foreach ($params as $key => $value) {
    if (substr($key, 0, 6) == 'limit_') {
      $hours = explode('-', substr($key, 6));
      if (count($hours) == 2 && is_numeric($hours[0]) && is_numeric($hours[1])) {
        // this seems to be a correct limit restriction
        $current_hour = date('H');
        if ($current_hour >= $hours[0] && $current_hour < $hours[1]) {
          // ...and it applies
          $params['limit'] = $value;
        }
      }
    }
  }

  $result = CRM_Bpk_Lookup::doSoapLookup($params);
  return civicrm_api3_create_success("{$result['success']} contacts resolved successfully, {$result['failed']} failures. Contacts (IDs) processed: {$result['contact_ids']}.");
}


/**
 * BPK.lookup parameters
 */
function _civicrm_api3_bpk_lookup_spec(&$params) {
  $params['limit'] = array(
    'name'         => 'limit',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Query Limit',
    'description'  => 'Will restrict the maximum number of contacts to be looked up. Will default to the extension\'s setting',
    );
  $params['contact_id'] = array(
    'name'         => 'contact_id',
    'api.required' => 0,
    'type'         => CRM_Utils_Type::T_INT,
    'title'        => 'Contact ID',
    'description'  => 'Looks up bPK for the given contact (for testing)',
    );
}
