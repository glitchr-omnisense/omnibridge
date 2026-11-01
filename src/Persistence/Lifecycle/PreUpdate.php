<?php

namespace Omnibridge\Persistence\Lifecycle;

use Omnibridge\Persistence\UnitOfWork;

/**
 * Before a changed entity is updated: its changes, which a listener may read and
 * change (Doctrine's preUpdate and its setNewValue()).
 */
final class PreUpdate extends EntityEvent
{
    /** @param array<string, array{0: mixed, 1: mixed}> $changes field => [before, after] */
    public function __construct(object $entity, UnitOfWork $unitOfWork, private array $changes)
    {
        parent::__construct($entity, $unitOfWork);
    }

    /** @return array<string, array{0: mixed, 1: mixed}> */
    public function changes(): array
    {
        return $this->changes;
    }

    public function hasChanged(string $field): bool
    {
        return \array_key_exists($field, $this->changes);
    }

    /** The value about to be written for a changed field (the field must have changed). */
    public function setNewValue(string $field, mixed $value): void
    {
        if (!$this->hasChanged($field)) {
            throw new \InvalidArgumentException(\sprintf('The field "%s" of %s did not change: only a changed field gets a new value before the update.', $field, $this->entity::class));
        }
        $this->changes[$field][1] = $value;
    }
}
