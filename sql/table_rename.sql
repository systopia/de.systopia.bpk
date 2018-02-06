RENAME TABLE civicrm_values_bpk TO civicrm_value_bpk;
UPDATE civicrm_custom_group SET table_name = 'civicrm_value_bpk' WHERE table_name = 'civicrm_values_bpk';