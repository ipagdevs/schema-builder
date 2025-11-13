<?php

namespace IpagDevs\Model\Schema;

use IpagDevs\Model\Schema\Exception\SchemaAttributeParseException;
use IpagDevs\Model\Schema\Exception\SchemaAttributeSerializeException;

class SchemaArrayAttribute extends SchemaAttribute
{
    protected ?SchemaAttribute $childrenSchema = null;

    public function __construct(Schema $schema, string $name, ?SchemaAttribute $childrenSchema = null)
    {
        parent::__construct($schema, $name);
        $this->childrenSchema = $childrenSchema;
    }

    public function parseContextual(mixed $value): mixed
    {
        if (is_iterable($value)) {
            if (!is_array($value)) {
                $value = iterator_to_array($value);
            }
        }

        if (is_array($value)) {
            return array_map(fn($v) => $this->childrenSchema?->parse($v), $value);
        }

        $arrayDefinition = $this->getArrayDefinitionString();
        throw new SchemaAttributeParseException($this, "Provided value is not a valid {$arrayDefinition}");
    }

    public function serialize(mixed $value): mixed
    {
        if (is_null($value)) {
            return null;
        }

        if (is_iterable($value)) {
            if (!is_array($value)) {
                $value = iterator_to_array($value);
            }

            return array_map(
                fn($serializable) => $this->childrenSchema ? $this->childrenSchema->serialize($serializable) : $serializable,
                $value
            );
        }

        throw new SchemaAttributeSerializeException($this, "Provided value is not a valid array to be serialized");
    }

    //

    private function getGenericDefinitionString(): string
    {
        return $this->childrenSchema ? $this->childrenSchema->getType() : '*';
    }

    private function getArrayDefinitionString(): string
    {
        $childrenType = $this->getGenericDefinitionString();
        return "array<{$childrenType}>";
    }

    //

    public static function from(Schema $schema, string $name, ?SchemaAttribute $childrenSchema = null): self
    {
        return new self($schema, $name, $childrenSchema);
    }
}
