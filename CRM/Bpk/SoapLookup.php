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

  /**
   * CRM_Bpk_SoapLookup constructor.
   *
   * @param $params
   */
  protected function __construct($params) {
    parent::__construct($params);
    // TODO: initialize SOAP controller
    $this->wsdl = dirname(__DIR__) . DIRECTORY_SEPARATOR . "../resources/soap/SZR.WSDL";
    $this->initializeSoapClient();
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

    $xml = new XMLWriter();
    $xml->openMemory();
    $name = 'pvp'; //"http://egov.gv.at/pvp1.xsd";

    $xml->startElementNS('wsse', 'Security', 'http://schemas.xmlsoap.org/ws/2002/04/secext');
      $xml->startElementNS($name, "pvpToken", 'http://egov.gv.at/pvp1.xsd');
      $xml->writeAttribute('version', "1.8");
        $xml->startElementNS($name, "authenticate", NULL);
          $xml->startElementNS($name, "participantId", NULL);
            $xml->Text($soapHeaderParameters['soap_header_participantId']);
          $xml->endElement();
          $xml->startElementNS($name, "userPrincipal", NULL);
            $xml->startElementNS($name, "userId", NULL);
              $xml->Text($soapHeaderParameters['soap_header_userId']);
            $xml->endElement();
            $xml->startElementNS($name, "cn", NULL);
              $xml->Text($soapHeaderParameters['soap_header_cn']);
            $xml->endElement();
            $xml->startElementNS($name, "gvOuId", NULL);
              $xml->Text($soapHeaderParameters['soap_header_gvOuId']);
            $xml->endElement();
            $xml->startElementNS($name, "ou", NULL);
              $xml->Text($soapHeaderParameters['soap_header_ou']);
            $xml->endElement();
            $xml->startElementNS($name, "gvSecClass", NULL);
              $xml->Text("2");
            $xml->endElement();
            $xml->startElementNS($name, "gvGid", NULL);
              $xml->Text($soapHeaderParameters['soap_header_gvGid']);
            $xml->endElement();
          $xml->endElement();
        $xml->endElement();
        $xml->startElementNS($name, "authorize", NULL);
          $xml->startElementNS($name, "role", NULL);
          $xml->writeAttribute('value', "szr-bpk-abfrage");
          $xml->endElement();
        $xml->endElement();
      $xml->endElement();
    $xml->endElement();

    $headerBody = new SoapVar($xml->outputMemory(), XSD_ANYXML);
    $soap_header = new SOAPHeader($this->ns, 'Header', $headerBody);
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
   * Perform the actual bpk lookup for the contact
   *
   * @param $contact DAO object with first_name, last_name, birth_date
   *
   * @return array with the following parameters:
   *               bpk_extern       bPK            (empty string if not resolved)
   *               vbpk             vbPK           (empty string if not resolved)
   *               bpk_status       status         (OptionGroup bpk_status)
   *               bpk_error_code   error code     (empty string if no error)
   *               bpk_error_note   error message  (empty string if no error)
   */
  public function getBpkResult($contact) {
    // TODO: use BAO
    error_log("querying for contact " . json_encode($contact));
    // TODO: setup a single soap request
    if (!isset($contact->first_name) || !isset($contact->last_name) || !isset($contact->birth_date)) {
      throw new Exception("Necessary Attributes aren't in array. Aborting transaction", 1);
    }

    $soap_request_data = array(
      'PersonInfo' => array(
        'Person' => array(
          'Name' => array(
            'GivenName' => $contact->first_name,
            'FamilyName' => $contact->last_name,
          ),
          // TODO: FORMAT!!
          'DateOfBirth' => $contact->birth_date,
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