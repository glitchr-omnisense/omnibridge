<?php

namespace Omnibridge\Validation\Constraint;

/**
 * A file sent or kept: at most maxSize bytes, of one of the mime types
 * ("image/*" allowed). The file is the host's (Symfony's or Laravel's
 * UploadedFile, an SplFileInfo) or a path. Null passes.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class File extends Constraint
{
    public const TOO_LARGE = 'df8637af-d466-48c6-a59d-e7126250a654';
    public const INVALID_MIME_TYPE = '744f00bc-4389-4c74-92de-9a43cde55534';
    public const NOT_FOUND = 'd2a3fb6e-7ddc-4210-8fbf-2ab345ce1998';

    /**
     * @param list<string>      $mimeTypes
     * @param list<string>|null $groups
     */
    public function __construct(
        public readonly ?int $maxSize = null,
        public readonly array $mimeTypes = [],
        public readonly string $maxSizeMessage = 'The file is too large ({{ size }} {{ suffix }}). Allowed maximum size is {{ limit }} {{ suffix }}.',
        public readonly string $mimeTypesMessage = 'The mime type of the file is invalid ({{ type }}). Allowed mime types are {{ types }}.',
        public readonly string $notFoundMessage = 'The file could not be found.',
        ?array $groups = null,
    ) {
        parent::__construct($groups);
    }
}
