<?php

namespace IpagDevs\Model\Schema\Exception;

use IpagDevs\Model\Schema\SchemaAttribute;

class SchemaAttributeParseException extends SchemaException
{
    public function __construct(SchemaAttribute $attribute, ?string $message = null)
    {
        $attributeName = $attribute->getAbsoluteName();
        $message ??= "Failed to parse attribute {$attribute->getName()}";
        parent::__construct("'{$attributeName}' {$message}");
    }
}
