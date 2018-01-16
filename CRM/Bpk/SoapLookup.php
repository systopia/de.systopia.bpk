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
 * Class CRM_Bpk_SoapLookup extends Lookup Class
 *
 * Implements a SOAP Request to the API, parses the request and then
 * add it to the local DB via the API
 */
class CRM_Bpk_SoapLookup extends CRM_Bpk_Lookup {

  private $wsdl;
  private $ns;
  private $location;
  private $options;
  private $soapClient;

  // TODO: debug last request/response
//REQUEST :
//" . htmlspecialchars($client->__getLastRequest()) . "
//");
//   //echo("
//RESPONSE:
//" .htmlspecialchars($client->__getLastResponse()) . "
//");

  /**
   * CRM_Bpk_SoapLookup constructor.
   *
   * @param $params
   */
  protected function __construct($params) {
    if (!isset($params['contact_id'])) {
      // Shouldn't be possible
      error_log("Leaving SOAPLookup constructor; contact_id is empty in params");
      return NULL;
    }
    $contact_details = $this->getPersonData($params['contact_id']);
    parent::__construct($contact_details);
    // TODO: initialize SOAP controller
    $this->wsdl = dirname(__DIR__) . DIRECTORY_SEPARATOR . "../resources/soap/SZR.WSDL";
    $this->initializeSoapClient();
  }

  /**
   * @param $contact_id
   * @param array $options
   *
   * @throws \CiviCRM_API3_Exception
   * @throws \Exception
   *
   * @return contact parameters; min first/last_name, birthdate
   */
  // FixMe: default value for contact_id is for testing purposes
  private function getPersonData($contact_id = '2', $options = array()) {
    $result = civicrm_api3('Contact', 'getsingle', array(
      'sequential' => 1,
      'id' => $contact_id,
    ));
    if (isset($result['is_error'])) {
      throw new Exception("Couldn't find Contact with id {$contact_id}. Aborting lookup");
    }
    $bpk_parameters = array(
      'first_name'  => $result['first_name'],
      'last_name'   => $result['last_name'],
      'birth_date'  => $result['birth_date'],
    );
    foreach ($options as $key => $opt) {
      if (isset($result[$opt])) {
        $bpk_parameters[$opt] = $result[$opt];
      }
    }
    return $bpk_parameters;
  }

  private function initializeSoapClient() {
    // needed? disable wsdl cache, check that, maybe make this configurable
    //                             depending on local config
    // ini_set("soap.wsdl_cache_enabled", "0");

    CRM_Core_Error::debug("INITIALIZE SOAP Client: " . $this->wsdl);
    CRM_Core_Error::debug("SOAP Client Options: " . json_encode($this->options));


    // FixMe: Need uri/location here??
    // TODO: Exception Handling
//    $this->soapClient = new SoapClient($this->wsdl, $this->options);
    $this->soapClient = new SoapClient($this->wsdl);
    $this->createSoapHeader();
  }

  /**
   * create SOAP Header from $config data
   */
  private function createSoapHeader() {
    error_log("in createSoapHeader");
    $config = CRM_Bpk_Config::singleton();
    $soapHeaderParameters = $config->getSoapHeaderSettings();

    $headerBody = array(
      'authenticate' => array(
        'participantId'     => $soapHeaderParameters['soap_header_participantId'],
        'pvpPrincipalType'  => array(
          'userId'      => $soapHeaderParameters['soap_header_userId'],
          'cn'          => $soapHeaderParameters['soap_header_cn'],
          'gvOuId'      => $soapHeaderParameters['soap_header_gvOuId'],
          'gvGid'       => $soapHeaderParameters['soap_header_gvGid'],
          'ou'          => $soapHeaderParameters['soap_header_ou'],
          'gvSecClass'  => '2',  // TODO ?? copied from example
        )
      ),
      'authorize' => array(
        'role' => array('value' => 'szr-bpk-abfrage'),
      )
    );

    $soap_header = new SOAPHeader($this->ns, 'requestHeader', $headerBody);
    $this->soapClient->__setSoapHeaders($soap_header);
  }

  /**
   * Debug function to print available soap functions to error log
   */
  public function debugSOAPFunctions() {
    $functions = $this->soapClient->__getFunctions();
    // TODO: Maybe don't do this in error log, might be cut off --> Move to CiviCRM Log
    error_log("Debug, soap functions: " . json_encode($functions));
    CRM_Core_Error::debug("DEBUG: " . json_encode($functions));
  }

  /**
   * @param $contact array('first_name', 'last_name', 'birth_date')
   */
  public function getBpkResult($contact) {
    error_log("querrying for contact " . json_encode($contact));
    // TODO: setup a single soap request
    if (!isset($contact['first_name']) || !isset($contact['last_name']) || !isset($contact['birth_date'])) {
      CRM_Core_Error::debug("Necessary Attributes aren't in array. Aborting transaction");
      return;
    }
    $soap_request_data = array(
      'PersonInfo' => array(
        'Person' => array(
          'Name' => array(
            'GivenName' => $contact['first_name'],
            'FamilyName' => $contact['last_name'],
          ),
          // TODO: consider bday format
          'DateOfBirth' => $contact['birth_date'],
        )
      ),
      // TODO --> get values from config class here
      'Bereichskennung' => 'woot??',
      'VKZ' => 'vkz',
      'target' => array(
        'BereichsKennung' => 'urn:publicid:gv.at:cdid+SA',
        'VKZ' => 'BMF',
      )
    );
    error_log("Executing Querry");
    // TODO: execute soap request now
//    GetBPK   <-- function
    // get function form wqsdl, and call via soap object
//    try {
      $this->soapClient->GetBPK($this->wsdl, $soap_request_data);
//    } catch (Exception $e) {
      CRM_Core_Error::debug(json_encode(htmlspecialchars($e)));
      error_log("Request: " . json_encode($this->soapClient->__getLastRequest()));
      error_log("Response: " . json_encode($this->soapClient->__getLastResponse()));
//    }
    error_log("finished Querry");
  }

  /**
   * creates a SOAP request
   */
  public function createSoapRequest() {

  }

  /**
   * @param $request
   *
   * @return result request
   * executes the given request, return the result
   */
  public function executeSoapRequest($request) {

  }
}