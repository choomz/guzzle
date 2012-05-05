<?php

namespace Guzzle\Common\Validation;

/**
 * Ensures that a value is not blank
 */
class NotBlank implements ConstraintInterface
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, array $options = null)
    {
        if ($value === false || (empty($value) && $value !== '0')) {
            return 'Value cannot be blank';
        }

        return true;
    }
}
