# Run from root of CSWeb installation
php composer.phar config --unset platform.php
php composer.phar config repositories.csfilesystem "{\"type\":\"path\",\"url\":\"./CSFilesystem\"}"
mkdir CSFilesystem
cd CSFilesystem
git clone git@github.com:blueraster/csfilesystem.git .
cd ..
composer require blueraster/csfilesystem
