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
 * Mail server protocol type.
 */
enum ServerType: string
{
    /** IMAP message access (RFC 3501). */
    case Imap = 'imap';

    /** POP3 message access (RFC 1939). */
    case Pop3 = 'pop3';

    /** SMTP message submission / MSA (RFC 6409). */
    case Submission = 'submission';
}
