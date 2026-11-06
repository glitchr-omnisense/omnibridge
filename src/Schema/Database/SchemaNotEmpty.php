<?php

namespace Omnibridge\Schema\Database;

/** The database already holds tables of the description: SchemaCreator creates a schema in an empty database only. */
final class SchemaNotEmpty extends \RuntimeException
{
    /** @param list<string> $tables */
    public function __construct(public readonly array $tables)
    {
        parent::__construct(\sprintf('The database already holds %d table%s of the description (%s): omnibridge:schema:create creates a schema in an empty database; an existing one is evolved by the persistence\'s migrations.', \count($tables), 1 === \count($tables) ? '' : 's', implode(', ', \array_slice($tables, 0, 5)).(\count($tables) > 5 ? ', ...' : '')));
    }
}
