<?php
/**
 * @file
 * behat_feature.features.inc
 */

/**
 * Implements hook_ctools_plugin_api().
 */
function behat_feature_ctools_plugin_api($module = NULL, $api = NULL) {
  if ($module == "strongarm" && $api == "strongarm") {
    return array("version" => "1");
  }
}

/**
 * Implements hook_node_info().
 */
function behat_feature_node_info() {
  $items = array(
    'behat_demo' => array(
      'name' => t('Behat Demo'),
      'base' => 'node_content',
      'description' => t('Custom content for demo of Drupal development using Behat.'),
      'has_title' => '1',
      'title_label' => t('Title'),
      'help' => '',
    ),
  );
  drupal_alter('node_info', $items);
  return $items;
}
