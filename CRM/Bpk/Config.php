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
 * Configurations
 */
class CRM_Bpk_Config {

  private static $singleton = NULL;

  // default limit
  private $limit = 200;
  private $id_counter = 0;
  private $reference_salt = NULL;

  protected $jobs = NULL;


  /**
   * get the config instance
   */
  public static function singleton() {
    if (self::$singleton === NULL) {
      self::$singleton = new CRM_Bpk_Config();
    }
    return self::$singleton;
  }

  /**
   * get bPK settings
   *
   * @return array
   */
  public function getSettings() {
    $settings = CRM_Core_BAO_Setting::getItem('de.systopia.bpk', 'bpk_settings');
    if (!$settings) {
      // TODO: defaults
      return array(
        'limit'                 => $this->limit,
        'key'                   => 'enter key here',
        'soap_header_namespace' => "http://egov.gv.at/pvp1.xsd",
        'soap_server_url'       => "https://pvawp.bmi.gv.at/bmi.gv.at/soap/SZ2Services/services/SZR");
    } else {
      return $settings;
    }
  }

  /**
   * get the default limit for bpk-requests per MINUTE
   * @return int
   */
  public function getDefaultLimit() {
    return $this->limit;
  }

  public function getSoapHeaderSettings() {
    $settings = CRM_Core_BAO_Setting::getItem('de.systopia.bpk', 'bpk_settings');
    if (!$settings) {
      return array();
    }
    $settings_elements = CRM_Bpk_Form_Settings::getSoapHeaderSettingsParameters();
    foreach ($settings as $key => $value) {
      if (!in_array($key, $settings_elements)) {
        unset($settings[$key]);
      }
    }
    return $settings;
  }

  public function getTableName() {
    return CRM_Bpk_CustomData::getGroupTable("bpk");
  }

  /**
   * set bPK settings
   *
   * @param $settings array
   */
  public function setSettings($settings) {
    CRM_Core_BAO_Setting::setItem($settings, 'de.systopia.bpk', 'bpk_settings');
  }

  /**
   * Install a scheduled job if there isn't one already
   */
  public static function installScheduledJob() {
    $config = self::singleton();
    $jobs = $config->getScheduledJobs();
    if (empty($jobs)) {
      // none found? create a new one
      civicrm_api3('Job', 'create', array(
        'api_entity'    => 'Bpk',
        'api_action'    => 'lookup',
        'run_frequency' => 'Hourly',
        'name'          => E::ts('Run bPK Lookup'),
        'description'   => E::ts('Will try to resolve the bPK for contacts that don\'t have one'),
        'is_active'     => '0'));
    }
  }

  /**
   * get all scheduled jobs that trigger the dispatcher
   */
  public function getScheduledJobs() {
    if ($this->jobs === NULL) {
      // find all scheduled jobs calling Sqltask.execute
      $query = civicrm_api3('Job', 'get', array(
        'api_entity'   => 'Bpk',
        'api_action'   => 'lookup',
        'option.limit' => 0));
      $this->jobs = $query['values'];
    }
    return $this->jobs;
  }

  /**
   * The organisation's uniqe "Finanzamt-Steuer-Nummer"
   */
  public function getFastnr() {
    $settings = $this->getSettings();
    return CRM_Utils_Array::value('fastnr', $settings, '');
  }

  /**
   * The organisation type in terms of tax exception
   */
  public function getOrgType() {
    $settings = $this->getSettings();
    return CRM_Utils_Array::value('fasttype', $settings, '');
  }

  /**
   * Get list of financial types that are deductible from taxes
   *
   * @return array of financial type IDs, or NULL (meaning all)
   */
  public function getDeductibleFinancialTypes() {
    // TODO: Setting?
    return NULL; // all
  }

  /**
   * Get list of contribution statuses that are deductible
   *
   * @return array of contributions status IDs
   */
  public function getDeductibleContributionsStatuses() {
    // TODO: Setting?
    return array('1'); // Completed
  }

