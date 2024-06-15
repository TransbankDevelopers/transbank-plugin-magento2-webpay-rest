<?php

namespace Transbank\Webpay\Helper;

interface ILogger
{
    function logInfo(string $str): void;
    function logError(string $str): void;
    function logDebug(string $str): void;
    function getInfo(): array;
    function getLogDetail(string $filename, bool $replaceNewline): array;
}
