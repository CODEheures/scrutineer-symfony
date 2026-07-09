<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Port;

use CODEHeures\Scrutineer\Model\CapturedMail;

/**
 * Store of mails the library captured instead of delivering (the staging «inbox»). Optional:
 * a host only needs it when it turns mail capture on (`scrutineer.mail_capture.enabled`). The
 * library ships a default Doctrine-backed implementation; implement this port to keep the
 * captured mail somewhere else.
 *
 * Like the history ledger it is INSERT + read only — a captured mail is an immutable snapshot,
 * scoped by the poste hash so a browser reads back only its own.
 */
interface MailStore
{
    /** Persist one captured mail. */
    public function capture(CapturedMail $mail): void;

    /**
     * @return list<CapturedMail> the mails tagged with $posteHash, most-recent-first
     */
    public function inbox(string $posteHash, ?int $limit = null): array;
}
