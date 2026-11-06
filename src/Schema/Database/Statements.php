<?php

namespace Omnibridge\Schema\Database;

use Omnibridge\Schema\Description;

/**
 * The statements that create a description's tables in an empty database of
 * one kind (MySqlStatements, SqliteStatements): what omnibridge:schema:create
 * runs, on any host, whatever persistence reads the tables afterwards (the
 * plan "omnibase agnostic", answer 11: a new site's schema comes from its
 * description; migrations, which evolve an existing database, belong to the
 * persistence chosen).
 */
interface Statements
{
    /** The PDO driver's name these statements are written for (mysql, sqlite). */
    public function driver(): string;

    /**
     * Every table of the description - its columns, primary key, indexes and unique indexes, foreign keys - in
     * statements run one after another on an empty database.
     *
     * @return list<string>
     */
    public function create(Description $description): array;
}
