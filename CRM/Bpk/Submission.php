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

  private static $_cleanupSQLs  = array();
  private static $_cleanupFiles = array();

  protected $submission_id;
  protected $amount;
  protected $reference;
  protected $year;
  protected $type_map;
  protected $tmp_file;

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
    $this->tmp_file   = NULL;
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
   * get a tmp file to write the XML into
   */
  public function getTmpFile() {
    if ($this->tmp_file === NULL) {
      $this->tmp_file = tempnam(sys_get_temp_dir(), $this->reference);
    }
    return $this->tmp_file;
  }

  /**
   * get a proposed file name for the XML file
   */
  public function getFileName() {
    return $this->reference . '.xml';
  }

  /**
   * Add an individual entry
   *  contact_id        - the donor's contact ID
   *  amount            - the donor's total amount for that year
   *  stype             - E, A, or S - from the docs:
   *                      "E (Erstübermittlung), A (Änderungsübermittlung) oder S (Stornoübermittlung)"
   *  record_reference  - the generated reference
   */
  public function addEntry($contact_id, $amount, $stype, $record_reference) {
    // lookup type
    $type = $this->type_map[$stype];

    // Storno gets no amount
    if ($stype == 'S') {
      $amount = '0.00';
    }

    // create submission record
    CRM_Core_DAO::executeQuery("
        INSERT INTO `civicrm_bmfsa_record` (`submission_id`,`type`,`contact_id`,`year`,`amount`,`reference`)
        VALUES (%1, %2, %3, %4, %5, %6);", array(
          1 => array($this->submission_id,    'Integer'),
          2 => array($this->type_map[$stype], 'Integer'),
          3 => array($contact_id,             'Integer'),
          4 => array($this->year,             'Integer'),
          5 => array($amount,                 'String'),
          6 => array($record_reference,       'String'),
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
   * Wrap up the current submission
   */
  public function wrapup($writer, $zip_stream) {
    // close the document
    $writer->endElement(); // end SonderausgabenUebermittlung
    $writer->endDocument();
    $writer->flush();

    // commit submission
    $this->commit();

    // add to zip file
    $zip_stream->addFile($this->getTmpFile(), $this->getFileName());

    // remove file
    self::$_cleanupFiles[] = $this->getTmpFile();
  }


  /**
   * do some cleanup
   */
  protected static function cleanup() {
    // clean DB
    foreach (self::$_cleanupSQLs as $cleanupQuery) {
      CRM_Core_DAO::executeQuery($cleanupQuery);
    }
    self::$_cleanupSQLs = array();

    // clean tmp files
    foreach (self::$_cleanupFiles as $file) {
      unlink($file);
    }
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
    $config           = CRM_Bpk_Config::singleton();
    $submission       = NULL;
    $data_pending     = TRUE;
    $record_count     = 0;
    $records_per_file = $config->getRecordsPerFile();
    $zip_file_name    = "BMF-Submission_" . date('YmdHis') . ".zip";
    $zip_tmp_file     = tempnam(sys_get_temp_dir(), $zip_file_name);

    // FETCH FIRST RECORD
    $data = CRM_Core_DAO::executeQuery($sql_query);
    if (!$data->fetch()) {
      CRM_Core_Session::setStatus(E::ts("No changes to submit."), E::ts('No Changes'), 'info');
      self::cleanup();
      return;
    }

    // WRITE HTML download header
    header('Content-Type: application/zip');
    header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    $isIE = strstr($_SERVER['HTTP_USER_AGENT'], 'MSIE');
    if ($isIE) {
      header("Content-Disposition: inline; filename={$zip_file_name}");
      header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
      header('Pragma: public');
    } else {
      header("Content-Disposition: attachment; filename={$zip_file_name}");
      header('Pragma: no-cache');
    }

    // open ZIP output stream
    $zip_stream = new ZipArchive();
    if ($zip_stream->open($zip_tmp_file, ZipArchive::CREATE)!==TRUE) {
      throw new Exception("Cannot open zip output stream", 1);
    }

    // write content
    while ($data_pending) {
      // first check header stuff
      if (($record_count % $records_per_file) == 0) {
        if ($submission) {
          // first: close last submission
          $submission->wrapup($writer, $zip_stream);
        }

        // create new submission
        $submission = new CRM_Bpk_Submission($year);

        // write XML header
        $writer = new XMLWriter();
        $writer->openURI($submission->getTmpFile());
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
      }

      // create a record
      $record_reference = $config->generateRecordReference($year, $data);
      $submission->addEntry($data->contact_id, $data->amount, $data->stype, $record_reference);

      // WRITE BLOCK "Sonderausgaben"
      $writer->startElement("Sonderausgaben");
      $writer->writeAttribute("Uebermittlungs_Typ", $data->stype);
      $writer->startElement("RefNr");
      $writer->text($record_reference);
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
      $record_count += 1;
      $data_pending = $data->fetch();
    }

    // close last submission
    $submission->wrapup($writer, $zip_stream);

    // clean up
    $data->free();
    $zip_stream->close();

    // dump the data
    readfile($zip_tmp_file);
    unlink($zip_tmp_file);

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

    // run duplicate check
    $duplicate_count = self::findBPKDuplicates();
    if ($duplicate_count > 0) {
      CRM_Core_Session::setStatus(E::ts("There are still %1 duplicates (identical BPK) in the system. Please resolve these first.", array(1 => $duplicate_count)), E::ts('Duplicate BPKs!'), 'warn');
      return;
    }

    // TMP TABLE:
    //  eligible submissions
    $eligible_donations = "tmp_bmf_donations_{$year}";
    $bpk_join  = CRM_Bpk_CustomData::createSQLJoin('bpk', 'bpk', 'civicrm_contribution.contact_id');

    // compile where clause
    $where_clauses = $config->getDeductibleContributionWhereClauses();
    $where_clauses[] = "(YEAR(civicrm_contribution.receive_date) = {$year})"; // select year
    $where_clauses[] = "(civicrm_group_contact.id IS NULL)";   // not member of the excluded groups
    $where_clauses[] = "(LENGTH(bpk.vbpk) = 172)";             // only contacts with valid vbpk (172 characters)
    $where_clauses[] = "(bpk.status IN (3,2))";                // status 'Resolved' or 'manual'
    if (!empty($contact_ids)) {
      $contact_id_list = implode(',', $contact_ids);
      $where_clauses[] = "(civicrm_contact.id IN ({$contact_id_list}))";
    }
    $where_clause = implode(' AND ', $where_clauses);
    $excluded_group_ids = $config->getGrousExcludedFromSubmission();

    CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS `{$eligible_donations}`;");
    $eligible_donation_query = "
      CREATE TABLE `{$eligible_donations}` AS
        SELECT
          civicrm_contribution.contact_id AS contact_id,
          SUM(total_amount)               AS amount
        FROM civicrm_contribution
        LEFT JOIN civicrm_contact       ON civicrm_contact.id = civicrm_contribution.contact_id
        LEFT JOIN civicrm_group_contact ON civicrm_group_contact.contact_id = civicrm_contribution.contact_id
                                        AND civicrm_group_contact.group_id IN ({$excluded_group_ids})
                                        AND civicrm_group_contact.status = 'Added'
        {$bpk_join}
        WHERE {$where_clause}
        GROUP BY civicrm_contribution.contact_id;";
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
        MAX(submission_id) AS submission_id,
        MIN(submission_id) AS first_submission_id
      FROM `civicrm_bmfsa_record`
      WHERE {$where_clause}
      GROUP BY contact_id";
    CRM_Core_DAO::executeQuery($lastsubmission_query);
    CRM_Core_DAO::executeQuery("ALTER TABLE `{$last_submission_link}` ADD INDEX `contact_id` (`contact_id`);");
    CRM_Core_DAO::executeQuery("ALTER TABLE `{$last_submission_link}` ADD INDEX `submission_id` (`submission_id`);");
    CRM_Core_DAO::executeQuery("ALTER TABLE `{$last_submission_link}` ADD INDEX `first_submission_id` (`first_submission_id`);");

    // FINAL QUERY:
    //   all eligible donations that haven't been submitted in this way
    //   + all submissions without any eligible donation
    $bpk_join1 = CRM_Bpk_CustomData::createSQLJoin('bpk', 'bpk',  'donation.contact_id');
    $bpk_join2 = CRM_Bpk_CustomData::createSQLJoin('bpk', 'bpk2', 'submission2.contact_id');
    $sql_query = "
    SELECT
      donation.contact_id                         AS contact_id,
      first_rec.reference                         AS reference,
      IF(record.type IS NULL OR record.type = 3, 'E', 'A')
                                                  AS stype,
      donation.amount                             AS amount,
      bpk.bpk_extern                              AS bpk,
      bpk.vbpk                                    AS vbpk
    FROM `{$eligible_donations}` donation
    {$bpk_join1}
    LEFT JOIN `{$last_submission_link}` submission ON submission.contact_id = donation.contact_id
    LEFT JOIN `civicrm_bmfsa_record`    first_rec  ON submission.contact_id = first_rec.contact_id
                                                  AND submission.first_submission_id = first_rec.submission_id
    LEFT JOIN `civicrm_bmfsa_record`    record     ON submission.contact_id = record.contact_id
                                                  AND submission.submission_id = record.submission_id
    WHERE (record.amount IS NULL OR donation.amount <> record.amount)

    UNION ALL

    SELECT
      submission2.contact_id AS contact_id,
      first_rec2.reference   AS reference,
      'S'                    AS stype,
      record2.amount         AS amount,
      bpk2.bpk_extern        AS bpk,
      bpk2.vbpk              AS vbpk
    FROM `{$last_submission_link}` submission2
    {$bpk_join2}
    LEFT JOIN `civicrm_bmfsa_record`    first_rec2  ON submission2.contact_id = first_rec2.contact_id
                                                   AND submission2.first_submission_id = first_rec2.submission_id
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


  /**
   * Get a count of the current number of active duplicates by BPK
   *
   * @return int number of duplicates found
   */
  public static function findBPKDuplicates() {
    // clear tmp view
    CRM_Core_DAO::executeQuery("DROP VIEW IF EXISTS tmp_bpk_dupecheck;");

    // compile helper view clauses
    $config = CRM_Bpk_Config::singleton();
    $where_clauses = array(); // don't dot this for now: $config->getDeductibleContributionWhereClauses();
    $where_clauses[] = "(bpk_extern IS NOT NULL)";
    $where_clauses[] = "(bpk_extern <> '')";
    $where_clauses[] = "(civicrm_contact.is_deleted = 0)";
    $where_clauses[] = "(YEAR(civicrm_contribution.receive_date) >= 2017)";
    $where_clause = implode(' AND ', $where_clauses);

    // create the helper view
    CRM_Core_DAO::executeQuery("
      CREATE VIEW tmp_bpk_dupecheck AS
      SELECT
        COUNT(civicrm_contribution.id)      AS contributions_since2017,
        COUNT(DISTINCT(civicrm_contact.id)) AS occurrences
      FROM civicrm_value_bpk
      LEFT JOIN civicrm_contact      ON civicrm_contact.id              = civicrm_value_bpk.entity_id
      LEFT JOIN civicrm_contribution ON civicrm_contribution.contact_id = civicrm_value_bpk.entity_id
      WHERE {$where_clause}
      GROUP BY bpk_extern;");

    // evaluate
    $duplicate_count = CRM_Core_DAO::singleValueQuery("
      SELECT COUNT(*)
      FROM tmp_bpk_dupecheck
      WHERE occurrences > 1
        AND contributions_since2017 > 0;");

    // clear tmp view
    CRM_Core_DAO::executeQuery("DROP VIEW IF EXISTS tmp_bpk_dupecheck;");

    return $duplicate_count;
  }
}
