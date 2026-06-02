<?php

namespace App\Logging;

use App\Support\LogSanitizer;
use Illuminate\Log\Logger as LaravelLogger;
use Monolog\LogRecord;

class SanitizeLogContext
{
    public function __invoke(LaravelLogger $logger): void
    {
        $logger->getLogger()->pushProcessor(function (LogRecord $record): LogRecord {
            return $record->with(
                message: LogSanitizer::sanitizeString($record->message),
                context: LogSanitizer::sanitizeArray($record->context),
                extra: LogSanitizer::sanitizeArray($record->extra),
            );
        });
    }
}
