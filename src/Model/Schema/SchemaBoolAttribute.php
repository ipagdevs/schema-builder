<?php

namespace IpagDevs\Model\Schema;

use IpagDevs\Model\Schema\Exception\SchemaAttributeParseException;

class SchemaBoolAttribute extends SchemaAttribute
{
    /**
     * @var array<mixed>
     */
    protected array $positiveMatches;

    /**
     * @var array<mixed>
     */
    protected array $negativeMatches;

    public function __construct(Schema $schema, string $name)
    {
        parent::__construct($schema, $name);
        $this->positiveMatches = [];
        $this->negativeMatches = [];
    }

    /**
     * @param array<mixed> $matches
     * @return self
     */
    public function positives(array $matches): self
    {
        $this->positiveMatches = $matches;
        return $this;
    }

    /**
     * @param array<mixed> $matches
     * @return self
     */
    public function negatives(array $matches): self
    {
        $this->negativeMatches = $matches;
        return $this;
    }

    protected function isNegativeMatch(mixed $value): bool
    {
        return array_reduce($this->negativeMatches, fn($carry, $current) => $carry || $current == $value ? true : false, false);
    }

    protected function isPositiveMatch(mixed $value): bool
    {
        return array_reduce($this->positiveMatches, fn($carry, $current) => $carry || $current == $value ? true : false, false);
    }

    public function parseContextual(mixed $value): mixed
    {
        if (is_integer($value)) {
            return boolval($value);
        }

        if (is_bool($value)) {
            return $value;
        }

        if ($this->isNegativeMatch($value)) {
            return false;
        }

        if ($this->isPositiveMatch($value)) {
            return true;
        }

        throw new SchemaAttributeParseException($this, "Provided value '$value' is not a boolean");
    }
}
