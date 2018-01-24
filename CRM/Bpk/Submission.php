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
 * This class contains the logic to generate XML files
 */
class CRM_Bpk_Submission {

  private static $_cleanupSQLs = array();

  protected $submission_id;
  protected $amount;
  protected $reference;
  protected $year;
  protected $type_map;

  /**
   * Creates a new submission instance,
   * which will only be submitted once
   * CRM_Bpk_Submission::store() is called
   * or discarded if CRM_Bpk_Submission::discard().
   */
  public function __construct($year) {
    $config = CRM_Bpk_Config::singleton();
    $this->reference  = $config->generateSubmissionReference();
    $this->amount     = 0.0;
    $this->year       = $year;
    $created_by = CRM_Core_Session::getLoggedInContactID();
    if (empty($created_by)) {
      // fallback to avoid errors
      $created_by = 1;
    }

    // start a transaction (so we can discard if necessary)
    // \Civi\Core\Transaction\Manager::singleton()->inc(TRUE);

    // create submission entry
    CRM_Core_DAO::executeQuery("
        INSERT INTO `civicrm_bmfsa_submission` (`year`,`date`,`reference`,`amount`,`created_by`)
        VALUES (%1, NOW(), %2, 0.00, %3);", array(
          1 => array($this->year,      'Integer'),
          2 => array($this->reference, 'String'),
          3 => array($created_by,      'Integer')
        ));