  /**
   * Build (AND) where clauses for the contribution selector
   *
   * @return SQL SELECT
   */
  public function getDeductibleContributionWhereClauses($contribution_table_name = 'civicrm_contribution') {
    $where_clauses = array();

    // add NO TEST clause
    $where_clauses[] = "(({$contribution_table_name}.is_test = 0) OR ({$contribution_table_name}.is_test IS NULL))";

    // add financial types clause
    $financial_types = $this->getDeductibleFinancialTypes();
    if (!empty($financial_types)) {
      $financial_type_list = implode(',', $financial_types);
      $where_clauses[] = "({$contribution_table_name}.financial_type_id IN ({$financial_type_list}))";
    }

    // add contribution clauses
    $contribution_statuses = $this->getDeductibleContributionsStatuses();
    if (!empty($contribution_statuses)) {
      $contribution_status_list = implode(',', $contribution_statuses);
      $where_clauses[] = "({$contribution_table_name}.contribution_status_id IN ({$contribution_status_list}))";
    }

    return $where_clauses;
  }

  /**
   * Generates a unique submission message reference
   */
  public function generateSubmissionReference() {
    // TODO: implement config option
    $reference_base   = 'GP-' . date('ymd') . '-';
    $counter          = $this->id_counter; // start with the last value
    $reference_exists = TRUE;
    while ($reference_exists) {
      $counter += 1;
      $proposed_reference = $reference_base . sprintf("%03d", $counter);
      $reference_exists = CRM_Core_DAO::singleValueQuery("SELECT id FROM `civicrm_bmfsa_submission` WHERE reference = %1",
                            array(1 => array($proposed_reference, 'String')));
    }

    $this->id_counter = $counter;
    return $proposed_reference;
  }

  /**
   * Generates a reference for the per-contact
   *  submission record
   */
  public function generateRecordReference($year, $data) {
    if (empty($data->reference)) {
      $salt = $this->getRecordReferenceSalt();
      return "{$year}-" . substr(sha1($salt . $data->bpk), 0, 18);
    } else {
      return $data->reference;
    }
  }

  /**
   * get the salt used for the reference generation
   * from the settings.
   * when called first, it will be generated
   */
  public function getRecordReferenceSalt() {
    if ($this->reference_salt === NULL) {
      $settings = $this->getSettings();
      if (empty($settings['reference_salt'])) {
        // first time: generate reference salt
        $settings['reference_salt'] = sha1(CIVICRM_SITE_KEY . microtime());
        $this->setSettings($settings);
      }
      $this->reference_salt = $settings['reference_salt'];
    }
    return $this->reference_salt;
  }

  /**
   * Returns the IDs of the contact groups that shoulde
   * be excluded from submission
   *
   * CAUTION: has to return at least 1 ID to avoid SQL Syntax error,
   *             send a dummy (e.g. 9999999) if you don't want to exclude any
   *
   * @return string a csv list of ids
   */
  public function getGroupsExcludedFromSubmission() {
    $settings = $this->getSettings();
    if (!empty($settings['exclude_groups']) && is_array($settings['exclude_groups'])) {
      return implode(',', $settings['exclude_groups']);
    } else {
      return '0';
    }
  }

  /**
   * get the limit of records per XML file
   */
  public function getRecordsPerFile() {
    return 10000; // as per XSD (https://www.bmf.gv.at/egovernment/fon/fuer-softwarehersteller/UebermittlungSonderausgaben_2.xsd)
  }

  /**
   * Get a list of years to be eligible for submission
   *
   * @return array with the year in key and value
   */
  public function getEligibleYearsForSubmission() {
    $year_list = array();
    $years = range(date('Y'), 2017);
    foreach ($years as $year) {
      $year_list[$year] = $year;
    }
    return $year_list;
  }

  /**
   * Get the exclusion activity type or '0' if not found
   */
  public static function getExclusionActivityTypeID() {
    try {
      return civicrm_api3('OptionValue', 'getvalue', [
          'return'          => 'value',
          'option_group_id' => 'activity_type',
          'name'            => 'bpk_submission_exclusion']);
    } catch (Exception $ex) {
      CRM_Core_Error::debug_log_message("BPK: Couldn't find submission exclusion activity type. Error was: " . $ex->getMessage());
      return '0';
    }
  }
}