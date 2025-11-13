<?php

namespace IpagDevs\Model;

use JsonSerializable;

interface SerializableModelInterface extends JsonSerializable
{
    /**
     * @param array<mixed> $data
     * @return self|null
     */
    static function tryParse(array $data): ?self;

    /**
     * @param array<mixed> $data
     * @return self
     */
    static function parse(array $data): self;

    /**
     * @return array<mixed>
     */
    function jsonSerialize(): array;
}
