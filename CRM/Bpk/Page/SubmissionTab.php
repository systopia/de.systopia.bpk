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
 * submissions to the BMI
 */
class CRM_Bpk_Page_SubmissionTab extends CRM_Core_Page {

  public function run() {
    $contact_id = CRM_Utils_Request::retrieve('cid', 'Integer');

    // TODO: gather donation data of this contact

    // gather the submission data of this contact
    $submissions = array();
    $query = CRM_Core_DAO::executeQuery("
      SELECT
        submission.reference AS reference,
        submission.date      AS date,
        record.amount        AS amount,
        record.type          AS type
      FROM `civicrm_bmisa_record` record
      LEFT JOIN `civicrm_bmisa_submission` submission ON submission.id = record.submission_id
      WHERE record.contact_id = %1
      ORDER BY record.year DESC, submission.date DESC",
      array(1 => array($contact_id, 'Integer')));

    while ($query->fetch()) {
      $submissions[] = array(
        'reference' => $query['reference'],
        'date'      => $query['date'],
        'amount'    => $query['amount'],
        'type'      => $query['type'],
      );
    }

    $this->assign('submissions', $submissions);

    // // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    // CRM_Utils_System::setTitle(E::ts('SubmissionTab'));

    // // Example: Assign a variable for use in a template
    // $this->assign('currentTime', date('Y-m-d H:i:s'));

    parent::run();
  }

}
