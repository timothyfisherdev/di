<?php

namespace DI\Test;

use StdClass;
use DI\Container;
use DI\ServiceProvider;
use PHPUnit\Framework\TestCase;

/**
 * Container testing.
 */
class ContainerTest extends TestCase
{
	/**
	 * Provides sample entries.
	 */
	public function entriesProvider()
	{
		return [
			[['foo' => 'bar', 'baz' => function() {
				return new StdClass();
			}]]
		];
	}

	/**
	 * Provides an invokable class.
	 */
	public function invokableProvider()
	{
		return [
			[new class {
				public function __invoke()
				{
					return new StdClass();
				}
			}]
		];
	}

	/**
	 * Test that a container can be instantiated
	 * with an array of entries.
	 * 
	 * @dataProvider entriesProvider
	 */
	public function testInstantiateWithEntries($entries)
	{
		$container = new Container($entries);

		$this->assertAttributeCount(2, 'entries', $container);
	}

	/**
	 * Test that the container can check whether
	 * or not it has entries.
	 * 
	 * @dataProvider entriesProvider
	 */
	public function testHas($entries)
	{
		$container = new Container($entries);

		$this->assertTrue($container->has('foo'));
		$this->assertTrue($container->has('baz'));
	}

	/**
	 * Test the ability to add entries to the container.
	 * 
	 * @dataProvider entriesProvider
	 */
	public function testAdd($entries)
	{
		$container = new Container();

		foreach ($entries as $id => $value) {
			$container->add($id, $value);
		}

		$this->assertTrue($container->has('foo'));
		$this->assertTrue($container->has('baz'));
	}

	/**
	 * Test that you cannot add an already resolved
	 * entry to the container. You must remove it
	 * first.
	 * 
	 * @dataProvider entriesProvider
	 * @expectedException DI\Exception\ImmutableException
	 */
	public function testAddResolvedEntry($entries)
	{
		$container = new Container($entries);
		
		$obj = $container->get('baz');
		$this->assertInstanceOf(StdClass::class, $obj);

		$container->add('baz', 'bim');
	}

	/**
	 * Test that you can add a null value to the container
	 * to be interpreted as a parameter.
	 */
	public function testAddNullValue()
	{
		$container = new Container();
		$container->add('foo', null);

		$this->assertEquals(null, $container->get('foo'));
	}

	/**
	 * Test that you can get all entry names.
	 * 
	 * @dataProvider entriesProvider
	 */
	public function testGetAllEntryKeys($entries)
	{
		$container = new Container($entries);

		$this->assertEquals(['foo', 'baz'], $container->keys());
	}

	/**
	 * Test that you can register a service provider
	 * and get back the container.
	 */
	public function testRegisterServiceProvider()
	{
		$provider = $this->createMock(ServiceProvider::class);

		$container = new Container();
		$container = $container->register($provider);

		$this->assertInstanceOf(Container::class, $container);
	}

	/**
	 * Test that an exception is thrown when getting
	 * an entry that does not exist.
	 * 
	 * @expectedException DI\Exception\NotFoundException
	 */
	public function testGetNotFoundEntry()
	{
		$container = new Container();
		$container->get('foo');
	}

	/**
	 * Test that an exception is thrown when attempting
	 * to get an entry that is currently being resolved.
	 * 
	 * @expectedException DI\Exception\CyclicException
	 */
	public function testGetResolvingEntry()
	{
		$container = new Container();
		$container->add('foo', function($container) {
			return $container->get('foo');
		});
		$container->get('foo');
	}

	/**
	 * Test that can get services and parameters from the container
	 * correctly.
	 * 
	 * @dataProvider entriesProvider
	 */
	public function testGetParameterAndServiceEntries($entries)
	{
		$container = new Container($entries);

		$this->assertEquals('bar', $container->get('foo'));
		$this->assertInstanceOf(StdClass::class, $container->get('baz'));
	}

