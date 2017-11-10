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


class CRM_Bpk_Lookup {

  protected $success = 0;
  protected $failed  = 0;
  protected $params  = NULL;
  protected $config  = NULL;

  protected function __construct($params) {
    $this->params = $params;
    $this->config = CRM_Bpk_Config::singleton();
  }

  /**
   * Run a bPK lookup / store for eligible contacts
   *
   * @return array with results: ['success' => <count>, 'failed' => <count>]
   */
  public static function doLookup($params) {
    $runner = new CRM_Bpk_Lookup();

    // step 1: select eligible contacts
    $select_sql = self::createSelectionQuery();

    // step 2: resolve
    self::executeLookupFor($select_sql);

    return $runner->getResult();
  }

  /**
   *
   */
  protected function getResult() {
    return array(
      'success' => $this->success,
      'failed'  => $this->failed,
    );
  }

  /**
   * Generate a SQL query to select the pending contacts
   */
  protected function createSelectionQuery() {
    // extract limit
    $limit = $this->config->getDefaultLimit();
    if (isset($this->params['limit'])) {
      $limit = (int) $this->params['limit'];
    }
    $limit_sql = "LIMIT {$limit}";

    // contact_id (for testing)
    if (!empty($this->params['contact_id'])) {
      $contact_id = (int) $this->params['contact_id'];
      $where_clauses_OR[] = "contact.id = {$contact_id}";

    } else {
      // generate WHERE clause
      // pba: bpk queries must always have first_name, last_name and birth_date
      // TODO: implement selection criteria
      $where_clauses_OR[] = "contact.birth_date IS NOT NULL";
      $where_clauses_OR[] = "contact.first_name IS NOT NULL";
      $where_clauses_OR[] = "contact.last_name IS NOT NULL";
    }

    $table_name = $this->config->getTableName();
    $field_name = 'bpk'; // TODO: will this change?
    $where_sql  = implode(') OR (', $where_clauses_OR);

    // TODO: check if contact_type = 'Individual' and bpk_group.{$field_name} IS NULL is correct
    $sql = "SELECT
             contact.id         AS contact_id,
             contact.first_name AS first_name,
             contact.last_name  AS last_name,
             contact.birth_date AS birth_date,
            FROM civicrm_contact contact
            LEFT JOIN {$table_name} bpk_group ON bpk_group.entity_id = contact.id
            WHERE (({$where_clause}))
            AND bpk_group.{$field_name} IS NULL
            AND contact.is_deleted = 0
            AND contact.contact_type = 'Individual'
            {$limit_sql}";

    return $sql;
  }

  /**
   *
   */
  protected function executeLookupFor($sql) {
    $cursor = CRM_Core_DAO::executeQuery($sql);
    while ($cursor->fetch()) {
      // $cursor->first_name, ...
      // TODO:
      $result = $this->getBpkResult($cursor);
      $this->storeResult($result);
    }
  }

  protected function getBpkResult($contact) {
    // TODO: SOAP lookup
    return array(
      'bPK' => '',
      'error_code' => '',
      'contact_id' => $contact->contact_id,
    );
  }

  /**
   * Store result in contact
   */
  protected function storeResult($result) {
    // TODO: does it work?
    $update = array(
      'id'                   => $result['contact_id'],
      'mygropup.bpk'         => $result['bPK'],
      'mygropup.status'      => $result['status'],
      'mygropup.lookup_date' => date('YmdHis')
    );
    CRM_Bpk_CustomData::resolveCustomFields($update);
    civicrm_api3('Contact', 'create', $update);
  }
}
