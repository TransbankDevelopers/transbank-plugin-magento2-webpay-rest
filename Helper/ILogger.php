<?php

namespace Transbank\Webpay\Helper;

interface ILogger
{
    function logInfo(string $str, array $context = []): void;
    function logError(string $str, array $context = []): void;
    function logDebug(string $str, array $context = []): void;
    function logWarning(string $str, array $context = []): void;
    function getInfo(): array;
    function getLogDetail(string $filename, bool $replaceNewline): array;
}
