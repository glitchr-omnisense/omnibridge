<?php

namespace Omnibridge\Event;

/**
 * An event known by a name as well as by its class (omnibase's user.register,
 * thread.publish...). PSR-14 dispatches by class only: a host whose
 * dispatcher also listens by name (Symfony's, Laravel's) gives the event to
 * the listeners of eventName() - omnibridge/laravel's DispatcherEvents does,
 * Symfony's dispatcher is given the name by the caller (Events::dispatch()).
 */
interface NamedEvent
{
    public function eventName(): string;
}
