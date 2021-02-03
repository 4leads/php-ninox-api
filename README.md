[![MIT licensed](https://img.shields.io/badge/license-MIT-blue.svg)](./LICENSE)

# PHP Ninox REST-API Client/Wrapper

**This libary allows you to quickly and easily perform REST actions on the Ninox backend using PHP.**

## Installation

### Prerequisites

- PHP version ^7.4
- Ninox Account including API function

### Ninox API-Docs

[Ninox-API](https://ninox.com/de/manual/ninox-api/rest-api)

### Install Package

Add Ninox-API to your `composer.json` file. If you are not using [Composer](http://getcomposer.org), we highly recommend it. It's an excellent way to
manage dependencies in your PHP application.

```sh
composer require 4leads/php-ninox-api
```

## Usage

### Set ``team_id`` and/or ``database_id`` static or dynamic

```php
use Ninox\Ninox;

$client = new Ninox("API_KEY");
//either set team_id and/or databse globally
Ninox::setFixTeam("teamId");
Ninox::setFixDatabase("databseId");
$client->listTables();
//or set it on every request (overwrites global settings for this request if set)
$client->listTables("databseId2","teamId2");
//or just override database id but use global teamId
$client->listTables("databseId3");
```

### On private cloud/on-premise systems

```php
use Ninox\Ninox;
$client = new Ninox("API_KEY",["host" => "https://yourprivate.host.com/v1"]);
```

### File Up-/Downloads

The library uses bare curl functions for file up-/downloads. This may lead to problems on bigger files. Consider script runtime and curl timeout on
bigger files as well.