<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Mail\Autoconfig;

use Horde\Mail\Autoconfig\Exception\AutoconfigException;
use Horde\Mail\Autoconfig\Validation\ValidatorInterface;

/**
 * Main facade contract for mail server auto-discovery.
 */
interface AutoconfigInterface
{
    /**
     * Return a new instance with the given validator and validation mode.
     *
     * The original instance is not modified.
     */
    public function withValidation(
        ValidatorInterface $validator,
        ValidationMode $mode = ValidationMode::Next,
    ): static;
    /**
     * Discover all mail servers (incoming + submission) for an email address.
     *
     * @param string $email A valid email address.
     *
     * @throws AutoconfigException On invalid input or unrecoverable errors.
     */
    public function discover(string $email): DiscoveryResult;

    /**
     * Discover submission (MSA / SMTP) servers only.
     *
     * @param string $email A valid email address.
     *
     * @throws AutoconfigException On invalid input or unrecoverable errors.
     */
    public function discoverMsa(string $email): DiscoveryResult;

    /**
     * Discover incoming mail servers (IMAP / POP3) only.
     *
     * @param string $email  A valid email address.
     * @param bool   $noImap If true, skip IMAP lookups.
     * @param bool   $noPop3 If true, skip POP3 lookups.
     *
     * @throws AutoconfigException On invalid input or unrecoverable errors.
     */
    public function discoverMail(
        string $email,
        bool $noImap = false,
        bool $noPop3 = false,
    ): DiscoveryResult;
}
