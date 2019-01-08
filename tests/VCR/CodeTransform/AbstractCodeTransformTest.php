<?php

namespace VCR\CodeTransform;

use PHPUnit\Framework\TestCase;

class AbstractCodeTransformTest extends TestCase
{
    protected function getFilter(array $methods = array())
    {
        $defaults = array_merge(
            array('transformCode'),
            $methods
        );

        $filter = $this->getMockBuilder('\VCR\CodeTransform\AbstractCodeTransform')
            ->setMethods($defaults)
            ->getMockForAbstractClass();

        if (in_array('transformCode', $methods)) {
            $filter
                ->expects($this->once())
                ->method('transformCode')
                ->with($this->isType('string'))
                ->will($this->returnArgument(0));
        }

        return $filter;
    }

    public function testRegisterAlreadyRegistered()
    {
        $filter = $this->getFilter();
        $filter->register();

        $this->assertContains(AbstractCodeTransform::NAME, stream_get_filters(), 'First attempt to register failed.');

        $filter->register();

        $this->assertContains(AbstractCodeTransform::NAME, stream_get_filters(), 'Second attempt to register failed.');
    }
}
