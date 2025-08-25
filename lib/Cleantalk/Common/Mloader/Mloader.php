<?php

namespace Cleantalk\Common\Mloader;

class Mloader
{
	private static $common_namespace = '\Cleantalk\Common\\';
	private static $custom_namespace = '\Cleantalk\Custom\\';

    /**
     * Retrieves the fully qualified class name of a module.
     *
     * This method attempts to find and return the class name of a module
     * based on the provided module name. It first checks if the module
     * exists in the custom namespace and if it is a subclass of the common
     * namespace module. If not found, it checks the common namespace.
     *
     * @param string $module_name The name of the module to retrieve. It can include a namespace.
     * @return string The fully qualified class name of the module.
     * @throws \InvalidArgumentException If the module is not found or if the custom module is not a subclass of the common module.
     *
     * @example
     * // Retrieve a class from standard namespace \Cleantalk\Common\SimpleClass\SimpleClass or \Cleantalk\Custom\SimpleClass\SimpleClass
     * $class = Mloader::get('SimpleClass');
     *
     * @example
     * // Retrieve a class from provided namespace \Cleantalk\Common\Namespace\Class or \Cleantalk\Custom\Namespace\Class
     * $class = Mloader::get('Namespace\Class');
     */
	public static function get($module_name)
	{
        $namespace = $module_name;

        // Check if the module name contains a Namespace, get it
        if (strpos($module_name, '\\') !== false) {
            $module_name_arr = explode('\\', $module_name);
            if ( count ($module_name_arr) === 2 ) {
                $namespace = $module_name_arr[0];
                $module_name = $module_name_arr[1];
            }
        }

        $custom_class = self::$custom_namespace . $namespace . '\\' . $module_name;
        $common_class = self::$common_namespace . $namespace . '\\' . $module_name;

		if ( class_exists($custom_class) )
		{
			if( is_subclass_of($custom_class, $common_class) ) {
				return $custom_class;
			}
			throw new \InvalidArgumentException('Called module ' . $custom_class . ' must be inherited from ' . $common_class);
		}

		if( class_exists($common_class) )
		{
			return $common_class;
		}

		throw new \InvalidArgumentException('Called module ' . $module_name . ' not found.');
	}
}
