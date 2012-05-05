<?php

namespace Guzzle\Common\Validation;

/**
 * Ensures that a value is blank
 */
class Blank implements ConstraintInterface
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, array $options = null)
    {
        if ($value !== '' && $value !== null) {
            return "{$value} is not blank";
        }

        return true;
    }
}
