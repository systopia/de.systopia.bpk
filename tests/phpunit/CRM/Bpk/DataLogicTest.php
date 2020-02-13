<?php

use CRM_Bpk_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Test BPK Data Logic
 *
 * @group headless
 */
class CRM_Bpk_DataLogicTest extends \PHPUnit_Framework_TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {
  use \Civi\Test\Api3TestTrait;

  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp() {
    parent::setUp();
  }

  public function tearDown() {
    parent::tearDown();
  }

  /**
   * Test whether BPKs get reset when a field that's relevant for BPK is updated
   */
  public function testScheduledResetIsExecuted() {
    // test twice to ensure no state is preserved between API calls
    for ($i = 0; $i < 2; $i++) {
      // create test contact
      $contact_id = $this->callApiSuccess('Contact', 'create', [
        'contact_type' => 'Individual',
        'first_name' => 'Bpk',
        'last_name' => 'Contact',
      ])['id'];

      $this->addDummyBpk($contact_id);

      // update a field that should cause bpk reset
      $this->callApiSuccess('Contact', 'create', [
        'id' => $contact_id,
        'first_name' => 'BpkUpd',
      ]);

      $expected = [
        `CRM_Bpk_CustomData::getCustomFieldKey('bpk', 'bpk_status') => BPK_STATUS_UNKNOWN,`
        CRM_Bpk_CustomData::getCustomFieldKey('bpk', 'bpk_extern') => '',
        CRM_Bpk_CustomData::getCustomFieldKey('bpk', 'vbpk') => '',
        CRM_Bpk_CustomData::getCustomFieldKey('bpk', 'bpk_error_code') => '',
        CRM_Bpk_CustomData::getCustomFieldKey('bpk', 'bpk_error_note') => '',
      ];
      // get the (hopefully) updated BPK fields
      $actual = $this->callApiSuccess('Contact', 'get', [
        'id' => $contact_id,
        'return' => array_keys($expected),
      ]);
      $actual = reset($actual['values']);
      // verify that BPK fields were set to the default value
      foreach ($expected as $key => $value) {
        $this->assertEquals($value, $actual[$key], "BPK field {$key} should be reset to {$value}");
      }
    }
  }

  /**
   * Add dummy BPK row. Go through SQL to avoid hooks
   *
   * @param $contact_id
   */
  private function addDummyBpk($contact_id) {
    CRM_Core_DAO::executeQuery("INSERT INTO civicrm_value_bpk
                                         (entity_id, bpk_extern, vbpk, status, error_code, error_note)
                                       VALUES
                                         ({$contact_id}, '1234', '1234', " . BPK_STATUS_RESOLVED . ", 0, 'error')");
  }

}
