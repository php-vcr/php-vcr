<?php

namespace VCR;

class RequestMismatch {
    private $request;
    private $fieldMismatchers;

    public function __construct($request) {
        $this->request = $request;
        $this->fieldMismatchers = array();
    }

    public function addFieldMismatcher(FieldMismatcher $fieldMismatcher) {
        $this->fieldMismatchers[] = $fieldMismatcher;
    }
}
