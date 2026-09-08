<?php

namespace Thalia\ShopifyRestToGraphql;

/**
 * Typed view over a GraphQL response body (`data`, `errors`, `extensions`).
 * Returned by GraphqlService::query(); graphqlQueryThalia() keeps returning
 * the raw array.
 */
class GraphqlResponse implements \ArrayAccess
{
    private array $raw;

    public function __construct(array $raw)
    {
        $this->raw = $raw;
    }

    public function data(): array
    {
        return is_array($this->raw['data'] ?? null) ? $this->raw['data'] : [];
    }

    public function errors(): array
    {
        return is_array($this->raw['errors'] ?? null) ? $this->raw['errors'] : [];
    }

    public function hasErrors(): bool
    {
        return $this->errors() !== [];
    }

    public function extensions(): array
    {
        return is_array($this->raw['extensions'] ?? null) ? $this->raw['extensions'] : [];
    }

    /** `extensions.cost` (requestedQueryCost, actualQueryCost, throttleStatus) or null. */
    public function cost(): ?array
    {
        $cost = $this->extensions()['cost'] ?? null;

        return is_array($cost) ? $cost : null;
    }

    public function requestedQueryCost(): ?int
    {
        $cost = $this->cost();

        return isset($cost['requestedQueryCost']) ? (int) $cost['requestedQueryCost'] : null;
    }

    public function actualQueryCost(): ?int
    {
        $cost = $this->cost();

        return isset($cost['actualQueryCost']) ? (int) $cost['actualQueryCost'] : null;
    }

    /** ['maximumAvailable' => float, 'currentlyAvailable' => float, 'restoreRate' => float] or null. */
    public function throttleStatus(): ?array
    {
        $status = $this->cost()['throttleStatus'] ?? null;

        return is_array($status) ? $status : null;
    }

    public function toArray(): array
    {
        return $this->raw;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->raw[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->raw[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->raw[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->raw[$offset]);
    }
}
