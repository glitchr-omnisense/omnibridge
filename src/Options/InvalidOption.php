<?php

namespace Omnibridge\Options;

/**
 * An option a configuration gives that its schema refuses (Options\Processor): its path ("base.guard.min_delay")
 * and the message, worded as Symfony's Config component words it, so that a host says the same thing whichever
 * reads the schema.
 */
final class InvalidOption extends \InvalidArgumentException
{
    public function __construct(string $message, private readonly string $path)
    {
        parent::__construct($message);
    }

    /** The option's path, the schema's root first. */
    public function path(): string
    {
        return $this->path;
    }
}
