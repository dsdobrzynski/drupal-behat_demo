<?php
/**
 * @file
 * behat_feature.features.field_instance.inc
 */

/**
 * Implements hook_field_default_field_instances().
 */
function behat_feature_field_default_field_instances() {
  $field_instances = array();

  // Exported field_instance: 'node-behat_demo-field_my_text_field'
  $field_instances['node-behat_demo-field_my_text_field'] = array(
    'bundle' => 'behat_demo',
    'default_value' => NULL,
    'deleted' => 0,
    'description' => 'This field should always be required.',
    'display' => array(
      'default' => array(
        'label' => 'above',
        'module' => 'text',
        'settings' => array(),
        'type' => 'text_default',
        'weight' => 1,
      ),
      'teaser' => array(
        'label' => 'above',
        'settings' => array(),
        'type' => 'hidden',
        'weight' => 0,
      ),
    ),
    'entity_type' => 'node',
    'field_name' => 'field_my_text_field',
    'label' => 'My Required Text',
    'required' => 0,
    'settings' => array(
      'text_processing' => 0,
      'user_register_form' => FALSE,
    ),
    'widget' => array(
      'active' => 1,
      'module' => 'text',
      'settings' => array(
        'size' => 60,
      ),
      'type' => 'text_textfield',
      'weight' => -3,
    ),
  );

  // Translatables
  // Included for use with string extractors like potx.
  t('My Required Text');
  t('This field should always be required.');

  return $field_instances;
}
