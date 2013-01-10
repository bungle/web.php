![web.php-logo](http://code.google.com/p/web-dot-php/logo)  
**web.php** is a zero configuration web development library for PHP.

## Hello World in web.php

```php
<?php
include 'web.php';
get('/', function() {
    die('Hello, World!');
});
```

## Table of Contents

* [Installation](#installation)
* [Routing](#routing)
* [Content Negotiation](#content-negotiation)
* [Forwards and Redirects](#forwards-and-redirects)
* [Views, Layouts, Blocks, Partials, and Pagelets](#views-layouts-blocks-partials-and-pagelets)
* [Filters, Forms, and Input Validation](#filters-forms-and-input-validation)
* [Sending Files](#sending-files)
* [Logging with log.php](#logging-with-logphp)
* [Password Hashing & Checking with password.php](#password-hashing--checking-with-passwordphp)
* [FAQ](#faq)
* [License](#license)

## Installation

Download `web.php` (for logging download `log.php`, and for password hashing download `password.php`).

There are also libraries for Tumblr (`tumblr.php`), OpenID (`openid.php`), SQLite 3 (`sqlite.php`),
and Postmark (`postmark.php`). New libraries are added now and then. These libraries follow the
minimalistic approach of `web.php`.

### On Apache Modify .htaccess

```apache
<IfModule mod_rewrite.c>
    RewriteCond %{REQUEST_FILENAME} !-f
	RewriteRule ^ index.php [L]
</IfModule>
```    

### On Nginx with PHP-FPM

```nginx
server {
    location / {
        try_files                 $uri $uri/ /index.php?$args;
    }
    location = /index.php {
        try_files $uri = 404;
        fastcgi_pass              127.0.0.1:9000;
        fastcgi_index             index.php;
        fastcgi_param             SCRIPT_FILENAME   $document_root$fastcgi_script_name;
        fastcgi_split_path_info   ^(.+\.php)(/.+)$;
        include fastcgi_params;
    }
}
```

If you are using something other than `Apache` with `mod_rewrite` or `nginx`, Google for instructions.

### Create `index.php` Bootstrap File

```php
<?php
include 'web.php';
get('/', function() {
    die('Hello, World!');
});
status(404);
die('404 Not Found');
```

## Routing

`web.php` has support for routing HTTP GET, POST, PUT, and DELETE request. Routes are case-insensitive, and the trailing `/` is omitted.

### GET Routes

Use `get($path, $func)` to route HTTP GET requests.

#### Routes without Parameters (the Routes Work on Sub-Directories too):

```php
<?php
get('/', function() {
    die('Hello, World!');
});
```

or:

```php
<?php
get('/posts', function() {
    die(json_encode([
        [
            'id'    => 1,
            'title' => 'Trying out web.php',
            'body'  => 'Lorem...' 
        ],
        [
            'id'    => 2,
            'title' => "I'm really starting to like web.php",
            'body'  => 'Lorem...' 
        ]
    ]));
});
```

#### Parameterized Routes

Route parameters in `web.php` are parsed with `sscanf` and `vsprintf`, but we have added extra parameter `%p` which acts
the same as `%[^/]` (everything until or except `/`). Please read the documentation for the format from
[sprintf's](http://www.php.net/manual/function.sprintf.php) documentation.

```php
<?php
get('/posts/%d', function($id) {
    switch ($id) {
        case 1: die(json_encode([
                'id'    => 1,
                'title' => 'Trying out web.php',
                'body'  => 'Lorem...' 
            ]));
        case 2: die(json_encode([
                'id'    => 2,
                'title' => "I'm really starting to like web.php",
                'body'  => 'Lorem...' 
            ]));
    }
}
```

### POST Routes

Use `post($path, $func)` to route HTTP POST requests. See the *GET Routes* examples.

### PUT Routes

Use `put($path, $func)` to route HTTP PUT requests. See the *GET Routes* examples.

You can send PUT requests with POST method by sending `_method` parameter that has a value of `PUT`:

```html
<form method="post">
    <input type="hidden" name="_method" value="PUT">
</form>
```

### DELETE Routes

Use `delete($path, $func)` to route HTTP DELETE requests. See the *GET Routes* examples.

You can send DELETE requests with POST method by sending `_method` parameter that has a value of `DELETE`:

```html
<form method="post">
    <input type="hidden" name="_method" value="DELETE">
</form>
```

### Routing All Types of Requests

Use `route($path, $func)` to route all HTTP requests. See the *GET Routes* examples.

## Content Negotiation

You can send different content based on client's Accept HTTP Header:

```php
<?php
get('/ping', accept('text/html', 'application/xhtml+xml', function() {
$html =<<<'HTML'
<html>
    <body>PONG</body>
</html>
HTML;
die($html);
}));

get('/ping', accept('application/xml', function() {
    echo '<' . '?xml version="1.0" encoding="utf-8"?' . '>';
    die('<pong />');
}));

// Parameterized Routes with Content Negotiation:
get('/plus/%d/%d', accept('application/html', function($a, $b) {
    die('<html><body>' . ($a + $b) . '</body></html>');
}));
```

You can also use `accept` function like this:

```php
<?php
get('/ping', function() {
    accept([
        'text/html'        => 'pong.html',
        'application/json' => 'pong.json',
        'application/xml'  => 'pong.xml'
    ]) and die;
});
```

These work too (as described [here](#is-routing-to-anonymous-function-the-only-option)):

```php
<?php
accept([
    'text/html'        => 'phpinfo',
    'application/xml'  => 'XML::xml',
    'application/json' => 'JSON->json',
    'text/plain'       => function() { die('Hello, World!'); }
]);
```

## Forwards and Redirects

### Forwards

Use `forward($name, $func)` to register a named forward. Call a named forward with `forward($name)`.
Forwards need to be registered before using them.

```php
<?php
forward('index', function() {
    die('Index Page');
});

get('/', function() {
    forward('index')
});

get('/another-url', function() {
    forward('index')
});
```

### Redirects

Use `redirect($url, $code = 302, $die = true)` to redirect the user in other page.
Use `$code` value of `301` for permanent redirects.

```php
<?php
session_start();
get('/', function() {
    die(isset($_SESSION['redirected']) ? 'Redirected' : 'Welcome!');
});

post('/', function() {
    flash('redirected');
    redirect('~/');
});
```

### Flash Variables

Use `flash($name, $value = true, $hops = 1)` to set short living session variables.
`$hops` argument tells us how long (for how many requests) should we keep the session variable.

## Views, Layouts, Blocks, Partials, and Pagelets

`web.php` has support for views, and layouts (or even sub-layouts).

### Views

```php
<?php
get('/', function() {
    die(new view('view.php'));
});
```

*view.php:*

```html
<!DOCTYPE html>
Hello World
```

#### Views with Properties

```php
<?php
get('/%s', function($text) {
    $view = new view('view.php');
    $view->text = htmlspecialchars($text);
    die($view);
});
```

*view.php:*

```html+php
<!DOCTYPE html>
Hello, <?= $text ?>!
```

#### Global View Variables

You can define global view variables that all the views will get with the following code:

```php
<?php
view::$globals->title = 'web.php rocks!';
```

Note: If local view variables are defined with same name as global variables, local variables overwrite the global ones.
The globals are still accessible from `view::$globals`.

### Layouts

You can define the layout by setting the `layout` variable in a view, you can do it like this:

```php
<?php
view::$globals->layout = 'layout.php';      // or
$view = new view('view.php', 'layout.php'); // or
$view = new view('view.php');
$view->layout = 'layout.php';               // or
$view = new view('view.php');               // see 'view.php'
```

*view.php:*

```php
<?php $layout = 'layout.php'; ?>
Hello, World!
```

*layout.php:*

```html+php
<!DOCTYPE html>
<html>
    <body><?= $view ?></body>
</html>
```

Note: All the view variables are also accessible from layouts.

#### Nested Layouts

*view.php:*

```html+php
<?php $layout = 'section.php' ?>
<p>Hello World</p>
```

*section.php:*

```html+php
<?php $layout = 'master.php' ?>
<section>
    <?= $view ?>
</section>
```

*master.php:*

```html+php
<!DOCTYPE html>
<html>
    <body>
        <?= $view ?>
    </body>
</html>
```
   
### Blocks

Blocks are a method to move particular block of 'text' from views to a particular location in layouts.

*view.php:*

```html+php
<?php
$layout = 'layout.php';
$title  = 'Blocks - web.php';
?>
<?php block($head); ?>
    <meta name="description" content"web.php has blocks too!">
<?php block(); ?>

Hello World!

<?php block($aside); ?>
    Hello Aside, too!
<?php block(); ?>

<?php block($scripts); ?>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"></script>
<?php block(); ?>
```

*layout.php:*

```html+php
<!DOCTYPE html>
<html>
    <head>
        <title><?= isset($title) ? $title : 'Default Title' ?></title>
        <?= isset($head) ? $head : '' ?>
    </head>
    <body>
        <article>
            <?= $view ?>
        </article>
        <?php if (isset($aside)): ?>
        <aside>
            <?= $aside ?>
        </aside>
        <?php endif; ?>
        <?= isset($scripts) ? $scripts : '' ?>
    </body>
</html>
```

### Partials

TBD

### Pagelets

TBD (see: [Facebook's BigPipe](https://www.facebook.com/note.php?note_id=389414033919)).

## Filters, Forms, and Input Validation

### Filtering and Validating Variables

Use `filter()` to filter a variable:

    <?php
    $email = 'john@doe.net';
    echo filter($email, 'email') ? 'Valid Email' : 'Invalid Email';

#### Built-in Filters and Validators

web.php has these built-in validators available that come with PHP's [Filter Funtions](http://www.php.net/manual/ref.filter.php):

    'bool', 'int', 'float', 'ip', 'ipv4', 'ipv6', 'email', and 'url'

In addition to that you can validate using regular expressions:

    <?php
    $email = 'john@doe.net';
    echo filter($email, '/^.+@.+$/') ? 'Valid Email' : 'Invalid Email'; // Outputs 'Valid Email'

But that is not all, web.php comes with these functions to aid in validation:

* `not($filter)`
* `equal($exact, $strict = true)`
* `length($min, $max = null, $charset = 'UTF-8')`
* `minlength($min, $charset = 'UTF-8')`
* `maxlength($max, $charset = 'UTF-8')`
* `between($min, $max)`
* `minvalue($min)`
* `maxvalue($max)`
* `choice()`

Example:

    <?php
    $email = 'john@doe.net';
    echo filter(
        $email,
        'email',
        choice('john@doe.net', 'john@doe.com')
    ) ? 'Valid Email' : 'Invalid Email'; // Outputs 'Valid Email'
    
    $age_o = '16';
    $age_f = filter(
        $age_o,
        'int',
        'intval',
        not(between(0, 18))
    );
    echo $age_f !== false ? 'Under-aged: {$age_o}' : "Over-aged: {$age_f}"; // Outputs 'Under-aged: 16'

Note: you can use multiple filters with single filter call.

#### User Defined Filters and Validators

Filters can either modify the `$value` or validate the `$value`. If validation filter fails, the filter function will
return false immediately.

The most simple modifying filter:

    <?php
    function world_filter($value) {
        return "{$value} World!";
    }
    echo filter('Hello', 'world_filter');               // Outputs 'Hello World!'
    echo filter('Hello', 'world_filter', 'strtoupper'); // Outputs 'HELLO WORLD!'

The most simple validating filters:

    <?php
    function true_validator($value) {
        return true;
    }
    function false_validator($value) {
        return false;
    }    
    $valid = filter('Hello', 'true_validator');                    // $valid holds true
    $valid = filter('Hello', 'true_validator', 'false_validator'); // $valid holds false

You can also mix modifying filters and validating filters:

    <?php
    $value = filter('1', 'int', 'intval'); // $value holds int(1)
    $value = filter('A', 'int', 'intval'); // $value holds bool(false)

Sometimes you need to write parameterized filters:

    <?php
    function lessthan($number) {
        return function($value) use ($number) {
            return $value < $number;
        };    
    }
    
    $num = '5';
    echo filter($num, 'int', 'intval', lessthan(6)) ? "{$num} is less than 6" : "{$num} is not less than 6";

Note: You can also have your filter functions namespaced.

### Forms Filtering and Validation

TBD

## Sending Files
### X-Sendfile Support
## Logging with `log.php`
### Logging with ChromePHP
### Logging with System Logger
### Extending the Logger
## Password Hashing & Checking with `password.php`
## Database Access
### Using SQLite with `sqlite.php`

`sqlite.php` has a few functions to make accessing SQLite 3 databases intuitive, and safe.

**Single row returning functions:**

* `\sqlite\value()`
* `\sqlite\pair()`
* `\sqlite\row()`

**Multiple rows can be queried with following functions:**

* `\sqlite\values()`
* `\sqlite\pairs()`
* `\sqlite\rows()`

**Data manipulation operations can be called with the following functions:**

* `\sqlite\insert($table, $values, &$id)`
* `\sqlite\update()`
* `\sqlite\delete()`

**Low lewel, and utility functions:**

* `\sqlite\connect($filename = null, $flags = SQLITE3_OPEN_READWRITE, $busyTimeout = null)`
* `\sqlite\prepare($query, $params = array())`
* `\sqlite\exec()`

#### Querying Database

Use `\sqlite\value()` to get single value from database:

```php
<?php
$max = \sqlite\value('SELECT MAX(amount) FROM sales');
// You can also pass arguments:
$max = \sqlite\value('SELECT MAX(amount) FROM sales WHERE cid = ?', 134);
// Or multiple arguments:
$max = \sqlite\value('SELECT MAX(amount) FROM sales WHERE cid = ? AND dtm < ?', 134, date_create());
```

## FAQ

#### Is routing to anonymous function the only option?

You can actually route to `files`, `functions`, `static class methods`, and `object instance methods`:

    get('/%d', 'router.php');          // Look for $args[0] inside router.php
    get('/%p', 'die');                 // URL: /hello will output 'hello' with PHP's built-in function 'die'
    get('/', 'Clazz::staticMethod');   // Executes a static method
    get('/', 'Clazz->instanceMethod'); // Instantiates new object from class 'Clazz' using parameterless constructor

#### web.php pollutes the global root namespace!

Yes, that's true. `web.php` could be wrapped to namespace just by making this declaration on top of the `web.php`:

    namespace web;

... and then just instead of calling for example `get` you would call `web\get`. The reason we didn't choose to do that is
just that we like using the shorter versions for these functions. We welcome you to do a fork of `web.php`, if you see
this as an issue. And we are also open for suggestions.

#### Why are you using 'die' inside controllers? How can I execute code after executing route, i.e. cleanup resources?

This design decision is probably something that people may or may not agree. We think that it is user's responsibility to
manage the execution. You don't have to `die`, but keep in mind that any other route that is executed after the matching
one will also be executed if it matches the url. That's why it's common to `die` with web.php.

If you want to run cleanup code, please try to build your code so that cleanup routines can be registered with
`register_shutdown_function`.

#### How fast is web.php?

It depends on what you compare it to. But if you compare it to other PHP frameworks, web.php will surely stand the
competition. If you know how web.php could be made faster, please let us know it too!

#### Why web.php is not object oriented?

We think that PHP enables us to do multiparadigm programming. We always try to evaluate different approaches,
when we decide to add features, or when we are refactoring web.php. Sometimes the procedural approach wins
(most of the web.php's core), and sometimes object oriented way of doing things stands out the best
(e.g. views, and forms). PHP also allows us to mix procedural, and object oriented programming with functional
programming. We tend to write the client code first, so that we can see how it looks like, before we go actually
implementing the thing. In that process we usually try different paradigms. It's might come to taste, but we tend
to like procedural / functional way of doing things cleaner (less abstraction and encapsulation, but also more
to the point solutions with less code). So basically we do use object oriented as we see it fit, but not exclusively.

Anthony Ferrara has said this better: [Paradigm Soup on YouTube](http://www.youtube.com/watch?v=CV4vPsEizJM).

#### What is the philosophy behind web.php?

If there is any, this comes to close:

> Perfection is achieved, not when there is nothing more to add, but when there is nothing left to take away.  
--  *Antoine de Saint-Exupéry*

web.php is trying to follow the principles of [Unix philosophy](http://en.wikipedia.org/wiki/Unix_philosophy).

#### web.php doesn't provide object relational mapper (ORM), what do you suggest?

You could try these:

* [RedBeanPHP](http://redbeanphp.com/)
* [Doctrine](http://www.doctrine-project.org/)

You could also try out NoSQL DBs like:

* [Redis](http://redis.io/)
* [RethinkDB](http://www.rethinkdb.com/)
* [MongoDB](http://www.mongodb.org/)

#### How do I run the tests?

Right now the tests are work in progress.

    cd tests
    pear run-tests

#### PHP Sucks! Where to go next?

Try these:

* Perl: [Mojolicious](http://mojolicio.us/)
* Ruby: [Sinatra](http://www.sinatrarb.com/)
* Python: [web.py](http://webpy.org/)
* Javascript: [Node.js](http://nodejs.org/) + [Express](http://expressjs.com/)

#### I see that you are using `goto` inside view class' `__toString`. Isn't `goto` considered harmful?

Feel free to make a fork and change it to `while (true) { ... }` or `do { ... } while (true);` or `for(;;) { ... }`.

## License

web.php is distributed with MIT License.

    web.php
    Copyright (c) 2012 Aapo Talvensaari
    
    Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
    documentation files (the "Software"), to deal in the Software without restriction, including without limitation
    the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software,
    and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
    
    The above copyright notice and this permission notice shall be included in all copies or substantial portions of
    the Software.
    
    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO
    THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
    THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF
    CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
    IN THE SOFTWARE.
