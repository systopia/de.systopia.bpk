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

// inject data dependency code
function inject_bpk_data_dependency() {
  var bpk_data_group        = "#custom-set-content-" + CRM.vars.bpk.bpk_group_id;
  var bpk_data_dependencies = ["#crm-contactname-content", "#crm-demographic-content"];

  // add data-dependent-fields dependencies
  for (var i = 0; i < bpk_data_dependencies.length; i++) {
    var current_value = cj(bpk_data_dependencies[i]).attr('data-dependent-fields');
    var fields = eval(current_value);
    if (fields) {
      if (fields.indexOf(bpk_data_group) == -1) {
        fields.push(bpk_data_group);
        cj(bpk_data_dependencies[i]).attr('data-dependent-fields', JSON.stringify(fields));
      }
    }
  }
}


// inject update button
cj(document).ready(function() {
  cj("div.crm-custom-set-block-" + CRM.vars.bpk.bpk_group_id)
    .prepend('<a href="' + CRM.vars.bpk.resolve_url + '" class="button" title="Resolve"><span><div class="icon refresh-icon ui-icon-refresh"></div></span></a>')

  // also, trigger data dependency function
  cj(document).bind("ajaxComplete", inject_bpk_data_dependency);
  inject_bpk_data_dependency();
});
