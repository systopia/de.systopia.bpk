/*-------------------------------------------------------+
| SYSTOPIA bPK Extensio                                  |
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

// inject update button
cj(document).ready(function() {
  cj("div.crm-custom-set-block-" + CRM.vars.bpk.bpk_group_id)
    .prepend('<a href="' + CRM.vars.bpk.resolve_url + '" class="button" title="Resolve"><span><div class="icon refresh-icon ui-icon-refresh"></div></span></a>')
});
