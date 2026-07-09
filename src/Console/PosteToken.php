<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Console;

/**
 * The «poste» token is a per-browser capability minted by the mint route and carried in the
 * httpOnly `scrutineer_poste` cookie. It does two jobs, both server-side only:
 *   - it authorises reading the captured-mail inbox (the read route resolves the cookie);
 *   - it scopes the inbox — every captured mail is tagged with THIS token so a browser only
 *     ever sees its own mail (parallel testers on the same seeded persona stay isolated).
 *
 * Only its SHA-256 hash is ever persisted (as the capture tag), mirroring how a host stores a
 * challenge/reset token: a DB dump reveals no reusable token. Hashing lives here so the write
 * side (the mail listener) and the read side (the mailbox) agree on one algorithm.
 */
final class PosteToken
{
    /** Cookie the mint route sets and the read route / host resolve — httpOnly, server-side only. */
    public const COOKIE = 'scrutineer_poste';

    /** MIME header the host stamps on a recette mail so the capture can tag it to a poste. */
    public const MAIL_HEADER = 'X-Scrutineer-Poste';

    private function __construct() {}

    /** A fresh 256-bit token (opaque, unguessable). */
    public static function mint(): string
    {
        return bin2hex(random_bytes(32));
    }

    /** The persisted tag for a raw token; '' for the empty/untagged case (never matches a real hash). */
    public static function hash(string $rawToken): string
    {
        return '' === $rawToken ? '' : hash('sha256', $rawToken);
    }
}
