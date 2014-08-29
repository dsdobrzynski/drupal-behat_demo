Vagrant.configure("2") do |config|
  # Variables to Change
  project = 'behat-demo'
  box_ip = '10.33.22.42'
  box_memory = 2048
  # End Variables To Change

  path = "/var/www/sites/#{project}.dev"
  config.vm.box = "promet_wheezy"
  config.vm.box_url = "https://s3.amazonaws.com/promet_debian_boxes/wheezy_virtualbox.box"

  config.vm.network :private_network, ip: "#{box_ip}"

  config.vm.provider :virtualbox do |vb|
    vb.customize ["modifyvm", :id, "--memory", box_memory]
  end

  config.vm.hostname = "#{project}"
  config.vm.synced_folder '.', '/vagrant', :enabled => false
  config.vm.synced_folder '.', path, :nfs => true
  config.vm.provision :shell, inline: 'curl -sS https://getcomposer.org/installer | php && cp composer.phar /usr/local/bin/composer && rm composer.phar'
  config.vm.provision :shell, inline: 'apt-get update -y'
  config.vm.provision :shell, inline: 'apt-get install -q -y git-core'
  config.vm.provision :shell, path:   'build/vagrant.sh', args: path
end
