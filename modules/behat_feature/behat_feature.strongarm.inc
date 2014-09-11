<?php
/**
 * @file
 * behat_feature.strongarm.inc
 */

/**
 * Implements hook_strongarm().
 */
function behat_feature_strongarm() {
  $export = array();

  $strongarm = new stdClass();
  $strongarm->disabled = FALSE; /* Edit this to true to make a default strongarm disabled initially */
  $strongarm->api_version = 1;
  $strongarm->name = 'node_options_behat_demo';
  $strongarm->value = array(
    0 => 'status',
    1 => 'promote',
  );
  $export['node_options_behat_demo'] = $strongarm;

  $strongarm = new stdClass();
  $strongarm->disabled = FALSE; /* Edit this to true to make a default strongarm disabled initially */
  $strongarm->api_version = 1;
  $strongarm->name = 'node_preview_behat_demo';
  $strongarm->value = '1';
  $export['node_preview_behat_demo'] = $strongarm;

  $strongarm = new stdClass();
  $strongarm->disabled = FALSE; /* Edit this to true to make a default strongarm disabled initially */
  $strongarm->api_version = 1;
  $strongarm->name = 'node_submitted_behat_demo';
  $strongarm->value = 1;
  $export['node_submitted_behat_demo'] = $strongarm;

  return $export;
}
