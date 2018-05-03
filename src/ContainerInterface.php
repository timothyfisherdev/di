<?php

namespace DI;

use ArrayAccess;
use Psr\Container\ContainerInterface as PsrContainerInterface;

/**
 * The interface that a custom container using this container package 
 * must implement in order to properly work.
 *
 * PSR-11 compliant with array access.
 */
interface ContainerInterface extends PsrContainerInterface, ArrayAccess
{
	/**
	 * Add an entry.
	 */
	public function add($id, $value);

	/**
	 * Remove an entry.
	 */
	public function remove($id);

	/**
	 * Tag a service to always return a new instance.
	 */
	public function factory($callback);

	/**
	 * Interpret an invokable as a literal value.
	 */
	public function protect($callback);

	/**
	 * Invokable to be ran after a service is created.
	 */
	public function extend($id, $callback = null);

	/**
	 * Get a list of entry names in the container.
	 */
	public function keys();

	/**
	 * Add a service provider class that will add entries to the container.
	 */
	public function register(ServiceProvider $provider);
}