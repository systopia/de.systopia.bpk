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
 * This class contains the logic to generate XML files
 */
class CRM_Bpk_Submission {

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

    // start a transaction (so we can discard if necessary)
    // \Civi\Core\Transaction\Manager::singleton()->inc(TRUE);

    // create submission entry
    CRM_Core_DAO::executeQuery("
        INSERT INTO `civicrm_bmisa_submission` (`year`,`date`,`reference`,`amount`,`created_by`)
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
    $type = $this->type_map[$stype];

    // create submission record
    CRM_Core_DAO::executeQuery("
        INSERT INTO `civicrm_bmisa_record` (`submission_id`,`type`,`contact_id`,`year`,`amount`)
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
   * Finish the the submission
   */
  public function commit() {
    CRM_Core_DAO::executeQuery("
        UPDATE `civicrm_bmisa_submission`
        SET amount = %1
        WHERE id = %2 ", array(
          1 => array($this->amount,        'String'),
          2 => array($this->submission_id, 'Integer'),
        ));

    // close transaction
    // \Civi\Core\Transaction\Manager::singleton()->dec();
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
    $writer->text($message_reference);
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
    $data = CRM_Core_DAO::executeQuery($sql_query);
    while ($data->fetch()) {
      // create a record
      $submission->addEntry($data->contact_id, $data->amount, $data->stype);

      // WRITE BLOCK "Sonderausgaben"
      $writer->startElement("Sonderausgaben");
      $writer->writeAttribute("Uebermittlungs_Typ", $data->stype);
      $writer->startElement("RefNr");
      $writer->text($data->reference);
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
    }

    $writer->endElement(); // end SonderausgabenUebermittlung
    $writer->endDocument();
    $writer->flush();

    // write-through the submission
    $submission->commit();

    // we're done, no return
    CRM_Utils_System::civiExit();
  }

  /**
   * Get the number of (active) submissions for the given contact
   */
  public static function getSubmissionCount($contact_id, $years_only = FALSE) {
    if ($years_only) {
      return CRM_Core_DAO::singleValueQuery("SELECT COUNT(DISTINCT(`year`)) FROM `civicrm_bmisa_record` WHERE `contact_id` = %1;", array(1 => array($contact_id, 'Integer')));
    } else {
      return CRM_Core_DAO::singleValueQuery("SELECT COUNT(*) FROM `civicrm_bmisa_record` WHERE `contact_id` = %1;", array(1 => array($contact_id, 'Integer')));
    }
  }


  /**
   * Generate (and stream to the output) the XML for
   * a list of contact IDs
   */
  public static function generateForContactIDs($year, $contact_ids) {
    $year = (int) $year;
    $ids  = implode(',', $contact_ids);
    $bpk_join  = CRM_Bpk_CustomData::createSQLJoin('bpk', 'bpk', 'civicrm_contribution.contact_id');
    $sql_query = "
    SELECT
      contact_id                     AS contact_id,
      SUM(total_amount)              AS amount,
      CONCAT('{$year}-', contact_id) AS reference,
      'E'                            AS stype,
      bpk.vbpk                       AS vbpk
    FROM civicrm_contribution
    LEFT JOIN civicrm_contact ON civicrm_contact.id = civicrm_contribution.contact_id
    {$bpk_join}
    WHERE YEAR(receive_date) = {$year}
      AND contribution_status_id = 1
      AND contact_id IN ({$ids})
      AND is_deleted = 0
      AND bpk.vbpk IS NOT NULL
    GROUP BY contact_id;
    ";
    return self::run($sql_query, $year);
  }

  /**
   * Generate (and stream to the output) the XML for
   * all contacts of the given year
   */
  public static function generateYear($year, $type) {
    $year = (int) $year;
    $ids  = implode(',', $contact_ids);
    $bpk_join  = CRM_Bpk_CustomData::createSQLJoin('bpk', 'bpk', 'civicrm_contribution.contact_id');
    $sql_query = "
    SELECT
      contact_id                     AS contact_id,
      SUM(total_amount)              AS amount,
      CONCAT('{$year}-', contact_id) AS reference,
      '{$type}'                      AS stype,
      bpk.vbpk                       AS vbpk
    FROM civicrm_contribution
    LEFT JOIN civicrm_contact ON civicrm_contact.id = civicrm_contribution.contact_id
    {$bpk_join}
    WHERE YEAR(receive_date) = {$year}
      AND contribution_status_id = 1
      AND is_deleted = 0
      AND bpk.vbpk IS NOT NULL
    GROUP BY contact_id;
    ";
    return self::run($sql_query, $year);
  }
}
