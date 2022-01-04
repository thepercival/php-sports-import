<?php

declare(strict_types=1);

namespace SportsImport\Tests;

use FCToernooi\User as User;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SportsImport\ExternalSource;

final class ExternalSourceTest extends TestCase
{
    public function testName(): void
    {
        $externSource = new ExternalSource('MyExternalSource', 'apiurl');
        self::assertSame('MyExternalSource', $externSource->getName());
    }
}
