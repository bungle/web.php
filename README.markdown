# What is web.php?

**web.php** is a simple and minimalistic front controller based model-view-controller implementation for PHP > 5.3.

## Hello World in web.php

    <?php
    include 'web.php';
    get('/', function() {
        die('Hello, World!');
    });

## Installation

Download `web.php` (for logging download `log.php`, and for password hashing download `password.php`).

### Modify .htaccess

    <IfModule mod_rewrite.c>
        RewriteCond %{REQUEST_FILENAME} !-f
    	RewriteCond %{REQUEST_FILENAME} !-d
    	RewriteRule . index.php [L]
    </IfModule>

If you are using something other than `Apache` with `mod_rewrite`, Google for instructions.

### Create `index.php` bootstrap file

    <?php
    include 'web.php';
    get('/', function() {
        die('Hello, World!');
    });