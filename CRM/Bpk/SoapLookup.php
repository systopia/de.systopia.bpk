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
  private $soap_options;
  private $soapClient;
  private $local_cert;
  private $certificate_password;
  private $pw_file;

  /**
   * CRM_Bpk_SoapLookup constructor.
   *
   * @param $params
   */
  protected function __construct($params) {
    parent::__construct($params);

    $this->ns = dirname(__DIR__) . DIRECTORY_SEPARATOR . "../resources/soap/pvp1.xsd";
    $this->wsdl = dirname(__DIR__) . DIRECTORY_SEPARATOR . "../resources/soap/SZR.WSDL";
    $this->local_cert = dirname(__DIR__) . DIRECTORY_SEPARATOR . "../resources/certs/certificate.pem";
    $this->pw_file = dirname(__DIR__) . DIRECTORY_SEPARATOR . "../resources/certs/pw.txt";

    $file_content= explode("\n", file_get_contents($this->pw_file));
    $this->certificate_password = array_pop(array_reverse($file_content));

    $this->soap_options = array(
      "trace"         => 1,
      "exceptions"    => true,
      "local_cert"    => $this->local_cert,
      // "context"       => $context,
      "cache_wsdl" => WSDL_CACHE_NONE,
      "soap_version"  => SOAP_1_1,
      //      'use' => SOAP_LITERAL,
      'passphrase'    => $this->certificate_password,
      "location" => $this->location,
      "uri" => $this->uri,
    );
    // craete Soap-Client Object
    $this->soapClient = new SoapClient($this->wsdl, $this->soap_options);
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
   * @param $contact (DAO object with first_name, last_name and birth_date)
   *
   * @return \SoapVar
   */
  private function createSoapBody($contact) {

    $xml = new XMLWriter();
    $xml->openMemory();
    $name = 'p'; // "http://reference.e-government.gv.at/namespace/persondata/20020228#";
    $def_name = 'ns1'; // "urn:SZRServices";
    $xml->startElementNS($def_name, "GetBPK", NULL);
    $xml->writeAttributeNS ("xmlns", $name, NULL, "http://reference.e-government.gv.at/namespace/persondata/20020228#");
      $xml->startElementNS($def_name, "PersonInfo", NULL);
        $xml->startElementNS($def_name, "Person", NULL);
          $xml->startElementNS($name, "Name", NULL);
            $xml->startElementNS($name, "GivenName", NULL);
              $xml->Text($contact['first_name']);
            $xml->endElement();
            $xml->startElementNS($name, "FamilyName", NULL);
              $xml->Text($contact['last_name']);
            $xml->endElement();
          $xml->endElement();
          $xml->startElementNS($name, "DateOfBirth", NULL);
            $xml->Text($contact['birth_date']);
          $xml->endElement();
        $xml->endElement();
      $xml->endElement();
      $xml->startElementNS($def_name, "BereichsKennung", NULL);
        $xml->Text("urn:publicid:gv.at:wbpk+XZVR+961128260");
      $xml->endElement();
      $xml->startElementNS($def_name, "VKZ", NULL);
        $xml->Text("XZVR-961128260");
      $xml->endElement();
      $xml->startElementNS($def_name, "Target", NULL);
        $xml->startElementNS($def_name, "BereichsKennung", NULL);
          $xml->Text("urn:publicid:gv.at:cdid+SA");
        $xml->endElement();
        $xml->startElementNS($def_name, "VKZ", NULL);
          $xml->Text("BMF");
        $xml->endElement();
      $xml->endElement();
    $xml->endElement();

    $soap_body = new SoapVar($xml->outputMemory(), XSD_ANYXML);
    return $soap_body;
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

    // TODO: setup a single soap request
    if (!isset($contact->first_name) || !isset($contact->last_name) || !isset($contact->birth_date)) {
      throw new Exception("Necessary Attributes aren't in array. Aborting transaction", 1);
    }
    $soap_request_data = $this->createSoapBody($contact);
    try{
      //    $response = $this->soapClient->GetBPK($this->wsdl, $soap_request_data);
      $response = $this->soapClient->__soapCall("GetBPK", array($soap_request_data));
    } catch(SoapFault $fault) {
      // debug Code
//      print "\n\nRequest: \n";
//      print $this->soapClient->__getLastRequest();
//      print "\nResponse: \n";
//      print $this->soapClient->__getLastResponse();
//      print "\nFault Code: \n";
//      print $fault->faultcode;
//      print "\nMessage: \n";
//      print $fault->getMessage();
//      print "\nDetail: \n";
//      print $fault->getTraceAsString();
//      print "\n";
    }
    // TODO: parse response; return array ('contact_id] => array ( ))
  }

}