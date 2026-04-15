<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Mail\Autoconfig\Validation;

use Horde\Mail\Autoconfig\ServerConfig;

/**
 * Tests whether a discovered mail server is reachable or usable.
 *
 * Implementations wrap whatever client libraries they need (IMAP, SMTP, etc.).
 */
interface ValidatorInterface
{
    /**
     * @param ServerConfig $server The server to validate.
     * @param string       $email  The email address being looked up
     *                             (useful for deriving login credentials).
     *
     * @return bool True if the server is valid / reachable.
     */
    public function validate(ServerConfig $server, string $email): bool;
}
