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
    parent::__construct($params);
    // TODO: initialize SOAP controller
    $this->wsdl = "__DIR__/resources/soap/SZR.WSDL";
    $this->initializeSoapClient();
  }

  private function initializeSoapClient() {
    // needed? disable wsdl cache, check that, maybe make this configurable
    //                             depending on local config
    // ini_set("soap.wsdl_cache_enabled", "0");

    // FixMe: Need uri/location here??
    // TODO: Exception Handling
    $this->soapClient = new SoapClient($this->wsdl, $this->options);
    $this->createSoapHeader();
  }

  /**
   * create SOAP Header from $config data
   */
  private function createSoapHeader() {
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
  }

  /**
   * @param $contact
   */
  public function getBpkResult($contact) {
    // TODO: setup a single soap request
    if (!isset($contact['first_name'] || !isset($contact['last_name']) || !isset($contact['birth_date'])) {
      // TODO: Abort transaction --> show eror message
      return;
    }
    $soap_request_data = array(
      'PersonInfo' => array(
        'Person' => array(
          'Name' => array(
            'GivenName' = $contact['first_name'],
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
    // TODO: execute soap request now
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