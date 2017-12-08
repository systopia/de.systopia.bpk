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
        'limit' => 200,
        'key'   => 'enter key here');
    } else {
      return $settings;
    }
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
}