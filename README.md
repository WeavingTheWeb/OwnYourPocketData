Own your pocket data
=================

[![Build Status](https://travis-ci.org/WeavingTheWeb/OwnYourPocketData.svg)](https://travis-ci.org/WeavingTheWeb/OwnYourPocketData)

Command-line application to retrieve a user's pocket data via Pocket API

## Requirements

 * curl
 * PHP 5.4
 * PHP curl extension

## Installation

### Install vendor libraries

Download composer with cURL or follow [alternative instructions](https://getcomposer.org/download/)

```
curl -sS https://getcomposer.org/installer | php
```

```
composer install --prefer-dist 
```

### Configure the command-line application

Register an application via [Pocket Developer Platform](http://getpocket.com/developer/apps/new)

Configure the command-line application by setting the application consumer key

```
# Declare a Pocket application consumer key as an environment variable
export POCKET_CONSUMER_KEY=my_pocket_application_consumer_key

# Copy the configuration example file
cp Resources/config/config.yml{.dist,}

# Replace "~" with a Pocket application consumer key
sed -i '' "s/~/$POCKET_CONSUMER_KEY/g" Resources/config/config.yml
```

### Run the application authorization server

```
# Run PHP 5.4 builtin server
php -S 127.0.0.1:8000 -t web web/index.php
```

### Authorize a Pocket application


Access the following URL in a browser: [http://127.0.0.1:8000/oauth/request](http://127.0.0.1:8000/oauth/request)