<?php

namespace IpagDevs\Model\Schema;

use DateTimeInterface;
use IpagDevs\Model\Model;
use IpagDevs\Model\Schema\SchemaAttribute;
use IpagDevs\Model\Schema\SchemaBoolAttribute;
use IpagDevs\Model\Schema\SchemaDateAttribute;
use IpagDevs\Model\Schema\SchemaStringAttribute;
use IpagDevs\Model\Schema\SchemaIntegerAttribute;
use IpagDevs\Model\Schema\SchemaRelationAttribute;

final class Schema
{
    /**
     * @var array<mixed>
     */
    protected array $props;

    protected ?string $name;

    public function __construct(?string $name = null)
    {
        $this->props = [];
        $this->name = $name;
    }

    public function any(string $attribute): SchemaAttribute
    {
        return $this->set(SchemaAttribute::from($this, $attribute));
    }

    public function int(string $attribute): SchemaIntegerAttribute
    {
        return $this->set(SchemaIntegerAttribute::from($this, $attribute));
    }

    public function string(string $attribute): SchemaStringAttribute
    {
        return $this->set(SchemaStringAttribute::from($this, $attribute));
    }

    public function date(string $attribute, string $format = DateTimeInterface::RFC3339): SchemaDateAttribute
    {
        return $this->set(SchemaDateAttribute::from($this, $attribute)->format($format));
    }

    public function bool(string $attribute): SchemaBoolAttribute
    {
        return $this->set(SchemaBoolAttribute::from($this, $attribute));
    }

    /**
     * @param string $attribute
     * @param array<mixed> $values
     * @return SchemaEnumAttribute
     */
    public function enum(string $attribute, array $values): SchemaEnumAttribute
    {
        return $this->set(SchemaEnumAttribute::from($this, $attribute)->values($values));
    }

    public function float(string $attribute): SchemaFloatAttribute
    {
        return $this->set(SchemaFloatAttribute::from($this, $attribute));
    }

    public function has(string $attribute, string $class = Model::class): SchemaRelationAttribute
    {
        return $this->set(SchemaRelationAttribute::from($this, $attribute, $class));
    }

    public function hasMany(string $attribute, string $class = Model::class): SchemaRelationAttribute
    {
        return $this->set(SchemaRelationAttribute::from($this, $attribute, $class)->many());
    }

    public function array(string $attribute, ?SchemaAttribute $schema = null): SchemaArrayAttribute
    {
        return $this->set(SchemaArrayAttribute::from($this, $attribute, $schema));
    }

    //

    public function builder(): SchemaBuilder
    {
        return SchemaBuilder::from($this);
    }

    public function query(string $attribute): ?SchemaAttribute
    {
        return $this->props[$attribute] ?? null;
    }

    /**
     * @return iterable<mixed>
     */
    public function getAttributes(): iterable
    {
        return $this->props;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    //

    protected function set(SchemaAttribute $schemaAttribute): mixed
    {
        $this->props[$schemaAttribute->getName()] = $schemaAttribute;
        return $schemaAttribute;
    }

    protected function unset(SchemaAttribute $schemaAttribute): mixed
    {
        unset($this->props[$schemaAttribute->getName()]);
        return $schemaAttribute;
    }
}
