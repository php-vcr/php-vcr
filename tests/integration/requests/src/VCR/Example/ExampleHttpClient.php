<?php
namespace VCR\Example;

use Requests;

class ExampleHttpClient
{
    public function get($url)
    {
        return json_decode(Requests::get($url)->body, true);
    }
}
