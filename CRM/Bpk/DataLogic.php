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


define('BPK_STATUS_UNKNOWN',  1);
define('BPK_STATUS_MANUAL',   2);
define('BPK_STATUS_RESOLVED', 3);
define('BPK_STATUS_NOMATCH',  4);
define('BPK_STATUS_ERROR',    5);
define('BPK_STATUS_AMBIG',    6);

/**
 * This class deals with the automatic
 * adjustement of date, e.g. delete the vBPK
 * if somebody sets it to 'unknown'
 * of reset it if somebody changes
 * relevant contact attributes
 */
class CRM_Bpk_DataLogic {

  // also declare the statuses as constants
  const STATUS_UNKNOWN  = BPK_STATUS_UNKNOWN;
  const STATUS_MANUAL   = BPK_STATUS_MANUAL;
  const STATUS_RESOLVED = BPK_STATUS_RESOLVED;
  const STATUS_NOMATCH  = BPK_STATUS_NOMATCH;
  const STATUS_ERROR    = BPK_STATUS_ERROR;
  const STATUS_AMBIG    = BPK_STATUS_AMBIG;

  protected static $bpk_group_id    = NULL;
  protected static $recursion       = FALSE;
  protected static $reset_recursion = FALSE;
  protected static $scheduled_reset = NULL;
  /**
   * detect relevant changes and
   *  reset BPK
   */
  public static function processContactPreHook($op, $id, &$params) {
    if (empty($id) || self::$scheduled_reset) {
      return; // skip
    }

    if ($op == 'edit' || $op == 'create') {
      $potential_changes   = array();
      $relevant_attributes = array('first_name', 'last_name', 'birth_date');
      foreach ($relevant_attributes as $attribute) {
        if (isset($params[$attribute])) {
          $potential_changes[$attribute] = $params[$attribute];
        }
      }

      if (!empty($potential_changes)) {
        // some relevant fields have been submitted -> check if those are real changes
        $actual_changes = array();
        $contact_current = civicrm_api3('Contact', 'getsingle', array(
          'id'     => $id,
          'return' => implode(',', $relevant_attributes)));

        foreach ($potential_changes as $key => $new_value) {
          $old_value = $contact_current[$key];
          if ($key == 'birth_date') { // format dates
            $old_value = date('Y-m-d', strtotime($old_value));
            $new_value = date('Y-m-d', strtotime($new_value));
          }
          if ($old_value != $new_value) {
            $actual_changes[$key] = $new_value;
          }
        }

        if (!empty($actual_changes)) {
          // there is some actual change to the relevant attributes happening here
          // ==> reset BPKs
          $reset['bpk.bpk_status']     = BPK_STATUS_UNKNOWN;
          $reset['id']                 = $id;
          $reset['bpk.bpk_extern']     = '';
          $reset['bpk.vbpk']           = '';
          $reset['bpk.bpk_error_code'] = '';
          $reset['bpk.bpk_error_note'] = '';
          CRM_Bpk_CustomData::resolveCustomFields($reset);
          self::$scheduled_reset = $reset;
        }
      }
    }
  }

  /**
   * send any pending BPK request
   */
  public static function sendPendingBPKRequests() {
    if (self::$scheduled_reset) {
      if (self::$reset_recursion) return; // avoid catching own reset
      self::$reset_recursion = TRUE;
      civicrm_api3('Contact', 'create', self::$scheduled_reset);
      self::$scheduled_reset = NULL;
      self::$reset_recursion = FALSE;
    }
  }

  /**
   * process the CUSTOM data hook
   */
  public static function processCustomHook($op, $groupID, $entityID, &$params) {
    if (self::$recursion) return; // protect agains recursion

    // check if the groupID is ours
    if ($groupID == self::getBpkGroupID()) {
      $status = self::getCustomValue('status', $params);
      $update = array();
      switch ($status) {
        case BPK_STATUS_UNKNOWN:
          self::addCustomUpdate('bpk_extern', '', $params, $update);
          self::addCustomUpdate('vbpk',       '', $params, $update);
          self::addCustomUpdate('error_code', '', $params, $update);
          self::addCustomUpdate('error_note', '', $params, $update);
          break;

        case BPK_STATUS_MANUAL:
          self::addCustomUpdate('error_code', '', $params, $update);
          self::addCustomUpdate('error_note', '', $params, $update);
          break;

        case BPK_STATUS_RESOLVED:
          self::addCustomUpdate('error_code', '', $params, $update);
          self::addCustomUpdate('error_note', '', $params, $update);
          break;

        case BPK_STATUS_NOMATCH:
          self::addCustomUpdate('bpk_extern', '', $params, $update);
          self::addCustomUpdate('vbpk',       '', $params, $update);
          self::addCustomUpdate('error_code', '', $params, $update);
          self::addCustomUpdate('error_note', '', $params, $update);
          break;

        case BPK_STATUS_ERROR:
          self::addCustomUpdate('bpk_extern', '', $params, $update);
          self::addCustomUpdate('vbpk',       '', $params, $update);
          self::addCustomUpdate('error_note', '', $params, $update);
          break;

        case BPK_STATUS_AMBIG:
          self::addCustomUpdate('bpk_extern', '', $params, $update);
          self::addCustomUpdate('vbpk',       '', $params, $update);
          self::addCustomUpdate('error_note', '', $params, $update);
          break;

        default:
          break;
      }

      // execute the update
      if (!empty($update)) {
        self::$recursion = TRUE;
        $update['id'] = $entityID;
        civicrm_api3('Contact', 'create', $update);
        self::$recursion = FALSE;
      }
    }
  }

  /**
   * get a vale from the custom data blob
   */
  protected static function getCustomValue($col_name, $customData) {
    foreach ($customData as $customFieldData) {
      if ($customFieldData['column_name'] == $col_name) {
        return $customFieldData['value'];
      }
    }
    return NULL;
  }

  /**
   * Add a variable to the $update set
   *  unless the value already has the expected value
   */
  protected static function addCustomUpdate($col_name, $value, $customData, &$update) {
    foreach ($customData as &$customFieldData) {
      if ($customFieldData['column_name'] == $col_name) {
        if ($customFieldData['value'] != $value) {
          $update["custom_{$customFieldData['custom_field_id']}"] = $value;
        }
        return;
      }
    }
  }

  /**
   * get the ID of the bpk group
   */
  public static function getBpkGroupID() {
    if (self::$bpk_group_id === NULL) {
      self::$bpk_group_id = civicrm_api3('CustomGroup', 'getvalue', array('name' => 'bpk', 'return' => 'id'));
    }
    return self::$bpk_group_id;
  }
}
