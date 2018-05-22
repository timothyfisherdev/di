<?php

namespace DI;

use Closure;
use SplObjectStorage;
use InvalidArgumentException;
use DI\Exception\CyclicException;
use DI\Exception\NotFoundException;
use DI\Exception\ImmutableException;
use DI\Exception\ExpectedInvokableException;
use DI\Autowiring\ReflectionBasedAutowiring;

/** 
 * A simple dependency injection implementation via use of a "container".
 *
 * The container centralizes the way that objects are created and accessed
 * in your applications. It acts as a sort of "registry" for all of your
 * "services" and "parameters".
 *
 * Services are object instances that you wish to access throughout your
 * application, and parameters are simple values that can be used to connect
 * to databases, populate services with data, or just be stored for later use.
 *
 * Services carry "definitions", that are invokable callbacks that provide 
 * instructions on how to make said service.
 *
 * This container implements a simple interface to access entries like an array,
 * and is PSR-11 compliant.
 */
class Container implements ContainerInterface
{
	/**
	 * Entry names only.
	 * 
	 * @var array
	 */
	private $keys = [];

	/**
	 * All data for services and parameters.
	 * 
	 * Updated with service instances.
	 * 
	 * @var array
	 */
	private $entries = [];

	/**
	 * Invokables that will always return a new instance.
	 * 
	 * @var array
	 */
	private $factories = [];

	/**
	 * Invokables that should be interpreted as parameters.
	 * 
	 * @var array
	 */
	private $protected = [];

	/**
	 * Entries currently being resolved.
	 * 
	 * @var array
	 */
	private $resolving = [];

	/**
	 * Keys of entries that have been resolved.
	 * 
	 * @var array
	 */
	private $resolved = [];

	/**
	 * Invokables that should be ran on every resolve.
	 * 
	 * @var array
	 */
	private $globals = [];

	private $useAutowiring = false;

	private $autowiring;

	/**
	 * Create the container.
	 *
	 * Optionally pass in entries on instantiation.
	 *
	 * We use SplObjectStorage to uniquely identify the
	 * invokable definitions.
	 * 
	 * @param array      $entries Service definitions and parameters.
	 * @param Autowiring $entries The autowiring method.
	 */
	public function __construct(array $entries = [], Autowiring $autowiring = null)
	{
		$this->factories = new SplObjectStorage();
		$this->protected = new SplObjectStorage();
		$this->autowiring = $autowiring;

		foreach ($entries as $id => $value) {
			$this->add($id, $value);
		}
	}

	/**
	 * Retrieve an entry.
	 *
	 * Gets a service or parameter from the container. Also
	 * calls global callbacks if there are any.
	 *
	 * @param  string $id Entry identifier.
	 * 
	 * @return mixed      Service instance or parameter value.
	 */
	public function get($id)
	{
		$id = $this->resolveBinding($id);

		if (!isset($this->keys[$id]) && !$this->useAutowiring) {
			throw new NotFoundException($id);
		}

		if (isset($this->resolving[$id])) {
			throw new CyclicException(sprintf(
				'Cyclic dependency detected while resolving "%s"', $id
			));
		}

		$this->resolving[$id] = true;

		if (isset($this->keys[$id])) {
			$definition = $this->entries[$id];

			if (
				isset($this->resolved[$id])
				|| !$this->invokable($definition)
				|| isset($this->protected[$definition])
			) {
				$this->callGlobals($definition);
				return $definition;
			}

			$service = $definition($this);

			if (isset($this->factories[$definition])) {
				unset($this->resolving[$id]);
				$this->callGlobals($service);
				return $service;
			}
		} else {
			$autowiring = $this->autowiring ?: new ReflectionBasedAutowiring($this);
			$service = $autowiring->autowire($id);
		}

		$this->resolved[$id] = true;
		$this->entries[$id] = $service;

		unset($this->resolving[$id]);
		$this->callGlobals($service);

		return $service;
	}

	/**
	 * Check if the container contains an entry.
	 *
	 * We keep the keys array so that we can quickly
	 * lookup whether we have an entry or not.
	 * 
	 * @param  string  $id Entry identifier.
	 * 
	 * @return boolean     True if found, false otherwise.
	 */
	public function has($id)
	{
		$concrete = $this->resolveBinding($id);

		return isset($this->keys[$concrete]) || isset($this->bindings[$id]);
	}

	/**
	 * Add an entry.
	 *
	 * Cannot add entries that are already resolved
	 * and shared, you must remove that entry first.
	 *
	 * We add the entry to the keys array and the
	 * entries array.
	 * 
	 * @param string $id    Entry identifier.
	 * @param mixed $value  Entry definition.
	 */
	public function add($id, $value)
	{
		if (isset($this->resolved[$id])) {
			throw new ImmutableException($id);
		}

		$this->keys[$id] = true;
		$this->entries[$id] = $value;
	}

	/**
	 * Remove an entry.
	 *
	 * If the entry that is set is an object, that means that 
	 * it is a service that is already resolved, or that it is
	 * a service definition (invokable class or closure) or
	 * protected invokable.
	 *
	 * In that case we need to remove it from the factory and
	 * protected storages.
	 * 
	 * @param  string $id Entry identifier.
	 */
	public function remove($id)
	{
		$id = $this->resolveBinding($id);

		if (isset($this->keys[$id])) {
			if (($obj = $this->entries[$id]) && is_object($obj)) {
				unset($this->factories[$obj], $this->protected[$obj]);
			}

			unset(
				$this->entries[$id], $this->resolving[$id],
				$this->resolved[$id], $this->keys[$id]
			);
		}
	}

