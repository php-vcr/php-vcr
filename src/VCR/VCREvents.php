<?php

namespace VCR;

final class VCREvents
{
    const VCR_BEFORE_RECORD = 'vcr.before_record';
    const VCR_BEFORE_PLAYBACK = 'vcr.before_playback';
    const VCR_AFTER_PLAYBACK = 'vcr.after_playback';
    const VCR_BEFORE_HTTP_REQUEST = 'vcr.before_http_request';
    const VCR_AFTER_HTTP_REQUEST = 'vcr.after_http_request';
}
