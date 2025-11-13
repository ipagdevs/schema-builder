<?php

namespace IpagDevs\Model\Schema\Exception;

class MutatorAttributeException extends MutatorException
{
    public function __construct(string $attribute, ?string $message = null)
    {
        $attributeName = $attribute;
        $message ??= "Failed to validate/mutate attribute";
        parent::__construct("'{$attributeName}' {$message}");
    }
}
