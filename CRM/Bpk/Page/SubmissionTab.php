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

/**
 * This tab will show an overview of a contact's
 * submissions to the BMF
 */
class CRM_Bpk_Page_SubmissionTab extends CRM_Core_Page {

  public function run() {
    $contact_id = CRM_Utils_Request::retrieve('cid', 'Integer');
    $type_map = array(1 => 'E', 2 => 'A', 3 => 'S');
    $config = CRM_Bpk_Config::singleton();

    // gather donation data of this contact
    $annual_donations = array();
    $where_clauses = $config->getDeductibleContributionWhereClauses();
    $where_clauses[] = "(civicrm_contribution.contact_id = %1)";
    $where_clause = implode(' AND ', $where_clauses);
    $query = CRM_Core_DAO::executeQuery("
      SELECT
        YEAR(civicrm_contribution.receive_date) AS year,
        SUM(civicrm_contribution.total_amount)  AS amount
      FROM civicrm_contribution
      WHERE {$where_clause}
      GROUP BY YEAR(civicrm_contribution.receive_date)
      ORDER BY YEAR(civicrm_contribution.receive_date);", array(1 => array($contact_id, 'Integer')));
    while ($query->fetch()) {
      $annual_donations[$query->year] = $query->amount;
    }

    // gather the submission data of this contact
    $years = array();
    $submissions = array();
    $query = CRM_Core_DAO::executeQuery("
      SELECT
        submission.reference AS reference,
        submission.date      AS date,
        submission.year      AS year,
        record.type          AS type,
        record.reference     AS rec_ref,
        record.amount        AS amount
      FROM `civicrm_bmfsa_record` record
      LEFT JOIN `civicrm_bmfsa_submission` submission ON submission.id = record.submission_id
      WHERE record.contact_id = %1
      ORDER BY record.year DESC, submission.date DESC",
      array(1 => array($contact_id, 'Integer')));

    while ($query->fetch()) {
      // calculate class
      if (isset($years[$query->year])) {
        $class = 'bmfsa-corrected';
        $current = '';
      } else {
        $years[$query->year] = 1; // mark year
        $current = isset($annual_donations[$query->year]) ? $annual_donations[$query->year] : 0.00;
        if ($current == $query->amount) {
          $class = 'bmfsa-current';
        } else {
          $class = 'bmfsa-changed';
        }
      }

      $submissions[] = array(
        'reference' => $query->reference,
        'rec_ref'   => $query->rec_ref,
        'date'      => $query->date,
        'year'      => $query->year,
        'amount'    => $query->amount,
        'type'      => $type_map[$query->type],
        'class'     => $class,
        'current'   => $current,
      );
    }

    $this->assign('submissions', $submissions);


    // add exclusion information
    // FIRST: exclusion groups
    $config = CRM_Bpk_Config::singleton();
    $excluded_group_ids = $config->getGroupsExcludedFromSubmission();
    if (empty($excluded_group_ids)) {
      $this->assign('exclusion_group_status', E::ts("No exclusion groups have been configured."));

    } else {
      // load groups
      $group_ids = explode(',', $excluded_group_ids);
      $group_names = [];
      $groups = civicrm_api3('Group', 'get', [
          'return'       => 'title,id',
          'id'           => ['IN' => $group_ids],
          'option.limit' => 0]);
      foreach ($groups['values'] as $group) {
        $group_names[] = $group['title'];
      }
      $group_name_list = '"' . implode('", "', $group_names) . '"';

      // check if contact is a member of those groups
      $membership_count = 0;
      foreach ($group_ids as $group_id) { // cannot use 'IN' => $group_ids in CiviCRM 4.6x
        $membership = civicrm_api3('GroupContact', 'get', [
            'group_id'   => $group_id,
            'contact_id' => $contact_id]);
        $membership_count += $membership['count'];
      }

      if ($membership_count > 0) {
        $this->assign('exclusion_group_status', E::ts("This contact is member of %2 of the excluding groups (%1). <strong>No contributions will be submitted!</strong>", [1 => $group_name_list, 2 => $membership_count]));
      } else {
        $this->assign('exclusion_group_status', E::ts("This contact is <strong>not</strong> member of any of the excluding groups (%1).", [1 => $group_name_list]));
      }
    }

    // SECOND: exclusion activities
    $years = $this->getExcludedYears($contact_id);
    if (empty($years)) {
      $this->assign('exclusion_activity_status', E::ts("This contact currently has no further individual exclusions via activity."));
    } else {
      $year_list = implode(', ', $years);
      $this->assign('exclusion_activity_status', E::ts("This contact further has <strong>excluded the following years from submission : %1</strong>", [1 => $year_list]));
    }

    // FINALLY: add 'create exception' link
    $this->assign('exclusion_activity_create', CRM_Utils_System::url('civicrm/activity/add', "action=add&reset=1&cid={$contact_id}&atype=108"));

    // finally: let's add some style...
    CRM_Core_Resources::singleton()->addStyleFile('de.systopia.bpk', 'css/bmfsa.css');

    parent::run();
  }

  /**
   * Extract the years that have been excluded from submission
   *  via activity for this contact
   * @param $contact_id
   * @return array
   */
  protected function getExcludedYears($contact_id) {
    // TODO: implement
    return range(2018,2022);
  }
}
