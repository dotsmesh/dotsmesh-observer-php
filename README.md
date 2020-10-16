# Dots Mesh Observer

This package is responsible for sending push notifications to private users.

## Requirements
- A web server (Apache, NGINX, etc.)
- PHP 7.2+
- A domain starting with "dotsmesh." (dotsmesh.example.com)
- SSL/TLS certificate

## How to install

You can [download the latest release as a PHAR file](https://github.com/dotsmesh/dotsmesh-observer-php/releases) and run the server this way. Create the index.php with the following content and configure it properly:
```php
<?php

define('DOTSMESH_OBSERVER_DATA_DIR', 'path/to/data/dir'); // The directory where the data will be stored.
define('DOTSMESH_OBSERVER_LOGS_DIR', 'path/to/logs/dir'); // The directory where the logs will be stored.
define('DOTSMESH_OBSERVER_HOSTS', ['example.com']); // A list of hosts supported by the observer server.

require 'dotsmesh-observer-php-x.x.x.phar';
```

There is a [ZIP file](https://github.com/dotsmesh/dotsmesh-observer-php/releases) option too. Just extract the content to a directory and point the index.php file to it.
```php
<?php

define('DOTSMESH_OBSERVER_DATA_DIR', 'path/to/data/dir'); // The directory where the data will be stored.
define('DOTSMESH_OBSERVER_LOGS_DIR', 'path/to/logs/dir'); // The directory where the logs will be stored.
define('DOTSMESH_OBSERVER_HOSTS', ['example.com']); // A list of hosts supported by the observer server.

require 'dotsmesh-observer-php-x.x.x/app/index.php';
```

## License

The Dots Mesh Observer is licensed under the GPL v3 license. See the [license file](https://github.com/dotsmesh/dotsmesh-observer-php/blob/master/LICENSE) for more information.

## Contributions

The Dots Mesh platform is a community effort. Feel free to join and help us build a truly open social platform for everyone.
