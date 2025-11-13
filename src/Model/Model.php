<?php

namespace IpagDevs\Model;

use Closure;
use UnexpectedValueException;
use IpagDevs\Model\Schema\Schema;
use IpagDevs\Model\Schema\Mutator;
use IpagDevs\Model\Schema\SchemaBuilder;
use IpagDevs\Model\Schema\MutatorContext;
use IpagDevs\Model\Schema\SchemaAttribute;
use IpagDevs\Model\SerializableModelInterface;
use IpagDevs\Model\Schema\SchemaRelationAttribute;
use IpagDevs\Model\Schema\Exception\MutatorAttributeException;
use IpagDevs\Model\Schema\Exception\SchemaAttributeParseException;

abstract class Model implements SerializableModelInterface
{
    private Schema $schema;

    /**
     * @var array<mixed>
     */
    private array $data;

    /**
     * @var array<mixed>
     */
    private array $relations;

    private string $name;

    /**
     * @var array<mixed>
     */
    private array $mutators;

    /**
     * @var array<mixed>
     */
    protected static array $globalSchema;

    /**
     * @param array<mixed> $data
     * @param array<mixed> $relations
     * @param string|null $name
     */
    public function __construct(array $data = [], array $relations = [], ?string $name = null)
    {
        $this->data = $data;
        $this->relations = $relations;
        $this->name = $name ?? $this->useDefaultName();

        $this->mutators = [];

        $this->schema = $this->useContextualSchema();
        $this->schemaLoadDefaults();
    }

    //

    private function useDefaultName(): string
    {
        return basename(str_replace('\\', '/', get_class($this)));
    }

    private function useContextualSchema(): Schema
    {
        $schema = static::$globalSchema[static::class] ?? null;
        if (is_null($schema)) {
            $schema = static::$globalSchema[static::class] = new Schema($this->name);
            $this->schema($schema->builder());
        }
        return $schema;
    }

    protected abstract function schema(SchemaBuilder $schema): Schema;

    //

    public function getModelName(): string
    {
        return $this->name;
    }

    public function setModelName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function setSchemaName(string $name): self
    {
        $this->schema->setName($name);
        return $this;
    }

    /**
     * @param array<mixed> $data
     * @return static
     */
    public function fill(array $data): static
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }

        foreach ($this->schema->getAttributes() as $attr) {
            if ($attr->isRequired() && !array_key_exists($attr->getName(), $data)) {
                throw new SchemaAttributeParseException($attr, "Missing required attribute");
            }
        }

        return $this;
    }

    public function set(string $attribute, mixed $value): self
    {
        $attributeSchema = $this->schema->query($attribute);
        $mutator = $this->loadMutator($attribute);

        $value = $mutator && $mutator->setter ? $mutator->setter->__invoke($value, new MutatorContext($this, $attribute, $attributeSchema)) : $value;

        if ($attributeSchema) {
            if ($attributeSchema instanceof SchemaRelationAttribute) {
                return $this->setRelation($attribute, $attributeSchema->parse($value));
            }

            return $this->setRawAttribute($attribute, $attributeSchema->parse($value));
        }

        return $this->setRawAttribute($attribute, $value);
    }

    public function get(string $attribute): mixed
    {
        $attributeSchema = $this->schema->query($attribute);

        if ($attributeSchema && $attributeSchema instanceof SchemaRelationAttribute) {
            return $this->getRelation($attribute);
        }

        $value = $this->getRawAttribute($attribute);
        $mutator = $this->loadMutator($attribute);

        return $mutator && $mutator->getter ? $mutator->getter->__invoke($value, new MutatorContext($this, $attribute, $attributeSchema)) : $value;
    }

    public function jsonSerialize(): array
    {
        $serialized = [];

        foreach ($this->schema->getAttributes() as $schema) {
            /** @var SchemaAttribute $schema */
            if ($schema->isHidden() || $schema->isHiddenIf($schema->serialize($this->get($schema->getName())), $this)) {
                continue;
            }

            $serialized[$schema->getName()] = $schema->serialize($this->get($schema->getName()));
        }

        return $serialized;
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $mapper = static function (Closure $mapper, array $items): array {
            return array_map(
                static fn($item) => is_iterable($item) ? $mapper($mapper, $item) : $item->toArray(),
                $items
            );
        };

        return array_merge(
            $this->getAttributes(),
            $mapper($mapper, $this->getRelations())
        );
    }

    //

    /**
     * @return array<mixed>
     */
    public function getAttributes(): array
    {
        return $this->data;
    }

    /**
     * @return array<mixed>
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * @return array<mixed>
     */
    public function getAllAttributes(): array
    {
        return array_merge($this->data, $this->relations);
    }

    /**
     * @return array<mixed>
     */
    protected function getRawAttribute(string $name)
    {
        return $this->data[$name] ?? null;
    }

    protected function setRawAttribute(string $name, mixed $value): self
    {
        $this->data[$name] = $value;
        return $this;
    }

    /**
     * @param array<mixed> $values
     * @return self
     */
    protected function setRawAttributes(array $values): self
    {
        foreach ($values as $name => $value) {
            $this->setRawAttribute($name, $value);
        }

        return $this;
    }

    protected function setRelation(string $name, mixed $value): self
    {
        $this->relations[$name] = $value;
        return $this;
    }

    protected function getRelation(string $name): mixed
    {
        return $this->relations[$name] ?? null;
    }

    protected function schemaLoadDefaults(): void
    {
        foreach ($this->schema->getAttributes() as $schema) {
            /** @var SchemaAttribute $schema */
            if ($schema->hasDefault()) {
                $this->set($schema->getName(), $schema->getDefault());
            }
        }
    }

    protected function loadMutator(string $name): ?Mutator
    {
        $mutator = $this->mutators[$name] ?? null;

        if (!$mutator && is_callable([$this, $name])) {
            $mutator = $this->$name();

            if (!$mutator instanceof Mutator) {
                throw new UnexpectedValueException("Expected value from mutator '$name' to be instance of Mutator");
            } else {
                $this->mutators[$name] = $mutator;
            }
        }

        return $mutator;
    }

    protected function schemaMutate(Closure $callback): void
    {
        if ($this->schema === (static::$globalSchema[static::class] ?? null)) {
            $this->schema = clone $this->schema;
        }

        $callback($this->schema->builder());
    }

    //

    public static function parse(array $data): static
    {
        return self::make()->fill($data);
    }

    public static function tryParse(array $data): ?static
    {
        try {
            return static::parse($data);
        } catch (SchemaAttributeParseException | MutatorAttributeException $e) {
            return null;
        }
    }

    public static function make(): static
    {
        return new static();
    }
}
