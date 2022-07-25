#!/bin/sh

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}

# WordPress test setup script for Travis CI

export WP_CORE_DIR=/tmp/wordpress
export WP_TESTS_DIR=/tmp/wordpress-tests

# Init database
mysql -e 'CREATE DATABASE wordpress_test;' -uroot

# Grab specified version of WordPress from github
wget -nv -O /tmp/wordpress.tar.gz https://wordpress.org/latest.tar.gz
mkdir -p $WP_CORE_DIR
tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C $WP_CORE_DIR

# Grab testing framework and config file
mkdir -p $WP_TESTS_DIR
svn co --quiet https://develop.svn.wordpress.org/tags/6.0.1/tests/phpunit/includes/ $WP_TESTS_DIR/includes
svn co --quiet https://develop.svn.wordpress.org/tags/6.0.1/tests/phpunit/data/ $WP_TESTS_DIR/data
wget -nv -O $WP_TESTS_DIR/wp-tests-config.php https://develop.svn.wordpress.org/tags/6.0.1/wp-tests-config-sample.php
# remove all forward slashes in the end
WP_CORE_DIR=$(echo $WP_CORE_DIR | sed "s:/\+$::")
sed -i "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR"/wp-tests-config.php
sed -i "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR"/wp-tests-config.php
sed -i "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR"/wp-tests-config.php
sed -i "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR"/wp-tests-config.php
sed -i "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR"/wp-tests-config.php

if [[ "$TRAVISCI" == "psalm" ]] ; then
  # Put various components in proper folders
  plugin_slug=$(basename $(pwd))
  plugin_dir=$WP_CORE_DIR/wp-content/plugins/cleantalk-spam-protect
  cd ..
  mv $plugin_slug $plugin_dir
  cd $plugin_dir
fi
