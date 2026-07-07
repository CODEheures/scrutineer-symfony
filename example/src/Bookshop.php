<?php

declare(strict_types=1);

namespace Acme;

/**
 * Stand-in for YOUR application's domain — here a tiny in-memory bookshop ("Acme Books"). In a
 * real host this would be your database + services; the example keeps it in arrays so it runs
 * with zero infrastructure. The Scrutineer {@see AcmeScenarioSeeder} drives it — it (re)builds
 * this decor per scenario. Nothing here knows Scrutineer exists.
 */
final class Bookshop
{
    /** @var array<string, array{title: string, available: bool}> ISBN => book */
    public array $books = [];

    /** @var array<string, array{member: string, dueDate: \DateTimeImmutable}> ISBN => loan */
    public array $loans = [];

    /** Wipe everything — the clean baseline every scenario reset starts from. */
    public function reset(): void
    {
        $this->books = [];
        $this->loans = [];
    }

    public function addBook(string $isbn, string $title, bool $available = true): void
    {
        $this->books[$isbn] = ['title' => $title, 'available' => $available];
    }

    public function lend(string $isbn, string $member, \DateTimeImmutable $dueDate): void
    {
        $this->books[$isbn]['available'] = false;
        $this->loans[$isbn] = ['member' => $member, 'dueDate' => $dueDate];
    }
}
