<?php

namespace Omnibridge\Exception;

/**
 * A contract asked of Omnibridge\Runtime when no host filled it: loud, where a
 * code that found no service would return null in silence.
 *
 * Formerly Base\Exception\MissingContractException, which remains its alias
 * until omnibase 4's final pass.
 */
final class MissingContractException extends \LogicException
{
    /** @param list<string> $formerNames */
    public static function for(string $contract, array $formerNames = []): self
    {
        if ([] !== $formerNames) {
            $contract .= ' (formerly '.implode(', ', $formerNames).')';
        }

        return new self(sprintf('No %s in the runtime: the host fills it at boot (its omnibridge bridge, omniscient/omnibase\'s bundle under Symfony), or Omnibridge\Runtime::set() / setContainer() before it is asked.', $contract));
    }

    public static function notAn(string $contract, object $service): self
    {
        return new self(sprintf('The runtime\'s %s is a %s, which does not implement it.', $contract, get_debug_type($service)));
    }
}
