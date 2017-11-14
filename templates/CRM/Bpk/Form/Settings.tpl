{*-------------------------------------------------------+
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
+-------------------------------------------------------*}

<div class="crm-section bpk">
    <div class="crm-section">
        <div class="label">{$form.limit.label}</div>
        <div class="content">{$form.limit.html}</div>
    <div class="clear"></div>
    </div>

    <div class="crm-section">
        <div class="label">{$form.key.label}</div>
        <div class="content">{$form.key.html}</div>
        <div class="clear"></div>
    </div>
</div>

<hr>

<div>
    <div class="crm-section">
        <div class="label">{$form.SOAPNamespace.label}</div>
        <div class="content">{$form.SOAPNamespace.html}</div>
        <div class="clear"></div>
    </div>
</div>


<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
