<?php

namespace DI\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class AutowiringException extends Exception implements ContainerExceptionInterface
{
	
}