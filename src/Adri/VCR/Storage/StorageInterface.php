<?php

namespace Adri\VCR\Storage;

use Adri\VCR\Request;
use Adri\VCR\Response;

interface StorageInterface extends \Iterator
{
    public function storeRecording(Request $request, Response $response);
}