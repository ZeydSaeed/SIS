<?php

namespace App\Security\Validation;

use DateTime;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Validator;
use Throwable;

/**
 * The framework validator, hardened for hostile input: the date rules (`after`, `before`, `date_format`, …)
 * compare a field with another field and pass raw request values to Carbon / DateTime, which throw a
 * TypeError / ValueError (HTTP 500) for arrays, integers beyond range or strings holding NUL bytes.
 * Here such a value is simply "not a date" — the rule fails with its normal 422 message.
 */
final class RobustValidator extends Validator
{
    protected function getDateTime($value)
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }
        if (is_string($value) && str_contains($value, "\0")) {
            return null;
        }

        try {
            return @Date::parse($value) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function getDateTimeWithOptionalFormat($format, $value)
    {
        if (! is_string($value) || str_contains($value, "\0")) {
            return null;
        }

        try {
            if ($date = DateTime::createFromFormat('!'.$format, $value)) {
                return $date;
            }
        } catch (Throwable) {
            return null;
        }

        return $this->getDateTime($value);
    }

    public function validateDate($attribute, $value)
    {
        if (is_string($value) && str_contains($value, "\0")) {
            return false;
        }

        try {
            return parent::validateDate($attribute, $value);
        } catch (Throwable) {
            return false;
        }
    }

    public function validateDateFormat($attribute, $value, $parameters)
    {
        if (is_string($value) && str_contains($value, "\0")) {
            return false;
        }

        try {
            return parent::validateDateFormat($attribute, $value, $parameters);
        } catch (Throwable) {
            return false;
        }
    }
}
