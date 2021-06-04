[Back to Contents](README.md)

# Getting started
This how-to describes how to get started using b2db. It assumes some basic knowledge about
php, composer and systems set-up, but should be easy to follow.

## Install composer
B2db uses [composer](https://getcomposer.org) for dependency management, and 
should be installed and used via composer. Download and install composer from the website 
before continuing.

## Create your project and add b2db as a dependency
To use b2db as an ORM for your application, add it as a composer dependency by running 
```
composer require pachno/b2db
```

This will download and install b2db, and add it to your project's `composer.json` file.
(if you haven't created a `composer.json` yet, running the command will create one for you.)

## Configure b2db
You can use b2db in your application right away, but it needs to be configured/bootstrapped to actually
connect to the database, retrieve database objects, etc.

To configure b2db, you should run the `\b2db\Core::initialize($options, $cache)` method from the bootstrap section 
in your application, where `$options` is an array with the information required to connect to the database.

Some frameworks can do this automatically using service configurations.

### The `$options` parameter
*Valid `$options` elements*
```php
<?php

$options = [
    // required
    'dsn' => '', // a valid DSN connection string
    // or
    'driver' => '', // a valid driver, see the drivers list
    'hostname' => '',
    'port' => '',
    'username' => '',
    'password' => '',
    'database' => '',
    
    // optional
    'tableprefix' => '', // prefix for all your tables, if used (default '')
    'debug' => '', // true / false to turn on or off debug mode (default false)
    'caching' => '', // true / false to turn on or off caching (default false)
];
```

#### DSN
If you pass a valid DSN configuration string you don't need to pass any of the other required configuration
entries, as the DSN usually contains all necessary information to connect to the database.

#### Valid database drivers
* `mysql` - connects to MySQL and MariaDB databases
* `pgsql` - connects to a PostgreSQL database
* `mssql` - connects to a Microsoft SQL server database

### Caching
If `$options['caching']` is `true` (or not defined), or `$options['debug']` is `false`, you should 
pass a cache object that `implement`s `b2db\interfaces\Cache` as the second parameter, or pass it a callable 
method which returns an object that `implement`s `b2db\interfaces\Cache`.

The `b2db\Cache` class already implements everything needed for either file-based or in-memory cache, so
the easiest option is to pass an instantiated `b2db\Cache` object.

