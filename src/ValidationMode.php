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
 * Controls how the Autoconfig facade handles server validation.
 */
enum ValidationMode: string
{
    /** Accept every discovered server without calling the validator. */
    case None = 'none';

    /** Validate each server; silently skip those that fail. */
    case Next = 'next';

    /** Validate each server; throw AutoconfigException on first failure. */
    case Fatal = 'fatal';
}
