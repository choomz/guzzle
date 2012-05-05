<?php

namespace Guzzle\Common\Validation;

/**
 * Ensures that a value is one of an array of choices
 */
class Choice implements ConstraintInterface
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, array $options = array())
    {
        if (isset($options['options'])) {
            $options = $options['options'];
        }

        if (!in_array($value, $options)) {
            return "{$value} is not one of: " . implode(', ', $options);
        }

        return true;
    }
}
