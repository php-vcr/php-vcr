<?php

namespace VCR\Event;

use Symfony\Component\EventDispatcher\Event as LegacyEvent;
use Symfony\Contracts\EventDispatcher\Event as ContractsEvent;

if (class_exists(ContractsEvent::class) && !class_exists(LegacyEvent::class)) {
    abstract class SymphonyEvent extends ContractsEvent
    {
    }
} elseif (class_exists(LegacyEvent::class)) {
    abstract class SymphonyEvent extends LegacyEvent
    {
    }
}

abstract class Event extends SymphonyEvent
{
}
