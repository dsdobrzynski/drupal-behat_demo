drupal_behat_demo
=======

requirements
------------
* [virtualBox](https://www.virtualbox.org/wiki/Downloads) >= 1.3
* [vagrant](http://downloads.vagrantup.com/) >= 1.2.0 (1.4.x recommended)

Legacy Notes:

	#1.  You no longer need any particular vagrant plugins. The box is already provisioned, so there's no need for a chef run.
				
	#2.  You no longer run this script with an enviroment instance option.


Building
--------

```bash
sudo cat cnf/hosts >> /etc/hosts 
composer install
```


* Run `vagrant up` to build the environment.
* ssh in with `vagrant ssh`
* Navigate to `/var/www/sites/PROJECT`.
* cp `env.json` from `/var/drupal/default/` to next to your `settings.php`.
* From inside your drupal root, run `../build/drush-build.sh local` and party.

Use
---

The build script `drush-build.sh` takes an optional argument which determines whether to install an existing site:

* install

That's it.

