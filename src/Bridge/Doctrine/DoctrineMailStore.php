<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Bridge\Doctrine;

use CODEHeures\Scrutineer\Model\CapturedMail;
use CODEHeures\Scrutineer\Port\MailStore;
use Doctrine\DBAL\Connection;

/**
 * The default {@see MailStore}: a DBAL-backed captured-mail inbox. INSERT + read only; the
 * connection is host-chosen (the same one hosting the ledger) and the table defaults to
 * {@see ScrutineerMailSchema::TABLE}.
 */
final class DoctrineMailStore implements MailStore
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $table = ScrutineerMailSchema::TABLE,
    ) {}

    public function capture(CapturedMail $mail): void
    {
        $this->connection->insert($this->table, [
            'id' => $mail->id,
            'captured_at' => $mail->capturedAt->format('Y-m-d H:i:s.u'),
            'poste_hash' => $mail->posteHash,
            'from_address' => $mail->from,
            'to_addresses' => implode(', ', $mail->to),
            'subject' => $mail->subject,
            'html_body' => $mail->htmlBody,
            'text_body' => $mail->textBody,
        ]);
    }

    public function inbox(string $posteHash, ?int $limit = null): array
    {
        // '' would match untagged rows — a poste never reads those, so refuse it explicitly
        // rather than leak the untagged pool to any caller with no cookie.
        if ('' === $posteHash) {
            return [];
        }

        $qb = $this->connection->createQueryBuilder()
            ->select('id', 'captured_at', 'poste_hash', 'from_address', 'to_addresses', 'subject', 'html_body', 'text_body')
            ->from($this->table)
            ->where('poste_hash = :hash')
            ->setParameter('hash', $posteHash)
            ->orderBy('captured_at', 'DESC')
            ->addOrderBy('id', 'DESC');

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        return array_map(
            fn(array $row): CapturedMail => $this->hydrate($row),
            $qb->executeQuery()->fetchAllAssociative(),
        );
    }

    public function purge(\DateTimeImmutable $olderThan): int
    {
        return (int) $this->connection->createQueryBuilder()
            ->delete($this->table)
            ->where('captured_at < :threshold')
            ->setParameter('threshold', $olderThan->format('Y-m-d H:i:s.u'))
            ->executeStatement();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): CapturedMail
    {
        $to = self::str($row['to_addresses']);

        return new CapturedMail(
            id: self::str($row['id']),
            capturedAt: new \DateTimeImmutable(self::str($row['captured_at'])),
            posteHash: self::str($row['poste_hash']),
            from: self::str($row['from_address']),
            to: '' === $to ? [] : array_map('trim', explode(',', $to)),
            subject: self::str($row['subject']),
            htmlBody: null !== $row['html_body'] ? self::str($row['html_body']) : null,
            textBody: null !== $row['text_body'] ? self::str($row['text_body']) : null,
        );
    }

    /** Coerce a DBAL row value (mixed) to string; a non-scalar degrades to ''. */
    private static function str(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }
}
