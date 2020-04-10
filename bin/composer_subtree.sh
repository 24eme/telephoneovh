#!/bin/bash

# Installation de composer
echo "Installation de composer"
echo ""
cd bin
EXPECTED_CHECKSUM="$(wget -q -O - https://composer.github.io/installer.sig)"
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]
then
    >&2 echo 'ERROR: Invalid installer checksum'
    rm composer-setup.php
    exit 1
fi

php composer-setup.php --quiet
RESULT=$?
rm composer-setup.php
cd - > /dev/null

# Ajout des dépendances via les subtree de git
echo ""
echo "Installation des dépendances via git subtree"
echo ""
cp bin/composer.json composer.json
mkdir vendor 2> /dev/null
php bin/composer.phar install --dry-run --no-cache --no-suggest 2>&1 | grep "\- Installing" | cut -d " " -f 5,6 | sed 's/[()]//g' | while read package_version;
do
    package=$(echo -n $package_version | cut -d " " -f 1)
    version=$(echo -n $package_version | cut -d " " -f 2)
    URLGIT=$(curl -s "https://packagist.org/packages/$package.json" | jq '.package.repository' | sed 's/"//g')
    git fetch $URLGIT $version
    git subtree add --prefix "vendor/$package" FETCH_HEAD --squash --message="Include $URLGIT in folder /vendor/$package at version $version"
done

# Génération de l'autoload
echo ""
echo "Génération de l'autoload"
echo ""

php bin/composer.phar install --prefer-source 2> /dev/null
git add vendor
git commit -m "Autoload generation from composer"
rm composer.json
rm composer.lock
