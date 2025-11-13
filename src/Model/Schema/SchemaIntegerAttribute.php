<?php

namespace IpagDevs\Model\Schema;

use IpagDevs\Model\Schema\Exception\SchemaAttributeParseException;

class SchemaIntegerAttribute extends SchemaAttribute
{
    public function parseContextual(mixed $value): mixed
    {
        if (is_integer($value)) {
            return $value;
        }

        throw new SchemaAttributeParseException($this, "Provided value '$value' is not an integer");
    }
}
