<?php

namespace Omnibridge\Exception;

/**
 * The persistence cannot be chosen (Omnibridge\Persistence\Persistences::choose()): a name no persistence has, one not
 * installed, or several installed and none named by the host's configuration.
 */
final class PersistenceException extends \LogicException
{
}