	/**
	 * Tag a service to always return a new instance.
	 *
	 * Stops the Container::get execution short by returning the 
	 * service before adding it to the resolved array, thereby making 
	 * the entry always return a new instance of the service.
	 * 
	 * @param  mixed $callback Invokable.
	 * 
	 * @return mixed           The original invokable.
	 */
	public function factory($callback)
	{
		if (!$this->invokable($callback)) {
			throw new ExpectedInvokableException(sprintf(
				'Invalid factory callback supplied'
			));
		}

		$this->factories->attach($callback);

		return $callback;
	}

	/**
	 * Tag an entry to be interpreted as a parameter.
	 *
	 * Since invokables are always treated as service definitions,
	 * this method exists to expose a method of treating an invokable
	 * as a literal value.
	 *
	 * This should be used in the case that you want to get the actual
	 * invokable object back rather than the object that it creates.
	 * 
	 * @param  mixed $callback Invokable.
	 * 
	 * @return mixed           The original invokable.
	 */
	public function protect($callback)
	{
		if (!$this->invokable($callback)) {
			throw new ExpectedInvokableException(sprintf(
				'Invalid protect callback supplied'
			));
		}

		$this->protected->attach($callback);

		return $callback;
	}

	/**
	 * Extends a service definition.
	 *
	 * Takes a callback that will be ran after a service
	 * is created.
	 *
	 * The callback will be passed the object instance and 
	 * an instance of the container. This method can be used 
	 * for setter injection.
	 *
	 * If the callback argument is left null and the id argument
	 * is an invokable, it will be treated as a global callback
	 * that should be ran on every resolve.
	 * 
	 * @param  string $id       Service entry identifier.
	 * @param  mixed  $callback Invokable.
	 * 
	 * @return void
	 */
	public function extend($id, $callback = null)
	{
		$id = $this->resolveBinding($id);

		if ($callback === null) {
			$callback = $id;
			$id = false;

			if (!$this->invokable($callback)) {
				throw new ExpectedInvokableException(sprintf(
					'Invalid extend callback supplied'
				));
			}

			return $this->globals[] = $callback;
		}

		if (!isset($this->keys[$id])) {
			throw new NotFoundException($id);
		}

		if (isset($this->resolving[$id])) {
			throw new ImmutableException(sprintf(
				'Cannot mutate "%s" while it\'s resolving', $id
			));
		}

		$definition = $this->entries[$id];

		if (!$this->invokable($definition)) {
			throw new ExpectedInvokableException(sprintf(
				'Cannot extend definition of a parameter or resolved entry'
			));
		}

		if (isset($this->protected[$definition])) {
			throw new ImmutableException(null, sprintf(
				'Cannot extend definition of a protected entry'
			));
		}

		if (!$this->invokable($callback)) {
			throw new ExpectedInvokableException(sprintf(
				'Invalid extend callback supplied'
			));
		}

		$callback = function($container) use ($callback, $definition, $id) {
			return $callback($definition($container), $container);
		};

		if (isset($this->factories[$definition])) {
			$this->factories->detach($definition);
			$this->factories->attach($callback);
		}

		return $this->add($id, $callback);
	}

	/**
	 * Retrieve all entry names.
	 * 
	 * @return array Entry names.
	 */
	public function keys()
	{
		return array_keys($this->keys);
	}

	/**
	 * Add a service provider.
	 *
	 * A service provider is a class that provides multiple
	 * entries to the container.
	 *
	 * These are often used for organizational purposes so that
	 * you may add a series of services and/or parameters that
	 * relate to each other to a container.
	 *
	 * The provider must implement the ServiceProvider interface,
	 * which simply exposes a register method that we use to pass
	 * an instance of the container so that entries may be added.
	 * 
	 * @param  ServiceProvider $provider Service provider class.
	 * 
	 * @return ContainerInterface        The container instance.        
	 */
	public function register(ServiceProvider $provider) : self
	{
		$provider->register($this);

		return $this;
	}

	public function bind($abstract, $concrete, $contextual = null)
	{
		$this->bindings[$abstract] = $concrete;	
	}

	public function useAutowiring(bool $boolean)
	{
		$this->useAutowiring = $boolean;
	}

	private function resolveBinding($abstract)
	{
		return $this->bindings[$abstract] ?? $abstract;
	}

	/**
	 * Checks whether a callback is considered
	 * invokable or not.
	 *
	 * This method is here to simply encapsulate
	 * this logic for later alteration.
	 * 
	 * @param  mixed $callback Invokable.
	 * 
	 * @return boolean         True if invokable, false otherwise.
	 */
	private function invokable($callback) : bool
	{
		if (is_callable($callback)) {
			return true;
		}

		return false;
	}

	/**
	 * Calls all global callbacks.
	 *
	 * Used multiple times in the Container::get method
	 * due to the fact that the method may return a result
	 * at many different points in its execution.
	 *
	 * @param mixed $value The resolved object or value.
	 * 
	 * @return void
	 */
	private function callGlobals($value = null)
	{
		if (!empty($this->globals)) {
			foreach ($this->globals as $callback) {
				$callback($value, $this);
			}
		}
	}

	/**
	 * ArrayAccess method.
	 * 
	 * @see DI\Container::has
	 */
	public function offsetExists($offset)
	{
		return $this->has($offset);
	}

	/**
	 * ArrayAccess method.
	 * 
	 * @see DI\Container::get
	 */
	public function offsetGet($offset)
	{
		return $this->get($offset);
	}

	/**
	 * ArrayAccess method.
	 * 
	 * @see DI\Container::add
	 */
	public function offsetSet($offset, $value)
	{
		return $this->add($offset, $value);
	}

	/**
	 * ArrayAccess method.
	 * 
	 * @see DI\Container::remove
	 */
	public function offsetUnset($offset)
	{
		return $this->remove($offset);
	}
}