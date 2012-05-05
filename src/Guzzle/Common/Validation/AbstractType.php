<?php

namespace Guzzle\Common\Validation;

use Guzzle\Common\Exception\InvalidArgumentException;

/**
 * Ensures that a value is of a specific type
 */
abstract class  AbstractType extends AbstractConstraint
{
    protected static $typeMapping = array();
    protected $default = 'type';
    protected $required = 'type';

    /**
     * {@inheritdoc}
     */
    protected function validateValue($value, array $options = array())
    {
        $type = (string) $options['type'];

        if (!isset(static::$typeMapping[$type])) {
            throw new InvalidArgumentException("{$type} is not one "
                . 'the mapped types: ' . array_keys(self::$typeMapping));
        }

        if (!call_user_func(static::$typeMapping[$type], $value)) {
            return 'This value must be of type ' . $type;
        }

        return true;
    }
}
