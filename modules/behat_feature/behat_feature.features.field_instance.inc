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

  // Exported field_instance: 'node-behat_demo-field_my_image_field'
  $field_instances['node-behat_demo-field_my_image_field'] = array(
    'bundle' => 'behat_demo',
    'deleted' => 0,
    'description' => 'This image is always optional, but should have a minimum resolution of 300 x 300 pixels.',
    'display' => array(
      'default' => array(
        'label' => 'above',
        'module' => 'image',
        'settings' => array(
          'image_link' => '',
          'image_style' => '',
        ),
        'type' => 'image',
        'weight' => 2,
      ),
      'teaser' => array(
        'label' => 'above',
        'settings' => array(),
        'type' => 'hidden',
        'weight' => 0,
      ),
    ),
    'entity_type' => 'node',
    'field_name' => 'field_my_image_field',
    'label' => 'My Image Field',
    'required' => 0,
    'settings' => array(
      'alt_field' => 0,
      'default_image' => 0,
      'file_directory' => '',
      'file_extensions' => 'png gif jpg jpeg',
      'max_filesize' => '',
      'max_resolution' => '',
      'min_resolution' => '300x300',
      'title_field' => 0,
      'user_register_form' => FALSE,
    ),
    'widget' => array(
      'active' => 1,
      'module' => 'image',
      'settings' => array(
        'preview_image_style' => 'thumbnail',
        'progress_indicator' => 'throbber',
      ),
      'type' => 'image_image',
      'weight' => -2,
    ),
  );

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
    'label' => 'My Text Field',
    'required' => 1,
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
  t('My Image Field');
  t('My Text Field');
  t('This field should always be required.');
  t('This image is always optional, but should have a minimum resolution of 300 x 300 pixels.');

  return $field_instances;
}
