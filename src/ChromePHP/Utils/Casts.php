<?php
/**
 * ChromePHP - Casts.php
 * Created by: Eric Draken
 * Date: 2017/10/5
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Utils;

use Draken\ChromePHP\Exceptions\RuntimeException;
use ReflectionClass;

class Casts
{
	/**
	 * Translates type
	 * @param $destination Object destination
	 * @param \stdClass $source Source
	 * @see https://stackoverflow.com/a/17697925/1938889
	 */
	public static function cast( &$destination, \stdClass $source )
	{
		$destReflection = new \ReflectionObject( $destination );
		$sourceReflection = new \ReflectionObject( $source );

		$sourceProperties = $sourceReflection->getProperties();
		foreach ( $sourceProperties as $sourceProperty )
		{
			$name = $sourceProperty->getName();
			$destProperty = $destReflection->getProperty( $name );
			$destProperty->setAccessible(true);

			if ( gettype( $destProperty->getValue( $destination ) ) == "object" )
			{
				self::cast( $destProperty->getValue( $destination ), $source->$name );
			}
			else if ( property_exists( $destination, $name ) )
			{
				$destProperty->setValue( $destination, $source->$name );
			}
			else
			{
				throw new RuntimeException( "Property '$name' not found" );
			}
		}
	}

	/**
	 * Create a new object from a given object
	 * @param string $classname
	 * @param \stdClass $source
	 *
	 * @return \stdClass
	 */
	public static function create( string $classname, \stdClass $source )
	{
		$obj = (new ReflectionClass( $classname ))->newInstanceWithoutConstructor();
		self::cast( $obj, $source );
		return $obj;
	}
}