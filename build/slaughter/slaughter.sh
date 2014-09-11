#! /usr/bin/env bash
echo "Installing database.";
$drush si -y --account-pass='drupaladm1n' --site-name='Behat Demo'
echo "Ensure that public file system is writable"
chmod 777 $top_path/www/sites/default/files
echo "Enabling modules.";
$drush en drop_ship -y -v
$drush en $DROPSHIP_SEEDS -y -v
echo "Clearing caches.";
$drush cc all -y