	/**
	 * Test that we can get a protected entry in the container
	 * that is interpreted as a literal value.
	 * 
	 * @dataProvider entriesProvider
	 */
	public function testGetProtectedEntry($entries)
	{
		$container = new Container();
		$container->add('baz', $container->protect($entries['baz']));

		$this->assertSame($entries['baz'], $container->get('baz'));
	}

	/**
	 * Test that we can get an entry from the container that
	 * is marked as a factory.
	 * 
	 * @dataProvider entriesProvider
	 */
	public function testGetFactoryService($entries)
	{
		$container = new Container();
		$container->add('baz', $container->factory($entries['baz']));

		$this->assertInstanceOf(StdClass::class, $container->get('baz'));
	}

	/**
	 * Test that, by default, the container will "share" service
	 * instances.
	 * 
	 * @dataProvider entriesProvider
	 */
	public function testGetSharesInstanceByDefault($entries)
	{
		$container = new Container($entries);

		$obj1 = $container->get('baz');
		$obj1->value = 42;
		$this->assertInstanceOf(StdClass::class, $obj1);

		$obj2 = $container->get('baz');
		$this->assertInstanceOf(StdClass::class, $obj2);

		$this->assertEquals(42, $obj2->value);
	}

	/**
	 * Test that, if a service is marked as a factory, the container
	 * will return a new instance of the service every time it is
	 * resolved.
	 * 
	 * @dataProvider entriesProvider
	 */
	public function testGetFactoryReturnsNewInstance($entries)
	{
		$container = new Container($entries);
		$container->add('baz', $container->factory($entries['baz']));

		$obj1 = $container->get('baz');
		$obj1->value = 5;
		$this->assertInstanceOf(StdClass::class, $obj1);

		$obj2 = $container->get('baz');
		$this->assertInstanceOf(StdClass::class, $obj2);

		$this->assertNotSame($obj1, $obj2);
		$this->assertFalse(isset($obj2->value));
	}

	/**
	 * Test that we can get a service via an invokable class.
	 * 
	 * @dataProvider invokableProvider
	 */
	public function testGetViaInvokeMethod($invokable)
	{
		$container = new Container();
		$container->add('foo', $invokable);

		$this->assertInstanceOf(StdClass::class, $container->get('foo'));
	}

	/**
	 * Test that we can remove entries.
	 * 
	 * @dataProvider entriesProvider
	 */
	public function testRemoveServiceEntry($entries)
	{
		$container = new Container($entries);

		$container->get('baz');
		$this->assertAttributeCount(1, 'resolved', $container);

		$container->remove('baz');

		$this->assertFalse($container->has('baz'));
		$this->assertAttributeCount(1, 'entries', $container);
		$this->assertAttributeCount(0, 'resolved', $container);
	}

	/**
	 * Test that can can remove services marked as factories.
	 * 
	 * @dataProvider entriesProvider
	 */
	public function testRemoveServiceFactoryEntry($entries)
	{
		$container = new Container();
		$container->add('baz', $container->factory($entries['baz']));

		$this->assertTrue($container->has('baz'));
		$this->assertAttributeCount(1, 'factories', $container);

		$container->remove('baz');

		$this->assertFalse($container->has('baz'));
		$this->assertAttributeCount(0, 'factories', $container);
	}

	/**
	 * Test that we can also remove protected entries.
	 * 
	 * @dataProvider entriesProvider
	 */
	public function testRemoveProtectedEntry($entries)
	{
		$container = new Container();
		$container->add('baz', $container->protect($entries['baz']));

		$this->assertTrue($container->has('baz'));
		$this->assertAttributeCount(1, 'protected', $container);
		
		$container->remove('baz');

		$this->assertFalse($container->has('baz'));
		$this->assertAttributeCount(0, 'protected', $container);
	}

	/**
	 * Test that callbacks marked as global get called correctly
	 * on every resolve.
	 * 
	 * @dataProvider entriesProvider
	 */
	public function testCallGlobalExtendCallbackOnEveryResolve($entries)
	{
		$container = new Container($entries);
		$container->counter = 1;
		$container->extend(function($resolved, $container) {
			if (is_object($resolved)) {
				$resolved->container = $container;
			}
			$container->counter++;
		});
		$foo = $container->get('foo');
		$baz = $container->get('baz');

		$this->assertAttributeEquals(3, 'counter', $container);
		$this->assertInstanceOf(Container::class, $baz->container);
	}

