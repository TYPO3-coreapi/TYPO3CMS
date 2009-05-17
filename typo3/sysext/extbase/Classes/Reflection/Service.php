<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Christopher Hlubek <hlubek@networkteam.com>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * A backport of the FLOW3 reflection service for aquiring reflection based information.
 * Most of the code is based on the FLOW3 reflection service.
 *
 * @package Extbase
 * @subpackage extbase
 * @version $Id$
 */
class Tx_Extbase_Reflection_Service implements t3lib_Singleton {

	/**
	 * List of tags which are ignored while reflecting class and method annotations
	 *
	 * @var array
	 */
	protected $ignoredTags = array('package', 'subpackage', 'license', 'copyright', 'author', 'version', 'const');

	/**
	 * @var array Array of array of method tags values by class name and method name
	 */
	protected $methodTagsValues;

	/**
	 * @var array Array of array of method parameters by class name and method name
	 */
	protected $methodParameters;
	
	/**
	 * Array of class names and names of their properties
	 *
	 * @var array
	 */
	protected $classPropertyNames = array();


	/**
	 * Returns the names of all properties of the specified class
	 *
	 * @param string $className Name of the class to return the property names of
	 * @return array An array of property names or an empty array if none exist
	 */
	public function getClassPropertyNames($className) {
		if (!isset($this->reflectedClassNames[$className])) $this->reflectClass($className);
		return (isset($this->classPropertyNames[$className])) ? $this->classPropertyNames[$className] : array();
	}

	/**
	 * Returns all tags and their values the specified method is tagged with
	 *
	 * @param string $className Name of the class containing the method
	 * @param string $methodName Name of the method to return the tags and values of
	 * @return array An array of tags and their values or an empty array of no tags were found
	 */
	public function getMethodTagsValues($className, $methodName) {
		if (!isset($this->methodTagsValues[$className][$methodName])) {
			$this->methodTagsValues[$className][$methodName] = array();
			$method = $this->getMethodReflection($className, $methodName);
			foreach ($method->getTagsValues() as $tag => $values) {
				if (array_search($tag, $this->ignoredTags) === FALSE) {
					$this->methodTagsValues[$className][$methodName][$tag] = $values;
				}
			}
		}
		return $this->methodTagsValues[$className][$methodName];
	}


	/**
	 * Returns an array of parameters of the given method. Each entry contains
	 * additional information about the parameter position, type hint etc.
	 *
	 * @param string $className Name of the class containing the method
	 * @param string $methodName Name of the method to return parameter information of
	 * @return array An array of parameter names and additional information or an empty array of no parameters were found
	 */
	public function getMethodParameters($className, $methodName) {
		if (!isset($this->methodParameters[$className][$methodName])) {
			$method = $this->getMethodReflection($className, $methodName);
			$this->methodParameters[$className][$methodName] = array();
			foreach($method->getParameters() as $parameterPosition => $parameter) {
				$this->methodParameters[$className][$methodName][$parameter->getName()] = $this->convertParameterReflectionToArray($parameter, $parameterPosition, $method);
			}
		}
		return $this->methodParameters[$className][$methodName];
	}

	/**
	 * Returns all tags and their values the specified class property is tagged with
	 *
	 * @param string $className Name of the class containing the property
	 * @param string $propertyName Name of the property to return the tags and values of
	 * @return array An array of tags and their values or an empty array of no tags were found
	 */
	public function getPropertyTagsValues($className, $propertyName) {
		if (!isset($this->reflectedClassNames[$className])) $this->reflectClass($className);
		if (!isset($this->propertyTagsValues[$className])) return array();
		return (isset($this->propertyTagsValues[$className][$propertyName])) ? $this->propertyTagsValues[$className][$propertyName] : array();
	}

	/**
	 * Reflects the given class and stores the results in this service's properties.
	 *
	 * @param string $className Full qualified name of the class to reflect
	 * @return void
	 */
	protected function reflectClass($className) {
		$class = new Tx_Extbase_Reflection_ClassReflection($className);
		$this->reflectedClassNames[$className] = time();

		foreach ($class->getTagsValues() as $tag => $values) {
			if (array_search($tag, $this->ignoredTags) === FALSE) {
				$this->taggedClasses[$tag][] = $className;
				$this->classTagsValues[$className][$tag] = $values;
			}
		}

		foreach ($class->getProperties() as $property) {
			$propertyName = $property->getName();
			$this->classPropertyNames[$className][] = $propertyName;

			foreach ($property->getTagsValues() as $tag => $values) {
				if (array_search($tag, $this->ignoredTags) === FALSE) {
					$this->propertyTagsValues[$className][$propertyName][$tag] = $values;
				}
			}
		}

		foreach ($class->getMethods() as $method) {
			$methodName = $method->getName();
			foreach ($method->getTagsValues() as $tag => $values) {
				if (array_search($tag, $this->ignoredTags) === FALSE) {
					$this->methodTagsValues[$className][$methodName][$tag] = $values;
				}
			}

			foreach ($method->getParameters() as $parameter) {
				$this->methodParameters[$className][$methodName][$parameter->getName()] = $this->convertParameterReflectionToArray($parameter, $method);
			}
		}
		ksort($this->reflectedClassNames);
	}
	
	/**
	 * Converts the given parameter reflection into an information array
	 *
	 * @param ReflectionParameter $parameter The parameter to reflect
	 * @return array Parameter information array
	 */
	protected function convertParameterReflectionToArray(ReflectionParameter $parameter, $parameterPosition, ReflectionMethod $method = NULL) {
		$parameterInformation = array(
			'position' => $parameterPosition,
			'byReference' => $parameter->isPassedByReference() ? TRUE : FALSE,
			'array' => $parameter->isArray() ? TRUE : FALSE,
			'optional' => $parameter->isOptional() ? TRUE : FALSE,
			'allowsNull' => $parameter->allowsNull() ? TRUE : FALSE
		);

		$parameterClass = $parameter->getClass();
		$parameterInformation['class'] = ($parameterClass !== NULL) ? $parameterClass->getName() : NULL;
		if ($parameter->isDefaultValueAvailable()) {
			$parameterInformation['defaultValue'] = $parameter->getDefaultValue();
		}
		if ($parameterClass !== NULL) {
			$parameterInformation['type'] = $parameterClass->getName();
		} elseif ($method !== NULL) {
			$methodTagsAndValues = $this->getMethodTagsValues($method->getDeclaringClass()->getName(), $method->getName());
			if (isset($methodTagsAndValues['param']) && isset($methodTagsAndValues['param'][$parameterPosition])) {
				$explodedParameters = explode(' ', $methodTagsAndValues['param'][$parameterPosition]);
				if (count($explodedParameters) >= 2) {
					$parameterInformation['type'] = $explodedParameters[0];
				}
			}
		}
		if (isset($parameterInformation['type']) && $parameterInformation['type']{0} === '\\') {
			$parameterInformation['type'] = substr($parameterInformation['type'], 1);
		}
		return $parameterInformation;
	}

	protected function getClassReflection($className) {
		if (!isset($this->classReflections[$className])) {
			$this->classReflections[$className] = new Tx_Extbase_Reflection_ClassReflection($className);
		}
		return $this->classReflections[$className];
	}

	protected function getMethodReflection($className, $methodName) {
		if (!isset($this->methodReflections[$className][$methodName])) {
			$this->methodReflections[$className][$methodName] = new Tx_Extbase_Reflection_MethodReflection($className, $methodName);
		}
		return $this->methodReflections[$className][$methodName];
	}
}
?>