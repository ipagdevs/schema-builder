<?php

namespace IpagDevs\Model\Schema;

use IpagDevs\Model\Model;
use IpagDevs\Model\Schema\SchemaAttribute;
use IpagDevs\Model\Schema\Exception\MutatorAttributeException;

final class MutatorContext
{
    public Model $target;
    public string $attribute;
    public ?SchemaAttribute $attributeSchema;

    public function __construct(Model $target, string $attribute, ?SchemaAttribute $attributeSchema = null)
    {
        $this->target = $target;
        $this->attribute = $attribute;
        $this->attributeSchema = $attributeSchema;
    }

    public function raise(?string $message = null): void
    {
        $attributeAbsoluteName = $this->getAttributeAbsoluteName();
        throw new MutatorAttributeException($attributeAbsoluteName, $message);
    }

    public function assert(mixed $conditional, ?string $message = null): void
    {
        if (!$conditional) {
            $this->raise($message);
        }
    }

    public function getAttributeRelativeName(): string
    {
        return implode('.', [$this->target->getModelName(), $this->attribute]);
    }

    public function getAttributeAbsoluteName(): string
    {
        return $this->attributeSchema ? $this->attributeSchema->getAbsoluteName() : $this->getAttributeRelativeName();
    }

    public static function from(Model $target, string $attribute, ?SchemaAttribute $attributeSchema = null): self
    {
        return new self($target, $attribute, $attributeSchema);
    }
}
