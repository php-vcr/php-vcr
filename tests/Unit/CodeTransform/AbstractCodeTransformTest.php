<?php

declare(strict_types=1);

namespace VCR\Tests\Unit\CodeTransform;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use VCR\CodeTransform\AbstractCodeTransform;

final class AbstractCodeTransformTest extends TestCase
{
    /**
     * @param string[] $methods
     *
     * @return AbstractCodeTransform&MockObject
     */
    protected function getFilter(array $methods = [])
    {
        $defaults = array_merge(
            ['transformCode'],
            $methods
        );

        $filter = $this->getMockBuilder(AbstractCodeTransform::class)
            ->setMethods($defaults)
            ->getMockForAbstractClass();

        if (\in_array('transformCode', $methods)) {
            $filter
                ->expects($this->once())
                ->method('transformCode')
                ->with($this->isType('string'))
                ->willReturnArgument(0);
        }

        return $filter;
    }

    public function testRegisterAlreadyRegistered(): void
    {
        $filter = $this->getFilter();
        $filter->register();

        $this->assertContains(AbstractCodeTransform::NAME, stream_get_filters(), 'First attempt to register failed.');

        $filter->register();

        $this->assertContains(AbstractCodeTransform::NAME, stream_get_filters(), 'Second attempt to register failed.');
    }
}
