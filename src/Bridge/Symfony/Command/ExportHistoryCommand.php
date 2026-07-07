<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Bridge\Symfony\Command;

use CODEHeures\Scrutineer\I18n\Messages;
use CODEHeures\Scrutineer\Model\HistoryQuery;
use CODEHeures\Scrutineer\Port\ActorResolver;
use CODEHeures\Scrutineer\Port\HistoryStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Exports the acceptance-test result history as CSV (spreadsheet-openable) — the reporting
 * tier ①. A projection over the append-only ledger; actor refs are resolved to labels
 * at export time (the ledger itself stays PII-free). No third-party dependency. Column
 * headers are localised by the library ({@see Messages}); pick the language with `--lang`.
 *
 *   php bin/console scrutineer:export --release=0.2.1 --lang=fr --out=history-0.2.1.csv
 */
#[AsCommand(
    name: 'scrutineer:export',
    description: 'Export the acceptance-test result history as CSV (spreadsheet-openable).',
)]
final class ExportHistoryCommand extends Command
{
    public function __construct(
        private readonly HistoryStore $history,
        private readonly ?ActorResolver $actors = null,
        private readonly string $defaultLang = Messages::DEFAULT_LANG,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('release', null, InputOption::VALUE_REQUIRED, 'Filter on a release version (e.g. 0.2.1).')
            ->addOption('scope', null, InputOption::VALUE_REQUIRED, 'Filter on a scope key.')
            ->addOption('scenario', null, InputOption::VALUE_REQUIRED, 'Filter on a scenario id.')
            ->addOption('lang', null, InputOption::VALUE_REQUIRED, 'Column-header language (e.g. fr, en). Defaults to the bundle config.')
            ->addOption('out', 'o', InputOption::VALUE_REQUIRED, 'Write to this CSV file (default: stdout).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $events = $this->history->timeline(new HistoryQuery(
            scenarioId: $this->strOpt($input, 'scenario'),
            appVersion: $this->strOpt($input, 'release'),
            scopeKey: $this->strOpt($input, 'scope'),
        ));

        // Resolve actor refs to labels once (live; the ledger stays opaque/PII-free).
        $labels = [];
        if (null !== $this->actors && [] !== $events) {
            $refs = array_values(array_unique(array_map(static fn(object $e): string => $e->actorRef, $events)));
            $labels = $this->actors->resolve($refs);
        }

        $out = $this->strOpt($input, 'out');
        $stream = fopen($out ?? 'php://temp', 'r+');
        if (false === $stream) {
            $io->error(\sprintf('Cannot open "%s" for writing.', $out ?? 'php://temp'));

            return Command::FAILURE;
        }

        if (null !== $out) {
            fwrite($stream, "\u{FEFF}"); // UTF-8 BOM so Excel reads accents correctly
        }
        $m = new Messages($this->strOpt($input, 'lang') ?? $this->defaultLang);
        fputcsv($stream, [
            $m->get('export.col.date'),
            $m->get('export.col.version'),
            $m->get('export.col.actor'),
            $m->get('export.col.scenario'),
            $m->get('export.col.outcome'),
            $m->get('export.col.comment'),
            $m->get('export.col.scope'),
        ], ',', '"', '');
        foreach ($events as $e) {
            fputcsv($stream, [
                $e->occurredAt->format('Y-m-d H:i:s'),
                $e->appVersion,
                $labels[$e->actorRef] ?? $e->actorRef,
                $e->scenarioId,
                $e->outcome,
                $e->comment ?? '',
                $e->scopeKey ?? '',
            ], ',', '"', '');
        }

        if (null !== $out) {
            fclose($stream);
            $io->success(\sprintf('%d result(s) exported to %s.', \count($events), $out));

            return Command::SUCCESS;
        }

        rewind($stream);
        $output->write((string) stream_get_contents($stream));
        fclose($stream);

        return Command::SUCCESS;
    }

    private function strOpt(InputInterface $input, string $name): ?string
    {
        $v = $input->getOption($name);

        return \is_string($v) && '' !== $v ? $v : null;
    }
}
