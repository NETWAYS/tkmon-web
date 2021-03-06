
# TKMON-WEB installation guide

TKMON-WEB is a small an simple interface to ICINGA monitoring. New users and also users with no linux knowledge should be able to use icinga for their medium sized environments.

This interface is open source software ([GPLv3](http://www.gnu.org/licenses/gpl.html)), you can install where ever you want. It’s also available as a appliance on Thomas-Krenn Low Energy Server ([LES](http://www.thomas-krenn.com/en/products/server-systems/thomas-krenn-low-energy-server.html)).

TKMON-WEB is a consortium project of [NETWAYS GmbH](http://www.netways.de) and [Thomas-Krenn.AG](http://www.thomas-krenn.com).

## Installation

### Prerequisites

Base system was a **Ubuntu LTS 12.04 Precise**

To get the system running you’ll need to have some mandatory things:

*   PHP > 5.3 (Web and CLI)

*   Extensions installed: pdo-sqlite

*   Apache2

To get a working base monitoring you’ll also need:

*   tkalert

*   IPMI checkplugin (nagios-plugins-contrib with ipmi perl version)

### Ubuntu install

#### Package install

Please install the following packages to run on Ubuntu Server

```
# apt-get install apache2 php5 php5-cli php5-sqlite php5-curl php5-xcache \
    libapache2-mod-php5 postfix sqlite3 zip unzip
```

#### Testinstall IPMI Plugin

To get IPMI checks working, please install new IPMI perl version of the debien testing isntall

```
# cd /root
# wget "http://ftp.us.debian.org/debian/pool/main/n/nagios-plugins-contrib/nagios-plugins-contrib_4.20120702_amd64.deb" \
  -O nagios-plugins-contrib_4.20120702_amd64.deb
# dpkg -i nagios-plugins-contrib_4.20120702_amd64.deb
# apt-get install libipc-run-perl freeipmi-tools
```

#### Testinstall TKALERT

Checkout the latest git release and install with python setup tools:

```
# cd /usr/local/src
# git clone git://git.netways.org/thomas-krenn/tkalert.git
# cd tkalert
# python setup.py install
```

For more information see tkalert/README.md

#### Copy updater script to its location

TKMON uses an upgrade script to install updates asynchronously. Sudoers and config needs the script into /usr/local/sbin/async_update.py

```
# cd ./bin
# cp async_update.py /usr/local/sbin
# chmod 750 /usr/local/sbin/async_update.py
```

#### PHP configuration

Enable XCache variable cache.

A cache is needed to cache the service catalogues. This catalogues are loaded once at first start. After that, data resides in apache memory.

```
# grep var_ /etc/php5/conf.d/xcache.ini
xcache.var_size  =            16M
xcache.var_count =             1
xcache.var_slots =            8K
xcache.var_ttl   =             0
xcache.var_maxttl   =          0
xcache.var_gc_interval =     300
```

Important is to set xcache.var_size to 16M. Restart apache after that.

#### Copy sources

Copy the sources to a safe place, e.g. /opt/tkmon-web or /usr/local/tkmon-web

```
{core.var_dir}/cache
{core.var_dir}/db
```

The webserver needs to write data into theese directories. But per default this directories are set to temp location.

#### Apache configuration

*   Copy configuration to **conf.d** directory

*   Enable rewrite module

*   Restart you apache instance

```
# cp etc/apache2/conf.d/tkmon.conf /etc/apache2/conf.d/tkmon.conf
# a2enmod rewrite
# /etc/init.d/apache2 restart
```

Please have a look into **/etc/apache2/conf.d/tkmon.conf** and alter wrong paths to match system path settings.

#### Test install

This packages are only needed if you want to run unit tests

```
# apt-get install php-pear
# pear config-set auto_discover 1
# pear install pear.phpunit.de/PHPUnit
```

#### Run tests

To run all possible unit tests type

```
# phpunit
```

To run integration tests (where icinga and other resource files are installed) type

```
# phpunit  --group integration
```

#### Install PHP Composer

In this project some certain libraries are used to build the functionallity. To resolved the dependencies you’ll have to use php composer to fix that.

More information can be found [on its website](http://getcomposer.org/). But I’ll show some basic steps to do this.

##### Local install

This means you do install the composer in the project directory to keep your system clean from unpackaged third-party tools:

```
# cd tk-mon/
# $ curl -s https://getcomposer.org/installer | php
  #!/usr/bin/env php
  All settings correct for using Composer
  Downloading...
  Composer successfully installed to: /data/users/mhein/workspaces/tk-mon/tk-mon/composer.phar
  Use it: php composer.phar
# php composer.phar install
Loading composer repositories with package information
Installing dependencies
  - Installing twig/twig (v1.11.1)
    Loading from cache

  - Installing pimple/pimple (dev-master v1.0.1)
    Cloning v1.0.1

Writing lock file
Generating autoload files
```

That’s all. The software is ready to run. Please check if you have a directory **lib/tkmon/vendor** in your source tree.

#### Sudoers file

Tkmon runs a couple of commands with root privileges you need to allow for the web user.

##### Copy sudoers

The mode is 440 or 400 is needed. Other sudo will not work.

```
# cp etc/sudoers.d/tkmon /etc/sudoers.d/tkmon
# chmod 440 /etc/sudoers.d/tkmon
```

##### Add admin group and allow web user

```
# addgroup --system tkmonweb
# adduser www-data tkmonweb
# service apache2 restart
```

#### Install Icinga

We’ll install icinga with some special configurations and paths to get managed with the appliance.

##### Install PPA repository

Currently a current version of icinga is needed. Default of Ubuntu is something arround **1.6.1-2 0** but we need 1.8.x. Therefore we use the PPA launchpad repository of the official debian packager: Alexander Wirth.

```
# aptitude install python-software-properties
# add-apt-repository ppa:formorer/icinga
# aptitude update
# aptitude install icinga
```

##### Add TK admin user to icinga

```
# cd /etc/icinga
# # Add password from config.json
# htpasswd -b htpasswd.users "tkadmin" "7RMan59XmN9t3FO2evmB"
# # Add tkadmin to cgi.cfg
# sed -i.BAK -e 's/icingaadmin/icingaadmin,tkadmin/g' cgi.cfg
```

This is the TKMON service account. The software needs admin access to fetch data or send commands into the icinga instance.

##### Configure icinga

Offer new configuration directories to icinga.

```
# cd /etc/icinga
# ln -s /path/to/tkmon/etc/icinga/ tkmon
```

Change icinga.cfg as follows

```
# diff -u  icinga.cfg.org icinga.cfg
--- icinga.cfg.org      2013-01-18 12:14:24.316237780 +0000
+++ icinga.cfg  2013-01-18 12:15:56.751081775 +0000
@@ -29,10 +29,9 @@
 # Hint: Check the docs/wiki on how to monitor remote hosts with different
 # transport methods and plugins

-# Debian uses by default a configuration directory where icinga-common,
-# other packages and the local admin can dump or link configuration
-# files into.
-cfg_dir=/etc/icinga/objects/
+cfg_dir=/etc/icinga/tkmon/base
+cfg_dir=/etc/icinga/tkmon/system/templates
+cfg_dir=/etc/icinga/tkmon/system/contacts
+cfg_dir=/etc/icinga/tkmon/system/hosts
+cfg_dir=/etc/icinga/tkmon/custom

 # Definitions for ido2db process checks
 #cfg_file=/etc/icinga/objects/ido2db_check_proc.cfg
```

Fix some privileges. This is needed to allow www-data (the apache) to write into our config directories. And apache is writing or configuration now.

```
# chown -R www-data:www-data /path/to/tkmon/etc/icinga/
```

Explanations on the new paths:

<dl>

<dt class="hdlist1">**/etc/icinga/tkmon/base**</dt>

<dd>

Base monitoring: appliance, templates. Everthing that is needed to run the appliance on top of

</dd>

<dt class="hdlist1">**/etc/icinga/tkmon/system/templates**</dt>

<dd>

Changeable templates. If change some templatse in the web interface dynamic template configuration goes into this. Please **DO NOT** change anything manually!

</dd>

<dt class="hdlist1">**/etc/icinga/tkmon/system/contacts**</dt>

<dd>

If you create new contacts there is the place to be. This directory holds dynamic frontend configuration so **DO NOT** change anything in there.

</dd>

<dt class="hdlist1">**/etc/icinga/tkmon/system/hosts**</dt>

<dd>

Host and service object. Also ***DO NOT** … Yes, I think you got it!

</dd>

</dl>

**/etc/icinga/tkmon/custom**: If you have custom configurations and knowledge about icinga and you **WANT TO** change something manually, please go here to do so.

### Packaging fixes

#### Database

The default database configuration for source package is something like this (config.json, debconf related part):

```
"db.autocreate":        true,
"db.debconf.use":       false,
"db.debconf.file":      "{core.etc_dir}/config-db.php",
```

This means that configuration is taken from config.json (paths, files) and a default datase is created for you. To create your own database change setting to this please:

```
"db.autocreate":        false,
"db.debconf.use":       true,
"db.debconf.file":      "/etc/tkmon/config-db.php",
```

This uses the config-db.php file to configure your connection and switch db autocreation off.

#### JSON core variables

To fix FHS directories tkmon checks if /etc/tkmon exists and use this directory for configuration. If you want to rewrite the pats for packaging examine config.json for the core objects:

```
"core.lib_dir":         "{core.root_dir}/lib/tkmon",
"core.share_dir":       "{core.root_dir}/share/tkmon",
"core.template_dir":    "{core.share_dir}/templates",
"core.var_dir":         "{core.temp_dir}/tkmon",
"core.cache_dir":       "{core.var_dir}/cache",
```

Also you can override some hidden settings:

```
"core.root_dir":        "",
"core.etc_dir":         "",
"core.tmp_dir":         "",
```

This settings are detected on system start. But you can override in config.json

#### Location of configuration file

**config.json** is automatically detected if resides in **/etc/tkmon/config.json** if not we’re looking relative to root directory in **etc/tkmon/config.json** which means e.g. **/opt/tkmon-web/etc/tkmon/config.json**

### Done

You are ready now to open your browser and go to you configured location.

*   Default user (and also the only one) is: **root**

*   Default password: **password**

## Configuration

Please have a look into the configuration file **config.json**. The most keys are self explainable, but in case of trouble here comes some explanation.

### How it works

config.json is (maybe you suspected it behind the file extension) is [JSON](http://en.wikipedia.org/wiki/JSON) which means:

*   All and every thing is enclosed by ""

*   No comments like // or /* */

*   Sub types are possible like [] = ARRAYS or {} are objects

*   Keep an eye of useless comma to separate things

At first the config is loaded from file, afterwards override settings are loaded from database. If something does not work like expected you can drop the sqlite database file.

Have a look on the setting **config.include**. Here a additional file is included if you need to override specific settings, e.g. links from logo or name of the application.

A example file for user config is provided: etc/tkmon/userconfig.json.example. Just copy that file to etc/tkmon/userconfig.json and change the settings you need. This file is automatically included on system start.

### Settings appendix

<dl>
    <dt class="hdlist1">core.lib_dir</dt>
    <dt class="hdlist1">core.share_dir</dt>
    <dt class="hdlist1">core.template_dir</dt>
    <dt class="hdlist1">core.var_dir</dt>
    <dt class="hdlist1">core.cache_dir</dt>
    <dt class="hdlist1">session.name</dt>
    <dt class="hdlist1">session.lifetime</dt>
    <dt class="hdlist1">view.refresh (int)</dt>
    <dd>Time in seconds for auto reload views, e.g. services or event
    log.</dd>
    <dt class="hdlist1">template.file</dt>
    <dt class="hdlist1">template.cache</dt>
    <dt class="hdlist1">template.cache_dir</dt>
    <dt class="hdlist1">navigation.data</dt>
    <dt class="hdlist1">mvc.action.namespace</dt>
    <dt class="hdlist1">app.version.major</dt>
    <dt class="hdlist1">app.version.minor</dt>
    <dt class="hdlist1">app.version.patch</dt>
    <dt class="hdlist1">app.version.full</dt>
    <dt class="hdlist1">app.name</dt>
    <dt class="hdlist1">app.copyright</dt>
    <dt class="hdlist1">app.version.release</dt>
    <dt class="hdlist1">app.login.counter</dt>
    <dt class="hdlist1">db.schema</dt>
    <dt class="hdlist1">db.basepath</dt>
    <dt class="hdlist1">db.name</dt>
    <dt class="hdlist1">db.type</dt>
    <dt class="hdlist1">db.autocreate</dt>
    <dt class="hdlist1">db.debconf.use</dt>
    <dt class="hdlist1">db.debconf.file</dt>
    <dt class="hdlist1">system.interface</dt>
    <dt class="hdlist1">system.apache_owner</dt>
    <dt class="hdlist1">locale.domain</dt>
    <dt class="hdlist1">locale.path</dt>
    <dt class="hdlist1">locale.name</dt>
    <dt class="hdlist1">locale.list</dt>
    <dt class="hdlist1">icinga.passwdfile</dt>
    <dt class="hdlist1">icinga.config</dt>
    <dt class="hdlist1">icinga.apacheconfig</dt>
    <dt class="hdlist1">icinga.adminuser</dt>
    <dt class="hdlist1">icinga.tkuser</dt>
    <dt class="hdlist1">icinga.tkpasswd</dt>
    <dt class="hdlist1">icinga.baseurl</dt>
    <dt class="hdlist1">icinga.accessurl</dt>
    <dt class="hdlist1">icinga.freshness</dt>
    <dt class="hdlist1">icinga.dir.base</dt>
    <dt class="hdlist1">icinga.dir.contact</dt>
    <dt class="hdlist1">icinga.dir.host</dt>
    <dt class="hdlist1">icinga.dir.template</dt>
    <dt class="hdlist1">thomaskrenn.template.host</dt>
    <dt class="hdlist1">icinga.record.contact</dt>
    <dt class="hdlist1">icinga.record.host</dt>
    <dt class="hdlist1">icinga.record.service</dt>
    <dt class="hdlist1">icinga.catalogue.services.json.default</dt>
    <dt class="hdlist1">icinga.catalogue.services.json.custom</dt>
    <dt class="hdlist1">command.config</dt>
</dl>