	/**
	 * Test that we cannot extend a non-invokable callback.
	 * 
	 * @expectedException DI\Exception\ExpectedInvokableException
	 */
	public function testGlobalExtendWithNonInvokableCallback()
	{
		$container = new Container();
		$container->extend(42);
	}

	/**
	 * Test that we cannot extend a non-existent entry.
	 * 
	 * @expectedException DI\Exception\NotFoundException
	 */
	public function testExtendNonExistantEntry()
	{
		$container = new Container();
		$container->extend('foo', function() {});
	}

	/**
	 * Test that we cannot extend an entry currently being resolved.
	 * 
	 * @expectedException DI\Exception\ImmutableException
	 */
	public function testExtendResolvingEntry()
	{
		$container = new Container();
		$container->add('foo', function() use ($container) {
			$container->extend('foo', function() {});
		});
		$container->get('foo');
	}

	/**
	 * Test that we cannot extend a parameter definition.
	 * 
	 * @dataProvider entriesProvider
	 * @expectedException DI\Exception\ExpectedInvokableException
	 */
	public function testExtendNonServiceEntry($entries)
	{
		$container = new Container($entries);
		$container->extend('foo', function() {});
	}

	/**
	 * Test that we cannot extend an already resolved entry.
	 * 
	 * @dataProvider entriesProvider
	 * @expectedException DI\Exception\ExpectedInvokableException
	 */
	public function testExtendResolvedEntry($entries)
	{
		$container = new Container($entries);
		$container->get('baz');
		$container->extend('baz', function() {});
	}

	/**
	 * Test that we cannot extend a protected definition
	 * because it is considered a parameter at that point.
	 * 
	 * @dataProvider entriesProvider
	 * @expectedException DI\Exception\ImmutableException
	 */
	public function testExtendProtectedEntry($entries)
	{
		$container = new Container();
		$container->add('baz', $container->protect($entries['baz']));
		$container->extend('baz', function() {});
	}

	/**
	 * Test that we cannot extend with a non-invokable callback.
	 * 
	 * @dataProvider entriesProvider
	 * @expectedException DI\Exception\ExpectedInvokableException
	 */
	public function testExtendWithNonInvokableCallback($entries)
	{
		$container = new Container($entries);
		$container->extend('baz', 42);
	}

	/**
	 * Test that when we extend a service that is marked as a factory,
	 * that the factory array updates.
	 * 
	 * @dataProvider entriesProvider
	 */
	public function testExtendFactoryUpdatesFactoryDefinition($entries)
	{
		$container = new Container();
		$container->add('baz', $container->factory($entries['baz']));
		$container->extend('baz', function($obj) {
			$obj->value = 42;
			return $obj;
		});
		$obj = $container->get('baz');

		$this->assertEquals(42, $obj->value);
	}

	/**
	 * Test that when extending a definition, the callback is passed 
	 * the object instance and the container instance.
	 * 
	 * @dataProvider entriesProvider
	 */
	public function testExtendPassesObjectInstanceAndContainer($entries)
	{
		$container = new Container();
		$container->add('baz', $container->factory($entries['baz']));
		$container->extend('baz', function($obj, $container) {
			return [$obj, $container];
		});
		$array = $container->get('baz');

		$this->assertInstanceOf(StdClass::class, $array[0]);
		$this->assertInstanceOf(Container::class, $array[1]);
	}

	/**
	 * Test that we can access the container entries like an array.
	 */
	public function testArrayAccess()
	{
		$container = new Container();

		$container['foo'] = 'bar';
		$this->assertTrue(isset($container['foo']));

		$foo = $container['foo'];
		$this->assertEquals('bar', $foo);

		unset($container['foo']);
		$this->assertFalse(isset($container['foo']));
	}
}