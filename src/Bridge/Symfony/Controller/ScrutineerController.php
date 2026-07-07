<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Bridge\Symfony\Controller;

use CODEHeures\Scrutineer\Console\ScrutineerConsole;
use CODEHeures\Scrutineer\Console\ScrutineerGuard;
use CODEHeures\Scrutineer\Model\HistoryQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thin transport over {@see ScrutineerConsole}. Every action is gated by {@see ScrutineerGuard}
 * (the `SCRUTINEER_ENABLED` env-var) and speaks only the OpenAPI contract — no business
 * logic. Authentication of the mutating routes is the host's job (security access_control),
 * not the library's.
 */
final class ScrutineerController
{
    public function __construct(
        private readonly ScrutineerConsole $console,
        private readonly ScrutineerGuard $guard,
        private readonly ?string $assetPath = null,
    ) {}

    public function catalog(): JsonResponse
    {
        if (!$this->guard->isEnabled()) {
            return $this->forbidden();
        }

        return new JsonResponse($this->console->catalog());
    }

    /**
     * Pre-flight the console calls when its per-browser flag asks it to open. Deliberately
     * NOT gated: a disabled host answers {available:false} with 200 (not 403) so the console
     * stays shut even if someone forced their local flag (an extra, server-side gate).
     */
    public function availability(): JsonResponse
    {
        return new JsonResponse(['available' => $this->guard->isEnabled()]);
    }

    public function publish(Request $request): JsonResponse
    {
        if (!$this->guard->isEnabled()) {
            return $this->forbidden();
        }

        return new JsonResponse($this->console->publish($this->decode($request)));
    }

    public function reset(Request $request): JsonResponse
    {
        if (!$this->guard->isEnabled()) {
            return $this->forbidden();
        }

        $scenarioId = $this->stringField($this->decode($request), 'scenarioId');
        if ('' === $scenarioId) {
            return $this->badRequest('scenarioId is required.');
        }

        $this->console->reset($scenarioId);

        return new JsonResponse(['ok' => true]);
    }

    public function history(Request $request): JsonResponse
    {
        if (!$this->guard->isEnabled()) {
            return $this->forbidden();
        }

        $q = $request->query;
        $query = new HistoryQuery(
            scenarioId: $q->get('scenarioId'),
            appVersion: $q->get('appVersion'),
            actorRef: $q->get('actorRef'),
            scopeKey: $q->get('scopeKey'),
            limit: $q->has('limit') ? $q->getInt('limit') : null,
            offset: $q->has('offset') ? $q->getInt('offset') : null,
        );

        return new JsonResponse($this->console->history($query));
    }

    public function events(Request $request): JsonResponse
    {
        if (!$this->guard->isEnabled()) {
            return $this->forbidden();
        }

        $data = $this->decode($request);
        $scenarioId = $this->stringField($data, 'scenarioId');
        $outcome = $this->stringField($data, 'outcome');
        if ('' === $scenarioId || '' === $outcome) {
            return $this->badRequest('scenarioId and outcome are required.');
        }
        $comment = isset($data['comment']) && \is_string($data['comment']) ? $data['comment'] : null;

        $event = $this->console->record($scenarioId, $outcome, $comment);

        return new JsonResponse(['event' => $this->console->eventToArray($event)], Response::HTTP_CREATED);
    }

    public function asset(?string $lang = null): Response
    {
        // Enabled-only, like every route: the asset is just JS (no data) and must load on
        // the root layout — even on the login screen, before any scope is established.
        if (!$this->guard->isEnabled()) {
            return $this->forbidden();
        }
        if (null === $this->assetPath) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $file = $this->assetPath;
        if (null !== $lang) {
            // Sibling locale add-on: scrutineer-console.<lang>.js. Validate the code so the
            // resolved path cannot escape the asset directory.
            if (1 !== preg_match('/^[a-z]{2}$/', $lang)) {
                return new Response('', Response::HTTP_NOT_FOUND);
            }
            $file = \dirname($this->assetPath) . '/' . basename($this->assetPath, '.js') . '.' . $lang . '.js';
        }
        if (!is_file($file)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        return new Response(
            (string) file_get_contents($file),
            Response::HTTP_OK,
            ['Content-Type' => 'application/javascript'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Request $request): array
    {
        $raw = $request->getContent();
        if ('' === $raw) {
            return [];
        }
        try {
            $data = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        if (!\is_array($data)) {
            return [];
        }

        // Normalise to a string-keyed map (a JSON object body; a JSON list would give int keys).
        $map = [];
        foreach ($data as $key => $value) {
            $map[(string) $key] = $value;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function stringField(array $data, string $key): string
    {
        return isset($data[$key]) && \is_string($data[$key]) ? $data[$key] : '';
    }

    private function forbidden(): JsonResponse
    {
        return new JsonResponse(
            ['error' => 'forbidden', 'message' => 'Scrutineer is disabled (SCRUTINEER_ENABLED is off).'],
            Response::HTTP_FORBIDDEN,
        );
    }

    private function badRequest(string $message): JsonResponse
    {
        return new JsonResponse(['error' => 'bad_request', 'message' => $message], Response::HTTP_BAD_REQUEST);
    }
}
