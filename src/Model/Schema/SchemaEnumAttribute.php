<?php

namespace IpagDevs\Model\Schema;

use IpagDevs\Model\Schema\Exception\SchemaAttributeParseException;

class SchemaEnumAttribute extends SchemaAttribute
{
    /**
     * @var array<mixed>
     */
    protected array $values;

    public function __construct(Schema $schema, string $name)
    {
        parent::__construct($schema, $name);
        $this->values = [];
    }

    /**
     * @param array<mixed> $values
     * @return self
     */
    public function values(array $values): self
    {
        $this->values = $values;
        return $this;
    }

    private function matchesLoose(mixed $value): mixed
    {
        return array_reduce($this->values, fn($x, $y) => $x ?? ($y == $value ? $y : null), null);
    }

    public function matchesStrict(mixed $value): mixed
    {
        return array_reduce($this->values, fn($x, $y) => $x ?? ($y === $value ? $y : null), null);
    }

    public function parseContextual(mixed $value): mixed
    {
        $value = $this->matchesLoose($value);
        if (!is_null($value)) {
            return $value;
        }

        $many = $this->getValuesVerbose();
        throw new SchemaAttributeParseException($this, "Provided value is not one of {$many}");
    }

    private function getValuesVerbose(): string
    {
        return implode(', ', $this->values);
    }

    public static function from(Schema $schema, string $name): self
    {
        return new self($schema, $name);
    }
}
