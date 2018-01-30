<?php
/**
 * Created by PhpStorm.
 * User: phil
 * Date: 09.01.18
 * Time: 13:53
 */


class mySOAPClient extends SoapClient {

  public function __doRequest($request, $location, $action, $version, $one_way = 0) {
    echo "Request:\n" . $request;
    echo "\nLocation:\n" . $location;
    echo "\nAction:\n" . $action;
    echo "\nVersion:\n" . $version;
    echo "\nOneWay: " . $one_way;
    return parent::__doRequest($request, $location, $action, $version, $one_way); // TODO: Change the autogenerated stub
  }
}

class SoapTester {

  private $wsdl;
  private $ns;
  private $local_cert;
  private $soapClient;
  private $location;
  private $uri;
  private $certificate_password;

  private $soap_options;

  public function __construct() {
    $this->wsdl = "SZR.WSDL";
    $this->ns = "http://egov.gv.at/pvp1.xsd";
    $this->certificate_password = "PASSWORD";

    // pro
    //    $this->location = "https://pvawp.bmi.gv.at/bmi.gv.at/soap/SZ2Services/services/SZR";
    // dev
    //    $this->location = "https://pvawp.bmi.gv.at/bmi.gv.at/soap/SZ2Services-T/services/SZR";

    // see https://php.net/manual/de/soapclient.soapclient.php#120888
    // $context = stream_context_create([
    //   'ssl' => [
    //     'local_cert' => 'certs/N-000-318-p-331-2017-05-16.p12',
    //     'local_pk'   => 'TODO'
    //     ]
    //   ]);
    $this->local_cert = "certs/greenpeace_bpk.pem";
//    $this->local_cert = "certs/N-000-318-p-331-2017-05-16.p12";

    // Test Environment
    $this->location = "https://pvawp.bmi.gv.at/at.gv.bmi.szrsrv-b/services/SZR";
    // produktiv Environment
//     $this->location = "https://pvawp.bmi.gv.at/bmi.gv.at/soap/SZ2Services/services/SZR";

    // previous
    //     $this->location = "https://pvawp.bmi.gv.at/bmi.gv.at/soap/SZ2Services-T/services/SZR";

    $this->uri = "urn:SZRServices";

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
    $this->initializeSoapClient();
  }

  private function initializeSoapClient() {

    // echo "$this->wsdl\n";
    $this->soapClient = new mySOAPClient(NULL, $this->soap_options);
    // $this->soapClient->__setLocation($this->location); // see https://stackoverflow.com/questions/28918666/unable-to-parse-url-exception-after-soap-request
    $this->createSoapHeader();
  }

  // TODO: Fix
  private function createSoapHeader() {
    $xml = new XMLWriter();
    $xml->openMemory();
    $name = 'pvp'; //"http://egov.gv.at/pvp1.xsd";

    $xml->startElementNS('wsse', 'Security', 'http://schemas.xmlsoap.org/ws/2002/04/secext');
      $xml->startElementNS($name, "pvpToken", 'http://egov.gv.at/pvp1.xsd');
      $xml->writeAttribute('version', "1.8");
        $xml->startElementNS($name, "authenticate", NULL);
          $xml->startElementNS($name, "participantId", NULL);
            $xml->Text("AT:VKZ:XZVR-961128260");
          $xml->endElement();
          $xml->startElementNS($name, "userPrincipal", NULL);
            $xml->startElementNS($name, "userId", NULL);
              $xml->Text("marco.haefner@greenpeace.org");
            $xml->endElement();
            $xml->startElementNS($name, "cn", NULL);
              $xml->Text("Greenpeace");
            $xml->endElement();
            $xml->startElementNS($name, "gvOuId", NULL);
              $xml->Text("Greenpeace EDV Abteilung");
            $xml->endElement();
            $xml->startElementNS($name, "ou", NULL);
              $xml->Text("EDV Abteilung");
            $xml->endElement();
            $xml->startElementNS($name, "gvSecClass", NULL);
              $xml->Text("2");
            $xml->endElement();
            $xml->startElementNS($name, "gvGid", NULL);
              $xml->Text("Greenpeace");
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
   * @param $contact
   *
   * @return \SoapVar
   */
  private function createSoapBody($contact) {

    $xml = new XMLWriter();
    $xml->openMemory();
    $name = 'p'; // "http://reference.e-government.gv.at/namespace/persondata/20020228#";
    $def_name = 'ns1'; // "urn:SZRServices";

//    $xml->startElementNS($def_name, "GetBPK", NULL);
      $xml->startElementNS($def_name, "PersonInfo", NULL);
        $xml->startElementNS($def_name, "Person", NULL);
          $xml->startElementNS($name, "Name", "http://reference.e-government.gv.at/namespace/persondata/20020228#");
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
//    $xml->endElement();

    $soap_body = new SoapVar($xml->outputMemory(), XSD_ANYXML);
    return $soap_body;
  }


  public function getBpkResult($contact) {
    echo "querying for contact " . json_encode($contact) . "\n";

//    $soap_request_data = array(
//      'PersonInfo' => array(
//        'Person' => array(
//          'Name' => array(
//            'GivenName' => $contact['first_name'],
//            'FamilyName' => $contact['last_name'],
//          ),
//          'DateOfBirth' => $contact['birth_date'],
//        )
//      ),
//      'BereichsKennung' => 'urn:publicid:gv.at:wbpk+XZVR-432857691',
//      'VKZ' => 'vkz',
//      'target' => array(
//        'BereichsKennung' => 'urn:publicid:gv.at:cdid+SA',
//        'VKZ' => 'XZVR-432857691MF',
//      )
//    );
    $soap_request_data = $this->createSoapBody($contact);
  try{
//    $response = $this->soapClient->GetBPK($this->wsdl, $soap_request_data);
    $response = $this->soapClient->__soapCall("GetBPK", array($soap_request_data));
    print "AFTER RESPONSE";

  } catch(SoapFault $fault) {
    print "IN EXCEPTION";
    print "\n\nRequest: \n";
    print $this->soapClient->__getLastRequest();
    print "\nResponse: \n";
    print $this->soapClient->__getLastResponse();
    print "\nFault Code: \n";
    print $fault->faultcode;
    print "\nMessage: \n";
    print $fault->getMessage();
    print "\nDetail: \n";
    print $fault->getTraceAsString();
    print "\n";
  }


    //    echo json_encode($response) . "\n";
  }
}

$contact_data = array(
  'first_name' => 'XXXTest',
  'last_name'  => 'XXXSZR',
  'birth_date' => '1960-04-04',
);
$myTester = new SoapTester();

echo "Starting soap call\n";
$myTester->getBpkResult($contact_data);