    // finally, store some parameters
    $this->submission_id = CRM_Core_DAO::singleValueQuery("SELECT LAST_INSERT_ID();");
    $this->type_map = array('E' => 1, 'A' => 2, 'S' => 3);
  }

  /**
   * Add an individual entry
   *  contact_id  - the donor's contact ID
   *  amount      - the donor's total amount for that year
   *  stype       - E, A, or S - from the docs:
   *                   "E (Erstübermittlung), A (Änderungsübermittlung) oder S (Stornoübermittlung)"
   */
  public function addEntry($contact_id, $amount, $stype) {
    // lookup type
    $type = $this->type_map[$stype];

    // Storno gets no amount
    if ($stype == 'S') {
      $amount = '0.00';
    }

    // create submission record
    CRM_Core_DAO::executeQuery("
        INSERT INTO `civicrm_bmfsa_record` (`submission_id`,`type`,`contact_id`,`year`,`amount`)
        VALUES (%1, %2, %3, %4, %5);", array(
          1 => array($this->submission_id,    'Integer'),
          2 => array($this->type_map[$stype], 'Integer'),
          3 => array($contact_id,             'Integer'),
          4 => array($this->year,             'Integer'),
          5 => array($amount,                 'String'),
        ));

    // also: keep track of the total
    $this->amount += $amount;
  }

  /**
   * Generate an individual message for the contact
   */
  public function generateContactReference($contact_id) {
    return $this->reference . '-' . $contact_id;
  }

  /**
   * Finish the the submission
   */
  public function commit() {
    CRM_Core_DAO::executeQuery("
        UPDATE `civicrm_bmfsa_submission`
        SET amount = %1, date = NOW()
        WHERE id = %2 ", array(
          1 => array($this->amount,        'String'),
          2 => array($this->submission_id, 'Integer'),
        ));

    // close transaction
    // \Civi\Core\Transaction\Manager::singleton()->dec();
  }


  /**
   * do some DB cleanup
   */
  protected static function cleanup() {
    foreach (self::$_cleanupSQLs as $cleanupQuery) {
      CRM_Core_DAO::executeQuery($cleanupQuery);
    }
    self::$_cleanupSQLs = array();
  }


  /**
   * will write the results of the XML file into the output stream
   *
   * Expects the $sql_query to yield the following fields
   *  contact_id  - the contact ID (duh)
   *  vbpk        - the contact's vBPK
   *  reference   - reference for the contact's submission
   *  amount      - the total amount
   *  stype       - E, A, or S - from the docs:
   *                   "E (Erstübermittlung), A (Änderungsübermittlung) oder S (Stornoübermittlung)"
   */
  protected static function run($sql_query, $year) {
    $config = CRM_Bpk_Config::singleton();
    $submission = new CRM_Bpk_Submission($year);
    $data_pending = TRUE;

    // FETCH FIRST RECORD
    $data = CRM_Core_DAO::executeQuery($sql_query);
    if (!$data->fetch()) {
      CRM_Core_Session::setStatus(E::ts("No changes to submit."), E::ts('No Changes'), 'info');
      return;
    }

    // WRITE HTML download header
    header('Content-Type: text/xml');
    header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    $isIE = strstr($_SERVER['HTTP_USER_AGENT'], 'MSIE');
    if ($isIE) {
      header("Content-Disposition: inline; filename={$submission->reference}.xml");
      header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
      header('Pragma: public');
    } else {
      header("Content-Disposition: attachment; filename={$submission->reference}.xml");
      header('Pragma: no-cache');
    }

    // write XML header
    $writer = new XMLWriter();
    $writer->openURI("php://output");
    $writer->startDocument();
    $writer->startElement("SonderausgabenUebermittlung");
    $writer->writeAttribute('xmlns', 'https://finanzonline.bmf.gv.at/fon/ws/uebermittlungSonderausgaben');

    // WRITE Info_Daten BLOCK
    $writer->startElement("Info_Daten");
    $writer->startElement("Fastnr_Fon_Tn");
    $writer->text($config->getFastnr());
    $writer->endElement(); // end Fastnr_Fon_Tn
    $writer->startElement("Fastnr_Org");
    $writer->text($config->getFastnr());
    $writer->endElement(); // end Fastnr_Org
    $writer->endElement(); // end Info_Daten

    // WRITE MessageSpec BLOCK
    $writer->startElement("MessageSpec");
    $writer->startElement("MessageRefId");
    $writer->text($submission->reference);
    $writer->endElement(); // end MessageRefId
    $writer->startElement("Timestamp");
    $writer->text(date('Y-m-d\TH:i:s'));
    $writer->endElement(); // end Timestamp
    $writer->startElement("Uebermittlungsart");
    $writer->text($config->getOrgType());
    $writer->endElement(); // end Uebermittlungsart
    $writer->startElement("Zeitraum");
    $writer->text($year);
    $writer->endElement(); // end Zeitraum
    $writer->endElement(); // end MessageSpec

    // write content
    while ($data_pending) {
      // create a record
      $submission->addEntry($data->contact_id, $data->amount, $data->stype);

      // WRITE BLOCK "Sonderausgaben"
      $writer->startElement("Sonderausgaben");
      $writer->writeAttribute("Uebermittlungs_Typ", $data->stype);
      $writer->startElement("RefNr");
      $writer->text($config->generateRecordReference($year, $data));
      $writer->endElement(); // end RefNr
      if ($data->stype == 'E' || $data->stype == 'A') {
        $writer->startElement("Betrag");
        $writer->text(number_format($data->amount, 2, '.', ''));
        $writer->endElement(); // end Betrag
      }
      if ($data->stype == 'E') {
        $writer->startElement("vbPK");
        $writer->text($data->vbpk);
        $writer->endElement(); // end vbPK
      }
      $writer->endElement(); // end Sonderausgaben

      // fetch the next record
      $data_pending = $data->fetch();
    }
    $data->free();

    $writer->endElement(); // end SonderausgabenUebermittlung
    $writer->endDocument();
    $writer->flush();

    // write-through the submission
    $submission->commit();

    // we're done, no return
    self::cleanup();
    CRM_Utils_System::civiExit();
  }

  /**
   * Get the number of (active) submissions for the given contact
   */
  public static function getSubmissionCount($contact_id, $years_only = FALSE) {
    if ($years_only) {
      return CRM_Core_DAO::singleValueQuery("SELECT COUNT(DISTINCT(`year`)) FROM `civicrm_bmfsa_record` WHERE `contact_id` = %1;", array(1 => array($contact_id, 'Integer')));
    } else {
      return CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM `civicrm_bmfsa_record` WHERE `contact_id` = %1;", array(1 => array($contact_id, 'Integer')));
    }
  }


  /**
   * Generate (and stream to the output) the XML for
   * all contacts of the given year
   */
  public static function generateYear($year, $contact_ids = NULL) {
    $config = CRM_Bpk_Config::singleton();
    $year = (int) $year;

    // TMP TABLE:
    //  eligible submissions
    $eligible_donations = "tmp_bmf_donations_{$year}";
    $bpk_join  = CRM_Bpk_CustomData::createSQLJoin('bpk', 'bpk', 'civicrm_contribution.contact_id');
    // compile where clause
    $where_clauses = $config->getDeductibleContributionWhereClauses();
    $where_clauses[] = "(YEAR(civicrm_contribution.receive_date) = {$year})"; // select year
    $where_clauses[] = "(civicrm_contact.is_deleted = 0)";                    // no deleted contacts
    $where_clauses[] = "(bpk.vbpk IS NOT NULL)";                              // only contacts with bpk
    if (!empty($contact_ids)) {
      $contact_id_list = implode(',', $contact_ids);
      $where_clauses[] = "(civicrm_contact.id IN ({$contact_id_list}))";
    }
    $where_clause = implode(' AND ', $where_clauses);

    CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS `{$eligible_donations}`;");
    $eligible_donation_query = "
      CREATE TABLE `{$eligible_donations}` AS
        SELECT
          contact_id                     AS contact_id,
          SUM(total_amount)              AS amount
        FROM civicrm_contribution
        LEFT JOIN civicrm_contact ON civicrm_contact.id = civicrm_contribution.contact_id
        {$bpk_join}
        WHERE {$where_clause}
        GROUP BY contact_id;";
    // error_log("T1: {$eligible_donation_query}");
    CRM_Core_DAO::executeQuery($eligible_donation_query);
    CRM_Core_DAO::executeQuery("ALTER TABLE `{$eligible_donations}` ADD INDEX `contact_id` (`contact_id`);");

    // TMP TABLE:
    //   last submission per contact
    $last_submission_link = "tmp_bmf_lastsubmission_{$year}";
    $where_clauses = array("(year = {$year})");
    if (!empty($contact_ids)) {
      $contact_id_list = implode(',', $contact_ids);
      $where_clauses[] = "(contact_id IN ({$contact_id_list}))";
    }
    $where_clause = implode(' AND ', $where_clauses);

    CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS `{$last_submission_link}`;");
    $lastsubmission_query = "
      CREATE TABLE `{$last_submission_link}` AS
      SELECT
        contact_id         AS contact_id,
        MAX(submission_id) AS submission_id
      FROM `civicrm_bmfsa_record`
      WHERE {$where_clause}
      GROUP BY contact_id";
    // error_log("T2: {$lastsubmission_query}");
    CRM_Core_DAO::executeQuery($lastsubmission_query);
    CRM_Core_DAO::executeQuery("ALTER TABLE `{$last_submission_link}` ADD INDEX `contact_id` (`contact_id`);");
    CRM_Core_DAO::executeQuery("ALTER TABLE `{$last_submission_link}` ADD INDEX `submission_id` (`submission_id`);");

    // FINAL QUERY:
    //   all eligible donations that haven't been submitted in this way
    //   + all submissions without any eligible donation
    $bpk_join1 = CRM_Bpk_CustomData::createSQLJoin('bpk', 'bpk',  'donation.contact_id');
    $bpk_join2 = CRM_Bpk_CustomData::createSQLJoin('bpk', 'bpk2', 'submission2.contact_id');
    $sql_query = "
    SELECT
      donation.contact_id                         AS contact_id,
      IF(submission.contact_id IS NULL, 'E', 'A') AS stype,
      donation.amount                             AS amount,
      bpk.vbpk                                    AS vbpk
    FROM `{$eligible_donations}` donation
    {$bpk_join1}
    LEFT JOIN `{$last_submission_link}` submission ON submission.contact_id = donation.contact_id
    LEFT JOIN `civicrm_bmfsa_record`    record     ON submission.contact_id = record.contact_id
                                                  AND submission.submission_id = record.submission_id
    WHERE (record.amount IS NULL OR donation.amount <> record.amount)

    UNION ALL

    SELECT
      submission2.contact_id AS contact_id,
      'S'                    AS stype,
      record2.amount         AS amount,
      bpk2.vbpk              AS vbpk
    FROM `{$last_submission_link}` submission2
    {$bpk_join2}
    LEFT JOIN `civicrm_bmfsa_record`    record2     ON submission2.contact_id = record2.contact_id
                                                   AND submission2.submission_id = record2.submission_id
    LEFT JOIN `{$eligible_donations}` donation2     ON donation2.contact_id = submission2.contact_id
    WHERE submission2.contact_id IS NOT NULL
      AND donation2.contact_id IS NULL
      AND record2.type <> 3
    ;";

    // add cleanup
    self::$_cleanupSQLs[] = "DROP TABLE IF EXISTS `{$last_submission_link}`;";
    self::$_cleanupSQLs[] = "DROP TABLE IF EXISTS `{$eligible_donations}`;";

    // TODO: drop tables?
    return self::run($sql_query, $year);
  }
}
