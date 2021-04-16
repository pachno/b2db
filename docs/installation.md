[Back to Contents](README.md)

# Getting started
This how-to describes how to get started using b2db. It assumes some basic knowledge about
php, composer and systems set-up, but should be easy to follow.


## Install composer
B2db uses [composer](https://getcomposer.org) for dependency management, and 
should be installed and used via composer. Download and install composer from the website 
before continuing.

## Create your project and add b2db as a dependency
To use b2db as an ORM for your application, add it as a composer dependency by 
running `composer require pachno/b2db`. This will download and install b2db, 
and add it to your project's `composer.json` (if you haven't created a `composer.json` yet, 
running the command will create one for you.

## Configure b2db
You can use b2db in your application right away, but it needs to be configured to actually connect
to the database, retrieve database objects, etc.

To configure b2db, bootstrap it from a file in your application that is included on all requests, by
calling `\b2db\Core::initialize($options, $cache)`, where `$options` is an array with the information
required to connect to the database.

Some frameworks can do this automatically using service configurations.

*Valid `$options` elements*
```php
<?php

$options = [
    'dsn' => '', // a valid DSN connection string
    'username' => '',
    'password' => '',
    'driver' => '', // a valid driver, see the drivers list
    'hostname' => '',
    'port' => '',
    'database' => '',
    
    // optional
    'tableprefix' => '', // prefix for all your tables, if used (default '')
    'debug' => '', // true / false to turn on or off debug mode (default false)
    'caching' => '', // true / false to turn on or off caching (default false)
];
```

If `$options['caching']` is `true` (or not defined), or `$options['debug']` is `false`, you should 
pass a cache object that implements `interfaces\Cache` as the second parameter. 

### DSN
If you pass a valid DSN configuration string, you don't need to pass any of the other configuration
entries, as the DSN usually contains all necessary information to connect to the database.

### Valid database drivers
The valid database drivers are:
* `mysql` - connects to MySQL and MariaDB databases
* `pgsql` - connects to a PostgreSQL database
* `mssql` - connects to a Microsoft SQL server database
