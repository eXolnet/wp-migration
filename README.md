# WP Migration

[![Latest Stable Version](https://poser.pugx.org/eXolnet/wp-migration/v/stable?format=flat-square)](https://packagist.org/packages/eXolnet/laravel-heartbeat)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/eXolnet/laravel-heartbeat/master.svg?style=flat-square)](https://travis-ci.org/eXolnet/wp-migration)
[![Total Downloads](https://img.shields.io/packagist/dt/eXolnet/laravel-heartbeat.svg?style=flat-square)](https://packagist.org/packages/eXolnet/wp-migration)

Add a laravel like migration system to Wordpress

## Installation

Require this package with composer:

```bash
composer require exolnet/wp-migration
```

## Commands

### make
Make a migration skeleton file in migrations folder in the themes folder
```
 * ## OPTIONS
 *
 * <migrationName>
 *
 *     : Name of the migration in Camel Case
 *
 * ## EXAMPLE
 *
 *     wp migration make exampleMigration
 *
 */
 ```
 
 ### migrate
 Run or rollback the migration files
 ```
 /**
  *
  * ## OPTIONS
  *
  * [--rollback]
  *     : A flag to run the rollback of the latest migration or a specific batch using the step
  *
  * [--nodev]
  *     : Run only migration that are not listed as development. To specify if the migration only in
  *       development you need to set the public variable `public $environment = 'development';`
  *       in your migration class
  *
  * [--step]
  *     : Force the migrations to be run so they can be rolled back individually.
  *
  * ## EXAMPLES
  *
  *     wp migration migrate
  *
  */
```
