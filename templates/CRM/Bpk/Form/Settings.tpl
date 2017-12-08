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
    <p>SOAP Configuration</p>
</div>

<div>
    <div class="crm-section">
        <div class="label">{$form.soap_header_namespace.label}</div>
        <div class="content">{$form.soap_header_namespace.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section">
        <div class="label">{$form.soap_header_userId.label}</div>
        <div class="content">{$form.soap_header_userId.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section">
        <div class="label">{$form.soap_header_cn.label}</div>
        <div class="content">{$form.soap_header_cn.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section">
        <div class="label">{$form.soap_header_gvOuId.label}</div>
        <div class="content">{$form.soap_header_gvOuId.html}</div>
        <div class="clear"></div>
    </div>
    <div class="crm-section">
        <div class="label">{$form.soap_header_gvGid.label}</div>
        <div class="content">{$form.soap_header_gvGid.html}</div>
        <div class="clear"></div>
    </div>
</div>


<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
