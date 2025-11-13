<?php

namespace IpagDevs\Model\Schema;

use Closure;
use IpagDevs\Model\Model;
use IpagDevs\Model\Schema\Exception\SchemaAttributeParseException;

class SchemaAttribute
{
    protected Schema $schema;
    protected string $name;
    protected ?string $visibleName;
    protected bool $nullable;
    protected bool $hidden;
    protected ?Closure $hiddenCheck;
    protected mixed $default;
    protected bool $hasDefault;
    protected bool $required;

    public function __construct(Schema $schema, string $name)
    {
        $this->schema = $schema;
        $this->name = $name;
        $this->visibleName = null;
        $this->nullable = false;
        $this->hidden = false;
        $this->hiddenCheck = null;

        $this->default = null;
        $this->hasDefault = false;

        $this->required = false;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setVisibleName(string $name): self
    {
        $this->visibleName = $name;
        return $this;
    }

    public function getVisibleName(): string
    {
        return $this->visibleName ?? $this->getName();
    }

    public function getAbsoluteName(): string
    {
        return implode('.', [$this->getSchema()->getName(), $this->getVisibleName()]);
    }

    public function getType(): string
    {
        return basename(str_replace('\\', '/', get_class($this)));
    }

    public function getSchema(): Schema
    {
        return $this->schema;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function isHiddenIf(mixed $value, Model $model): bool
    {
        return $this->hiddenCheck?->__invoke($value, $model) ?? false;
    }

    public function hasDefault(): bool
    {
        return $this->hasDefault;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    //

    public function nullable(bool $value = true): self
    {
        $this->nullable = $value;
        return $this;
    }

    public function hidden(bool $value = true): self
    {
        $this->hidden = $value;
        return $this;
    }

    public function hiddenIf(callable $check): self
    {
        $this->hiddenCheck = $check instanceof Closure ? $check : Closure::fromCallable($check);
        return $this;
    }

    public function hiddenIfNull(): self
    {
        return $this->hiddenIf(static fn($value, Model $model) => is_null($value));
    }

    public function default(mixed $value): self
    {
        $this->default = $value;
        $this->hasDefault = true;
        return $this;
    }

    public function required(bool $default = true): self
    {
        $this->required = $default;

        return $this;
    }

    public function array(): SchemaArrayAttribute
    {
        return $this->schema->array($this->getName(), $this);
    }

    public function list(): SchemaArrayAttribute
    {
        return $this->array();
    }

    //

    public function parse(mixed $value): mixed
    {
        if (is_null($value) && $this->isNullable()) {
            return $value;
        }

        return $this->parseContextual($value);
    }

    public function parseContextual(mixed $value): mixed
    {
        return $value;
    }

    public function tryParse(mixed $value): mixed
    {
        try {
            return $this->parse($value);
        } catch (SchemaAttributeParseException $e) {
            return null;
        }
    }

    public function serialize(mixed $value): mixed
    {
        return $value;
    }

    public function copy(): self
    {
        return clone $this;
    }

    //

    public static function from(Schema $schema, string $name): self
    {
        return new static($schema, $name);
    }
}
