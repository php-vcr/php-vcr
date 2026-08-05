<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\Storage;

use PHPUnit\Framework\TestCase;
use VCR\Storage\Blackhole;
use VCR\Storage\BlackholeStorageFactory;
use VCR\Storage\StorageFactoryInterface;

final class BlackholeStorageFactoryTest extends TestCase
{
    public function testImplementsStorageFactoryInterface(): void
    {
        $this->assertInstanceOf(StorageFactoryInterface::class, new BlackholeStorageFactory());
    }

    public function testCreateReturnsBlackholeStorageAndIgnoresTheCassetteLocation(): void
    {
        $storage = (new BlackholeStorageFactory())->create('/does/not/exist/', 'test-cassette');

        $this->assertInstanceOf(Blackhole::class, $storage);
    }
}
