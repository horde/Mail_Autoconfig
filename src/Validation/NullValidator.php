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
 * Validator that accepts every server unconditionally.
 *
 * Used as the default when no real validator is injected.
 */
class NullValidator implements ValidatorInterface
{
    public function validate(ServerConfig $server, string $email): bool
    {
        return true;
    }
}
