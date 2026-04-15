<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Mail\Autoconfig;

/**
 * TLS connection mode for a mail server.
 */
enum TlsMode: string
{
    /** Implicit TLS — direct SSL connection on a dedicated port (e.g. 993, 465). */
    case Tls = 'tls';

    /** Upgrade to TLS via STARTTLS command after initial plaintext connection. */
    case StartTls = 'starttls';

    /** No encryption. */
    case None = 'none';
}
