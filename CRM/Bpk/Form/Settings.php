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
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Bpk_Form_Settings extends CRM_Core_Form {

  public function buildQuickForm() {
    CRM_Utils_System::setTitle(E::ts('BPK Extension Settings'));

    $this->add(
      'text',
      'fastnr',
      E::ts('Finanzamt Steuernummer'),
      TRUE
    );

    $this->add(
      'select',
      'fasttype',
      E::ts('Organisation Type'),
      $this->getFATypes(),
      FALSE,
      array("class" => "huge")
    );

    $this->add(
      'text',
      'records_per_file',
      E::ts('Records per File'),
      TRUE
    );

    // add form elements
    // TODO: Make the form fields longer/larger
    $this->add(
      'text',
      'limit',
      E::ts('Request Limit'),
      TRUE
    );

    $this->add(
        'text',
        'soap_server_url',
      E::ts('SOAP-Server-URL'),
      array("class" => "huge"),
      FALSE
    );

    // TODO: create help message, or remove this part.
    // this should only be changed if you know what you are doing, and maybe not even then
    $this->add(
        'text',
        'soap_header_namespace',
      E::ts('SOAP-Header-Namespace'),
      array("class" => "huge"),
      FALSE
    );

    $this->add(
      'text',
      'soap_header_participantId',
      E::ts('SOAP Header participantId'),
      array("class" => "huge"),
      FALSE
    );

    $this->add(
      'text',
      'soap_header_userId',
      E::ts('SOAP Header UserId'),
      array("class" => "huge"),
      FALSE
    );

    $this->add(
      'text',
      'soap_header_cn',
      E::ts('SOAP Header cn'),
      array("class" => "huge"),
      FALSE
    );

    $this->add(
      'text',
      'soap_header_gvOuId',
      E::ts('SOAP Header gvOuId'),
      array("class" => "huge"),
      FALSE
    );

    $this->add(
      'text',
      'soap_header_gvGid',
      E::ts('SOAP Header gvGid'),
      array("class" => "huge"),
      FALSE
    );

    $this->add(
      'text',
      'soap_header_ou',
      E::ts('SOAP Header ou'),
      array("class" => "huge"),
      FALSE
    );


    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Save'),
        'isDefault' => TRUE,
      ),
    ));

    parent::buildQuickForm();
  }

  /**
   * set the default (=current) values in the form
   */
  public function setDefaultValues() {
    $config = CRM_Bpk_Config::singleton();
    return $config->getSettings();
  }

  /**
   * get the elements of the form
   * used as a filter for the values array from post Process
   * @return array
   */
  protected function getSettingsInForm() {
      return array(
        'fasttype',
        'records_per_file',
        'fastnr',
        'limit',
        'soap_server_url',
        'soap_header_namespace',
        'soap_header_participantId',
        'soap_header_userId',
        'soap_header_cn',
        'soap_header_gvOuId',
        'soap_header_gvGid',
        'soap_header_ou',
      );
  }

  /**
   * @todo: Document and move, e.g. to config
   */
  public static function getSoapHeaderSettingsParameters() {
    return array(
      'soap_server_url',
      'soap_header_namespace',
      'soap_header_participantId',
      'soap_header_userId',
      'soap_header_cn',
      'soap_header_gvOuId',
      'soap_header_gvGid',
      'soap_header_ou',
    );
  }

  /**
   * Process Form Data
   */
  public function postProcess() {
    $config = CRM_Bpk_Config::singleton();
    $values = $this->exportValues();

    $settings = $config->getSettings();
    $settings_in_form = $this->getSettingsInForm();
    foreach ($settings_in_form as $name) {
      if (isset($values[$name])) {
        $settings[$name] = $values[$name];
      }
    }
    $config->setSettings($settings);
    parent::postProcess();
  }

  /**
   * Get valid organisation types
   */
  protected function getFATypes() {
    return array(
      "KK" => "[KK] Einrichtung Kunst und Kultur (gem. § 4a Abs 2 Z 5 EStG)",
      "SO" => "[SO] Karitative Einrichtungen (gem § 4a Abs 2 Z3 lit a bis c EStG)",
      "FW" => "[FW] Wissenschaftseinrichtungen (gem. 4a Abs 2Z 1 EStG)",
      "NT" => "[NT] Naturschutz und Tierheime (gem § 4a Abs 2 Z 3 lit d und e EStG)",
      "SN" => "[SN] Sammeleinrichtungen Naturschutz (gem § 4a Abs 2 Z 3 lit d und e EStG)",
      "SG" => "[SG] gemeinnützige Stiftungen (§ 4b EStG 1988, hinsichtlich Spenden)",
      "UN" => "[UN] Universitätetn, Kunsthochschulen, Akademie der bildenden Künste (inkl. Fakultäten, Institute und besondere Einrichtungen, § 4a Abs 3 Z 1 EStG)",
      "MÖ" => "[MÖ] Museen von Körperschaften öffentlichen Rechts (§ 4a Abs 4 lit b EStG)",
      "MP" => "[MP] Privatmuseen mit überregionaler Bedeutung (§ 4a Abs 4 lit b EStG)",
      "FF" => "[FF] Freiwillige Feuerwehren ( § 4a Abs 6 EStG) und Landesfeuerwehrverbände (§ 4a Abs 6 EStG) KR Kirchen und Religionsgesellschaften mit verpflichtenden Beiträgen (§ 18 Abs 1 Z 5 EStG)",
      "PA" => "[PA] Pensionsversicherungsanstalten und Versorgungseinrichtungen (§ 18 Abs 1 Z 1a EStG)",
      "SE" => "[SE] Behindertensportdachverbände, Internationale Anti-Korruptions-Akademie, Diplomatische Akademie (§ 4a Abs 4 EStG)",
      "ZG" => "[ZG] gemeinnützige Stiftungen (§ 4b EStG, hinsichtlich Zuwendungen zur Vermögensausstattung) SV Spendensammeleinrichtungen karitativ (gem § 4a Abs 2 Z 3 lit a bis c EStG)",
      "ZI" => "[ZI] Zuwendungen an die Innovationsstiftung für Bildung (§ 4c EStG 1988)"
    );
  }
}
