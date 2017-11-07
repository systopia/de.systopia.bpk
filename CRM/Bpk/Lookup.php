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


class CRM_Bpk_Lookup {

  /**
   * Run a bPK lookup / store for eligible contacts
   *
   * @return array with results: ['success' => <count>, 'failed' => <count>]
   */
  public static function doLookup($params) {
    // TODO:
    return array(
      'success' => 0,
      'failed'  => 0);
  }
}
