# Changelog

## Unreleased (1.0.0)

omnisense/omnibridge: the generic contracts omnibase's bridges implement and omnisense/omnibase re-exposes under
`Base\Contract\*` - the persistence (`UnitOfWork` with `clear()` and `isOpen()`, `Schedule`, `Backend`, the neutral
lifecycle events), the schema description (`Schema\Description`, its builder from the mapping attributes of
`Base\Mapping` or Doctrine's vocabulary, the SchemaCreator), and the host's services (urls, views, translations,
access, tokens...). No framework, no ORM.
