<?php

declare(strict_types=1);

namespace VCR;

final class VCREvents
{
    public const VCR_BEFORE_RECORD = 'vcr.before_record';
    public const VCR_BEFORE_PLAYBACK = 'vcr.before_playback';
    public const VCR_AFTER_PLAYBACK = 'vcr.after_playback';
    public const VCR_BEFORE_HTTP_REQUEST = 'vcr.before_http_request';
    public const VCR_AFTER_HTTP_REQUEST = 'vcr.after_http_request';
}
