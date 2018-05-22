<?php

namespace DI\Autowiring;

use DI\Autowiring;
use ReflectionClass;
use ReflectionParameter;
use DI\ContainerInterface;
use DI\Exception\AutowiringException;

class ReflectionBasedAutowiring implements Autowiring
{
	private $container;

	public function __construct(ContainerInterface $container)
	{
		$this->container = $container;
	}

	public function autowire($class)
	{
		$reflector = new ReflectionClass($class);

		if (!$reflector->isInstantiable()) {
			throw new AutowiringException(sprintf(
				'The class [%s] is not instantiable', $class
			));
		}

		if (!$constructor = $reflector->getConstructor()) {
			return new $class();
		}

		$args = $this->buildArgs(...$constructor->getParameters());

		return $reflector->newInstanceArgs($args);
	}

	private function buildArgs(ReflectionParameter ...$parameters)
	{
		$args = [];

		foreach ($parameters as $parameter) {
			$typehint = $parameter->getClass();

			if (!$typehint) {
				if (!$parameter->isOptional()) {
					throw new AutowiringException(sprintf(
						'Could not resolve parameter [%s] while resolving [%s]',
						$parameter->name, $parameter->getDeclaringClass()->name
					));
				}

				$args[] = $parameter->getDefaultValue();
				continue;
			}

			$args[] = $this->container->get($typehint->name);
		}

		return $args;
	}
}