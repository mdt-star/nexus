<?php

/**
 * Exception messages English mapping
 *
 * Loaded via __('nexus::exceptions.xxx')
 */
return [
    'invalid_subject' => 'Invalid action subject, unable to retrieve permission information',
    'subject_not_has_permissions' => 'Action subject error, unable to retrieve permission information',
    'no_read_permission' => 'Permission denied, unable to view this content',
    'no_write_permission' => 'Permission denied, unable to perform write operation',
    'no_delete_permission' => 'Permission denied, unable to perform delete operation',
    'scope_not_found' => 'Data scope strategy configuration error, unable to execute query',
    'scope_model_not_in_whitelist' => 'Current model is not in the data scope strategy whitelist',
    'scope_class_not_found' => 'Data scope strategy class not found, please contact administrator',
    'scope_execution_failed' => 'Data scope strategy execution failed, unable to complete query',
    'tag_not_found' => 'Unable to determine permission tag',
    'subject_not_has_permission_interface' => 'Subject does not implement permission interface',
    'no_tag_permission' => 'Permission denied, missing :tag permission',
];
