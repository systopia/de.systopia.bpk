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

  /**
   * Generates a unique message reference
   */
  protected static function generateMessageReference() {
    // TODO: implement
    return "GP-" . (int) microtime(TRUE);
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
    return self::generateXML($sql_query, $year);
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
    return self::generateXML($sql_query, $year);
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
  protected static function generateXML($sql_query, $year) {
    $config = CRM_Bpk_Config::singleton();
    $message_reference = self::generateMessageReference();

    // WRITE HTML download header
    header('Content-Type: text/xml');
    header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    $isIE = strstr($_SERVER['HTTP_USER_AGENT'], 'MSIE');
    if ($isIE) {
      header("Content-Disposition: inline; filename={$message_reference}.xml");
      header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
      header('Pragma: public');
    } else {
      header("Content-Disposition: attachment; filename={$message_reference}.xml");
      header('Pragma: no-cache');
    }

    // write XML header
    $writer = new XMLWriter();
    $writer->openURI("php://output");
    $writer->startElement("SonderausgabenUebermittlung");

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
      // WRITE Sonderausgaben BLOCK
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
    $writer->flush();

    // we're done, no return
    CRM_Utils_System::civiExit();
  }
}
