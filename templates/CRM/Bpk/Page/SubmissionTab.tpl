{*-------------------------------------------------------+
| SYSTOPIA bPK Extension                                 |
| Copyright (C) 2018 SYSTOPIA                            |
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

{if $submissions}
<h3>{ts domain="de.systopia.bpk"}Recorded Submissions{/ts}</h3>
<table>
  <thead>
    <tr class="columnheader">
      <td>{ts domain="de.systopia.bpk"}Year{/ts}</td>
      <td>{ts domain="de.systopia.bpk"}Date{/ts}</td>
      <td>{ts domain="de.systopia.bpk"}Reference{/ts}</td>
      <td>{ts domain="de.systopia.bpk"}Type{/ts}</td>
      <td>{ts domain="de.systopia.bpk"}Submitted Amount{/ts}</td>
      <td>{ts domain="de.systopia.bpk"}Current Amount{/ts}</td>
    </tr>
  </thead>

  <tbody>
    {foreach from=$submissions item=submission}
    <tr class="bmfsa-record {$submission.class} {cycle values="odd-row,even-row"}"">
      <td>{$submission.year}</td>
      <td>{$submission.date|crmDate}</td>
      <td>{$submission.reference}</td>
      <td>{$submission.type}</td>
      <td>{$submission.amount|crmMoney}</td>
      <td>{$submission.current|crmMoney}</td>
    </tr>
    {/foreach}
  </tbody>
</table>

{else}

<div id="help">
{ts domain="de.systopia.bpk"}This contact no recorded submissions to the BMF.{/ts}
</div>

{/if}