<?php

namespace Omnibridge\Contract;

/**
 * The stored files as the application sees them: where a file is served from
 * and where PHP can read it, by its path in a storage. Nothing of how it is
 * stored (Flysystem, a disk, a bucket): that stays with the storage.
 *
 * Generic: a storage is named as the application's configuration names it
 * ("local.uploads", "s3.private"...), null for the default one; each bridge
 * maps those names to its host's storages (Flysystem's, Laravel's disks).
 *
 * Formerly Base\Contract\Files, which remains its alias until omnibase 4's
 * final pass.
 */
interface Files
{
    /**
     * A stored file's path under the public directory, which the web server
     * serves, or for a remote storage the address it is served from; null when
     * it has neither (a missing file, a private storage).
     */
    public function publicPath(string $path, ?string $storage = null): ?string;

    /** The web address of a stored file (/uploads/...); null as publicPath(). */
    public function publicUrl(string $path, ?string $storage = null): ?string;

    /**
     * The file's path on this machine (a local storage, its root prefixed),
     * null where there is no local file behind it (a remote storage).
     */
    public function localPath(string $path, ?string $storage = null): ?string;

    /** Whether the storage is remote: no file on this machine behind its paths. */
    public function isRemote(?string $storage = null): bool;
}
