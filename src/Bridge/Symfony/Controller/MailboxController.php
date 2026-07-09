<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Bridge\Symfony\Controller;

use CODEHeures\Scrutineer\Console\PosteToken;
use CODEHeures\Scrutineer\Console\ScrutineerGuard;
use CODEHeures\Scrutineer\Console\ScrutineerMailbox;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The captured-mail inbox transport — two routes, both gated by {@see ScrutineerGuard} AND by
 * mail capture being on (a null mailbox = capture off → 403, so possessing a poste cookie
 * always implies capture is enabled):
 *   - POST /poste  → mint a per-browser «poste» token into the httpOnly `scrutineer_poste`
 *                    cookie (idempotent: an existing cookie is kept). The token never reaches
 *                    JS — the host reads it server-side to stamp recette mails, and the read
 *                    route resolves it server-side too.
 *   - GET  /mails  → the captured mails for THIS browser's poste (resolved from the cookie).
 */
final class MailboxController
{
    public function __construct(
        private readonly ScrutineerGuard $guard,
        private readonly bool $mailCaptureEnabled = false,
        private readonly ?ScrutineerMailbox $mailbox = null,
    ) {}

    public function mint(Request $request): JsonResponse
    {
        if (!$this->available()) {
            return $this->forbidden();
        }

        // Self-cleaning: minting a token is the frequent, autonomous hook to drop mails past the
        // retention window — no host cron, the library owns its table's lifecycle end to end.
        $this->mailbox?->purgeExpired();

        // Idempotent: keep an existing poste so its already-captured mail stays readable; the
        // tester's browser owns one stable identity for the whole session.
        $existing = $request->cookies->get(PosteToken::COOKIE);
        $token = \is_string($existing) && '' !== $existing ? $existing : PosteToken::mint();

        $response = new JsonResponse(['ok' => true]);
        $response->headers->setCookie($this->cookie($request, $token));

        return $response;
    }

    public function mails(Request $request): JsonResponse
    {
        if (!$this->available() || null === $this->mailbox) {
            return $this->forbidden();
        }

        $token = $request->cookies->get(PosteToken::COOKIE);
        $limit = $request->query->has('limit') ? max(1, $request->query->getInt('limit')) : 100;

        return new JsonResponse($this->mailbox->inbox(\is_string($token) ? $token : null, $limit));
    }

    private function available(): bool
    {
        return $this->guard->isEnabled() && $this->mailCaptureEnabled;
    }

    private function cookie(Request $request, string $token): Cookie
    {
        $secure = $request->isSecure();

        // Same-site (Lax) covers app↔api on a shared registrable domain in dev; None+Secure
        // covers a genuinely cross-site prod. httpOnly: JS never needs the value (server reads
        // it for the mail header and the inbox), and keeping it off JS is the point.
        return Cookie::create(PosteToken::COOKIE, $token)
            ->withHttpOnly(true)
            ->withPath('/')
            ->withSecure($secure)
            ->withSameSite($secure ? Cookie::SAMESITE_NONE : Cookie::SAMESITE_LAX)
            ->withExpires(time() + 8 * 3600);
    }

    private function forbidden(): JsonResponse
    {
        return new JsonResponse(
            ['error' => 'forbidden', 'message' => 'Scrutineer mail capture is disabled.'],
            Response::HTTP_FORBIDDEN,
        );
    }
}
