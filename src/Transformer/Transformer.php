<?php

namespace NilPortugues\Api\Transformer;

use NilPortugues\Api\Mapping\Mapping;
use NilPortugues\Api\Transformer\Helpers\RecursiveDeleteHelper;
use NilPortugues\Api\Transformer\Helpers\RecursiveFilterHelper;
use NilPortugues\Api\Transformer\Helpers\RecursiveFormatterHelper;
use NilPortugues\Api\Transformer\Helpers\RecursiveRenamerHelper;
use NilPortugues\Serializer\Serializer;
use NilPortugues\Serializer\Strategy\StrategyInterface;

abstract class Transformer implements StrategyInterface
{
    /**
     * @var Mapping[]
     */
    protected $mappings = [];

    /**
     * @param array $apiMappings
     */
    public function __construct(array $apiMappings)
    {
        $this->mappings = $apiMappings;
    }

    /**
     * Represents the provided $value as a serialized value in string format.
     *
     * @param mixed $value
     *
     * @return string
     */
    abstract public function serialize($value);

    /**
     * Unserialization will fail. This is a transformer.
     *
     * @param string $value
     *
     * @throws TransformerException
     *
     * @return array
     */
    public function unserialize($value)
    {
        throw new TransformerException(sprintf('%s does not perform unserializations.', __CLASS__));
    }

    /**
     * Removes array keys matching the $unwantedKey array by using recursion.
     *
     * @param array $array
     * @param array $unwantedKey
     */
    protected function recursiveUnset(array &$array, array $unwantedKey)
    {
        RecursiveDeleteHelper::deleteKeys($array, $unwantedKey);
    }

    /**
     * Replaces the Serializer array structure representing scalar values to the actual scalar value using recursion.
     *
     * @param array $array
     */
    protected function recursiveSetValues(array &$array)
    {
        RecursiveFormatterHelper::formatScalarValues($array);
    }

    /**
     * Simplifies the data structure by removing an array level if data is scalar and has one element in array.
     *
     * @param array $array
     */
    protected function recursiveFlattenOneElementObjectsToScalarType(array &$array)
    {
        RecursiveFormatterHelper::flattenObjectsWithSingleKeyScalars($array);
    }

    /**
     * Renames a sets if keys for a given class using recursion.
     *
     * @param array  $array   Array with data
     * @param string $typeKey Scope to do the replacement.
     */
    protected function recursiveRenameKeyValue(array &$array, $typeKey)
    {
        RecursiveRenamerHelper::renameKeyValue($this->mappings, $array, $typeKey);
    }

    /**
     * Delete all keys except the ones considered identifier keys or defined in the filter.
     *
     * @param array $array
     * @param       $typeKey
     */
    protected function recursiveDeletePropertiesNotInFilter(array &$array, $typeKey)
    {
        RecursiveFilterHelper::deletePropertiesNotInFilter($this->mappings, $array, $typeKey);
    }

    /**
     * Removes a sets if keys for a given class using recursion.
     *
     * @param array  $array   Array with data
     * @param string $typeKey Scope to do the replacement.
     */
    protected function recursiveDeleteProperties(array &$array, $typeKey)
    {
        RecursiveDeleteHelper::deleteProperties($this->mappings, $array, $typeKey);
    }

    /**
     * Changes all array keys to under_score format using recursion.
     *
     * @param array $array
     */
    protected function recursiveSetKeysToUnderScore(array &$array)
    {
        $newArray = [];
        foreach ($array as $key => &$value) {
            $underscoreKey = $this->camelCaseToUnderscore($key);

            $newArray[$underscoreKey] = $value;
            if (is_array($value)) {
                $this->recursiveSetKeysToUnderScore($newArray[$underscoreKey]);
            }
        }
        $array = $newArray;
    }

    /**
     * Transforms a given string from camelCase to under_score style.
     *
     * @param string $camel
     * @param string $splitter
     *
     * @return string
     */
    protected function camelCaseToUnderscore($camel, $splitter = '_')
    {
        $camel = preg_replace(
            '/(?!^)[[:upper:]][[:lower:]]/',
            '$0',
            preg_replace('/(?!^)[[:upper:]]+/', $splitter.'$0', $camel)
        );

        return strtolower($camel);
    }

    /**
     * Array's type value becomes the key of the provided array using recursion.
     *
     * @param array $array
     */
    protected function recursiveSetTypeAsKey(array &$array)
    {
        if (is_array($array)) {
            foreach ($array as &$value) {
                if (!empty($value[Serializer::CLASS_IDENTIFIER_KEY])) {
                    $key = $value[Serializer::CLASS_IDENTIFIER_KEY];
                    unset($value[Serializer::CLASS_IDENTIFIER_KEY]);
                    $value = [$this->namespaceAsArrayKey($key) => $value];

                    $this->recursiveSetTypeAsKey($value);
                }
            }
        }
    }

    /**
     * Given a class name will return its name without the namespace and in under_score to be used as a key in an array.
     *
     * @param string $key
     *
     * @return string
     */
    protected function namespaceAsArrayKey($key)
    {
        $keys = explode('\\', $key);
        $className = end($keys);

        return $this->camelCaseToUnderscore($className);
    }
}