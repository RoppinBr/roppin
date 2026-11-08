<?php
abstract class Model
{
    protected array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $allowed = array_flip(static::fields());
        $this->attributes = array_intersect_key($attributes, $allowed);
    }

    abstract public static function table(): string;

    abstract public static function primaryKey(): string;

    abstract public static function fields(): array;

    public static function hasAutoIncrement(): bool
    {
        return true;
    }

    public function get(string $field): mixed
    {
        if (!in_array($field, static::fields(), true)) {
            throw new InvalidArgumentException('Campo não definido para este model.');
        }

        return $this->attributes[$field] ?? null;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}