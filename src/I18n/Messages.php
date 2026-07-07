<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\I18n;

/**
 * The library's own message catalogues. The host picks a language (config / CLI option); the
 * library owns the translations — nothing host-provided. English is the neutral default;
 * unknown languages, and keys missing from a language, fall back to English.
 *
 * Scope today = the CSV export column headers (the only user-facing strings the back-end
 * renders; scenario labels/steps come from the host's CatalogProvider). Adding a language =
 * one more entry here; adding a string = one more key across the catalogues.
 */
final class Messages
{
    public const DEFAULT_LANG = 'en';

    /** @var array<string, array<string, string>> */
    private const CATALOG = [
        'en' => [
            'export.col.date' => 'date',
            'export.col.version' => 'version',
            'export.col.actor' => 'tester',
            'export.col.scenario' => 'scenario',
            'export.col.outcome' => 'result',
            'export.col.comment' => 'comment',
            'export.col.scope' => 'scope',
        ],
        'fr' => [
            'export.col.date' => 'date',
            'export.col.version' => 'version',
            'export.col.actor' => 'recetteur',
            'export.col.scenario' => 'scenario',
            'export.col.outcome' => 'resultat',
            'export.col.comment' => 'commentaire',
            'export.col.scope' => 'perimetre',
        ],
    ];

    private readonly string $lang;

    public function __construct(string $lang = self::DEFAULT_LANG)
    {
        $this->lang = isset(self::CATALOG[$lang]) ? $lang : self::DEFAULT_LANG;
    }

    public function get(string $key): string
    {
        return self::CATALOG[$this->lang][$key]
            ?? self::CATALOG[self::DEFAULT_LANG][$key]
            ?? $key;
    }
}
