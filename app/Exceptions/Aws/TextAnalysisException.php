<?php

namespace App\Exceptions\Aws;

use Exception;

class TextAnalysisException extends Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}