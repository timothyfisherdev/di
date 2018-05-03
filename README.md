# DI - A PHP Dependency Injection Container

[![Build Status](https://travis-ci.org/timothyfisherdev/di.svg?branch=master)](https://travis-ci.org/timothyfisherdev/di)
[![Coverage Status](https://coveralls.io/repos/github/timothyfisherdev/di/badge.svg?branch=master)](https://coveralls.io/github/timothyfisherdev/di?branch=master)

This library is a simple implementation of dependency injection via the use of a "container". This project was built simply for learning purposes as well as getting a handle on testing using PHPUnit, Coveralls for code coverage, and the concept of dependency injection.

The container:

  - Handles "services" and "parameters"
  - Acts as a sort of "registry" to access its data
  - Centralizes the way this data is created and accessed
  - Is fast and dependable

# The Guide

### Synopsis

The container handles two types of data: **services** and **parameters**.

A service can be thought of as some global object that is part of a bigger operation. Examples include: PDO database connections, mailers, loggers, etc. These services may also be dependent on other services. For example a ``UserMananger`` service may be dependent on a ``Mailer`` object to send a user emails for registration, password resets, etc. A ``Logger`` service may also be dependent on a ``Mailer`` and vice versa.

You quickly see how the relationships between objects are important, and the need to centralize the way they are created and accessed becomes important as well.

##### Inversion of Control (IoC)

The inversion of control (IoC) principle states that these services should be decoupled from their dependencies. This means that the ``UserManager`` service we talked about earlier, should not know how to create, or be responsible for creating its ``Mailer`` dependency. If the ``UserManager`` is responsible for creating its own dependency, and you decide to change the mailer that you use in the future, you will have to alter the ``UserManager`` class directly. This is bad, and can potentially break a lot of things. The last thing you want is to update to a better mailer and break your whole application. Applications need to be flexible, and IoC is one way of doing that.

Consider this example:

```php
<?php

class UserManager
{
    private $mailer;

    public function __construct()
    {
        $this->mailer = new Mailer();
        $this->mailer->setTransport('sendmail');
    }
    
    public function setMailerTransport($transport)
    {
        $this->mailer->setTransport($transport);
    }
}
```

Here we have our ``UserManager`` service that is responsible for creating its ``Mailer`` dependency via setter injection. If we decide to later change the type of mailer that is used, and that mailer uses constructor injection rather than setter injection, we must alter our ``UserManager`` code, potentially breaking other methods that may use the ``setTransport`` method.

Instead, the ``UserManager`` service should simply ask for a Mailer object, and get one. It should not care how it is created or what methods the mailer uses to configure itself, it just asks for one and receives one:

```php
class UserManager
{
    private $mailer;

    public function __construct(Mailer $mailer)
    {
        $this->mailer = $mailer;
    }
}
```

The configuration of the mailer is now setup outside of the ``UserManager``, and will not break anything if it is changed. So that leaves us with a question: Where should we write this "wiring" or configuration of the dependencies?

That's where the container concept comes in. An IoC, or dependency injection container is the place where you would write up how your services should be created. Going back to the above example:

```php
<?php

$container = new Container();

$container->add('userManager', function($container) {
    $mailer = $container->get('mailer');
    
    return new UserManager($mailer);
});

$container->add('mailer', function() {
    $transport = 'gmail';
    $mailer = new Mailer($transport);
});
```

Now all we have to do is ask for the ``UserManager``, and its ``Mailer`` dependency will be created for us. If we decide to change the transport methodback to sendmail at any point, all we have to do is change it in one place in the service configuration area.

### Usage

##### Instantiation

To use the container, simply create a new instance of the ``DI\Container`` class:

```php
$container = new DI\Container();
```

You may also optionally pass an array of service and parameter entries directly into the constructor:

```php
$entries = ['foo' => 'bar'];
$container = new Container($entries);
```

##### Adding Definitions

**Services** are defined by invokable callback functions. Most commonly, this is a closure (anonymous function) that explains how to create a service:

```php
$myServiceDefinition = function() {
    $obj = new Service();
    
    return $obj;
};
```

**Parameters** are simple primitive values that should be accessed throughout your application. It is assumed that your entry is a parameter if it is not an invokable function.

To add a service or parameter to the container, use the ``DI\Container::add`` method.

```php
$container = new DI\Container();

$container->add('myParameter', 'value');
$container->add('myService', function() {
    return new MyService();
});
```

The container can also be accessed like an array. So adding entries is as easy as using array notation:

```php
$container = new DI\Container();

$container['myParameter'] = 'value';
$container['myService'] = function() {
    return new MyService();
};
```

When defining services, note that an instance of the container is always passed as an argument to the invokable callback. This allows you to resolve nested dependencies. In our synopsis, we used the example of a ``UserManager`` service who needed a ``Mailer`` object. We can define how to create the mailer in one entry, and how to create the user manager in a separate entry like this:

```php
$container = new DI\Container();

$container['userManager'] = function($container) {
    // The user manager needs a mailer, so get that from the container first
    $mailer = $container->get('mailer');
    
    // Now pass that to the UserManager
    return new UserManager($mailer);
};

$container['mailer'] = function() {
    return new Mailer();
};

$userManager = $container->get('userManager');
```

This is known as manually wiring your dependencies.

##### Entry Management

Just as entries can be added to the container, they may be removed via `DI\Container::remove` as well:

```php
$container = new DI\Container();

$container['myParameter'] = 'value';
$container->remove('myParameter');
// or: unset($container['myParameter']);
```

We can also check if the container contains as entry with ``DI\Container::has``:

```php
$container = new DI\Container();

$container['myParameter'] = 'value';
$has = $container->has('myParameter');
// or: isset($container['myParameter']);

var_dump($has); // outputs: true
```

Lastly, we can get an array of all of the **entry names** in our container with ``DI\Container::keys``:

```php
$container = new DI\Container();

$container['myParameter'] = 'value';
$entries = $container->keys();

var_dump($entries); // outputs: ['myParameter']
```

##### Retrieving Services and Parameters

Once an entry is added to the container, we can resolve that entry by using the ``DI\Container::get`` method, or via array access:

```php
$container = new DI\Container();

$container['myParameter'] = 'value';
$myParameter = $container->get('myParameter');
var_dump($myParameter); // outputs: 'value'

$container['myService'] = function() {
    return new MyService();
};
$myService = $container['myService'];
var_dump($myService instanceof MyService::class); // outputs: true
```

Note that the service definition is executed and the result of the callback is what is assigned to ``$myService``, rather than the literal callback function. If you would like a callback function to be interpreted as a literal value, you can use the ``DI\Container::protect`` method:

```php
$container = new DI\Container();

$container->add('myService', $container->protect(function() {
    return new MyService();
}));
$myServiceFactory = $container->get('myService');
var_dump($myServiceFactory instanceof \Closure); // outputs: true
```

By default, when the container resolves a service entry, that service will be "shared" across the life of the container. This means that when the container is asked for the service a second time, the **same** instance will be returned as the first. This is often times the desired behavior when dealing with database connections, mailers, and logger objects.

It would be a waste of memory to create these types of objects multiple times. You would not want 20 different database connections open at the same time if you really only need one:

```php
$container = new DI\Container();

$container->add('db', function() {
    ... // database configuration
    return new PDO();
});
    
$db = $container->get('db');
$db2 = $container->get('db');

var_dump($db === $db2); // outputs: true
```

However, sometimes you do need to get a new instance of a service each time it is accessed. For those cases, simply define the service as a **factory** with ``DI\Container::factory``:

```php
$container = new DI\Container();

$container->add('myService', $container->factory(function() {
    return new MyService();
}));

$myService = $container->get('myService');
$myService2 = $container->get('myService');

var_dump($myService === $myService2); // outputs: false
```

##### Extending Definitions

Often times you need to modify a service after it has been created. This is often times called "setter injection" or "decorating". This can be achieved by using the ``DI\Container::extend`` method. The extend method is passed the instance of the object and an instance of the container in that order. 

An extend will fail if:

  - The entry does not exist
  - The entry is a parameter
  - The entry is currently being resolved
  - The entry is protected
  - The extend callback is not invokable

Therefore, extending is meant to be done on existing service definitions only:

```php
$container = new DI\Container();

$container['mailer'] = function() {
    return new Mailer();
};

$container->extend('mailer', function($mailer, $container) {
    $mailer->setTransport('sendmail');
    $mailer->setUsername($container->get(...));
});
```

If, however, you leave the second argument empty and only assign an invokable callback, like so:

```php
$container->extend(function() {});
```

This will be treated as a global callback function that should be run on every resolve. This is also a cool way to see how inversion of control works. We can assign a callback to run whenever the container resolves any type of entry, and get some feedback about what it is creating.

A great example of dependency injection comes from the [Auryn](https://github.com/rdlowrey/auryn#recursive-dependency-instantiation) container docs:

```php
class Car
{
    private $engine;

    public function __construct(Engine $engine)
    {
        $this->engine = $engine;
    }
}

class Engine
{
    private $sparkPlug;
    private $piston;

    public function __construct(SparkPlug $sparkPlug, Piston $piston)
    {
        $this->sparkPlug = $sparkPlug;
        $this->piston = $piston;
    }
}
```

As you can see we have a ``Car`` object that depends on an ``Engine`` object, and the ``Engine`` object depends on its own service objects ``SparkPlug`` and ``Piston``. Using a normal object creation workflow, the wiring of these objects would look something like this:

* Application needs Car
* Car needs Engine so:
    * Engine needs SparkPlug and Piston so:
        * Engine creates SparkPlug
        * Engine creates Piston
    * Car creates engine
* Application creates Car

However the wiring of objects using inversion of control looks like this:

* Application needs Car, which needs Engine, which needs SparkPlug and Piston so:
* Application creates SparkPlug and Piston
* Application creates Engine and gives it SparkPlug and Piston
* Application creates Car and gives it Engine

You can see that the difference with inversion of control is that application (container) creates the dependencies in an inverted fashion, creating the lowest level dependencies first, and passing them up through the chain of services. This ensures that none of the services know how their dependencies are created, and that they are loosely coupled.

We can have a look at this in action with something like this:

```php
$container = new DI\Container();

// Create something to run on every resolve
$container->extend(function($resolved, $container) {
    echo is_object($resolved) ? get_class($resolved) . ' created.<br />' : '';
});

// Define our services
$container['sparkPlug'] = function() { 
    return new SparkPlug(); 
};
$container['piston'] = function() { 
    return new Piston(); 
};
$container['engine'] = function($c) {
    return new Engine($c['sparkPlug'], $c['piston']);
};
$container['car'] = function($c) { 
    return new Car($c['engine']); 
};

// Get our service
$container->get('car');
```

This would then output the following:

```
SparkPlug created.
Piston created.
Engine created.
Car created.
```

This is proof that the container uses the inversion of control principle since the global callback function gets called after each resolve.

##### Service Providers

Service providers are a way to organize your container entries. Service providers are nothing more than classes that implement the ``DI\ServiceProvider`` interface that exposes a ``register`` method. The ``register`` method is always passed an instance of the container.

For example, lets say that you have a suite of services that deal with user management. You could organize them into a single service provider class/file called ``UserServiceProvider``. This class would then be responsible for adding all container entries related to user management. This simply provides a more organized and collected way of registering data in the container:

```php
class UserServiceProvider implements DI\ServiceProvider
{
    public function register(DI\ContainerInterface $container)
    {
        $container->add('userManager', function() {
            ...
        });
        $container->add('foo' ...);
        ...
    }
}
```

#### Author

Timothy Fisher


### Todos

 - Helper functions for factory and protect
 - Autowiring
 - File loading

License
----

MIT
