# Changelog

## [Unreleased]

### Fixed

- README and docblocks: omnibridge/symfony is named the Symfony host (it was "next"); the former
  names (`Base\Contract\*`, `Omnibridge\Twig\Contract\*`, `Base\Contract\SystemClock`,
  `Base\Exception\MissingContractException`) are said removed at omnibase 4's final pass, where they
  were said to remain aliases; `Declarations` names omnibridge/doctrine's `DeclarationDriver` (it
  said `DescriptionDriver`).
- composer.json requires `ext-mbstring`, which the package calls (`mb_*` functions): a PHP built without it failed with an undefined function.
