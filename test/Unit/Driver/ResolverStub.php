<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Mail\Autoconfig\Test\Unit\Driver;

use Closure;
use NetDNS2\Exception as DnsException;
use stdClass;

/**
 * Stub for NetDNS2\Resolver which is declared final and cannot be mocked.
 * Accepts a callback that receives ($name, $type) and should return a response
 * object or throw DnsException.
 */
class ResolverStub
{
    private Closure $callback;

    /**
     * @param Closure(string, string): stdClass $callback
     */
    public function __construct(Closure $callback)
    {
        $this->callback = $callback;
    }

    /**
     * Create a stub that always throws DnsException.
     */
    public static function failing(): self
    {
        return new self(function (string $name, string $type): never {
            throw new DnsException('not found');
        });
    }

    /**
     * Create a stub that always returns a response with the given answer records.
     */
    public static function withAnswer(array $answer): self
    {
        return new self(function (string $name, string $type) use ($answer): stdClass {
            $response = new stdClass();
            $response->answer = $answer;
            return $response;
        });
    }

    public function query(string $name, string $type = 'A'): stdClass
    {
        return ($this->callback)($name, $type);
    }
}
