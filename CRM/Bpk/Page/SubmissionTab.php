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
    $type_map = array(1 => 'E', 2 => 'A', 3 => 'S');

    // TODO: gather donation data of this contact

    // gather the submission data of this contact
    $years = array();
    $submissions = array();
    $query = CRM_Core_DAO::executeQuery("
      SELECT
        submission.reference AS reference,
        submission.date      AS date,
        submission.year      AS year,
        record.amount        AS amount,
        record.type          AS type
      FROM `civicrm_bmisa_record` record
      LEFT JOIN `civicrm_bmisa_submission` submission ON submission.id = record.submission_id
      WHERE record.contact_id = %1
      ORDER BY record.year DESC, submission.date DESC",
      array(1 => array($contact_id, 'Integer')));

    while ($query->fetch()) {
      // calculate class
      if (isset($years[$query->year])) {
        $class = 'bmisa-corrected';
      } else {
        $class = 'bmisa-current';
        $years[$query->year] = 1; // mark year
      }

      $submissions[] = array(
        'reference' => $query->reference,
        'date'      => $query->date,
        'year'      => $query->year,
        'amount'    => $query->amount,
        'type'      => $type_map[$query->type],
        'class'     => $class,
      );
    }

    $this->assign('submissions', $submissions);

    // let's add some style...
    CRM_Core_Resources::singleton()->addStyleFile('de.systopia.bpk', 'css/bmisa.css');

    parent::run();
  }

}
