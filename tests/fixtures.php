<?php

namespace DI\Test;

interface FooInterface {}

class FooNoConstructor {}

class FooConstructorNoArgs
{
	public function __construct() {}
}

class FooConstructorOneArg
{
	public function __construct(FooNoConstructor $arg) 
	{
		$this->arg = $arg;
	}
}

class FooConstructorMultipleArgs
{
	public function __construct(FooNoConstructor $arg1, FooNoConstructor $arg2)
	{
		$this->arg1 = $arg1;
		$this->arg2 = $arg2;
	}
}

class FooRecursiveArgs
{
	public function __construct(FooConstructorMultipleArgs $arg)
	{
		$this->arg = $arg;
	}
}

class FooImplementation implements FooInterface {}

class FooTestBinding
{
	public function __construct(FooInterface $arg)
	{
		$this->arg = $arg;
	}
}

