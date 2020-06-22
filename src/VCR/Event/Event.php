<?php

namespace VCR\Event;

if (class_exists(\Symfony\Component\EventDispatcher\Event::class)) {
    class Event extends \Symfony\Component\EventDispatcher\Event
    {
        //
    }
} else {
    class Event extends \Symfony\Contracts\EventDispatcher\Event
    {
        //
    }
}
