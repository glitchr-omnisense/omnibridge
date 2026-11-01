<?php

namespace Omnibridge\Persistence\Lifecycle;

/** Before a new entity is first written (persist() of a new entity; Doctrine's prePersist). */
final class PrePersist extends EntityEvent
{
}
