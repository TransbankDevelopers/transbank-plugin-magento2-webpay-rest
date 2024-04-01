<?php

namespace Transbank\Webpay\Helper;

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Cache;

/**
 * This class provides logging functionality for the plugin.
 * It utilizes the Monolog library for logging to files.
 */
class PluginLogger implements ILogger
{
    /**
     * The logger instance.
     *
     * @var Logger
     */
    private $logger;

    /**
     * The directory path for log files.
     *
     * @var string
     */
    private $logDir;

    public function __construct()
    {
        $directoryList = ObjectManagerHelper::get(DirectoryList::class);
        $cache = ObjectManagerHelper::get(Cache::class);

        $this->logDir = $directoryList->getPath(DirectoryList::LOG) . '/Transbank_webpay';

        $cacheLogName = 'transbank_log_name';
        $logFile = $cache->load($cacheLogName);

        if (!$logFile) {
            $uniqueId = uniqid('', true);
            $logFile = "{$this->logDir}/log_transbank_{$uniqueId}.log";
            $expireTime = strtotime('tomorrow') - time();
            $cache->save($logFile, $cacheLogName, [], $expireTime);
        }

        $dateFormat = "Y-m-d H:i:s";
        $output = "%datetime% > %level_name% > %message% %context% %extra%\n";
        $formatter = new LineFormatter($output, $dateFormat);
        $stream = new RotatingFileHandler($logFile, 100, Logger::DEBUG);
        $stream->setFormatter($formatter);
        $this->logger = new Logger('transbank');
        $this->logger->pushHandler($stream);
    }

    /**
     * Log a debug message.
     *
     * @param string $msg The message to log.
     * @return void
     */
    public function logDebug(string $msg): void
    {
        $this->logger->debug($msg);
    }

    /**
     * Log an info message.
     *
     * @param string $msg The message to log.
     * @return void
     */
    public function logInfo(string $msg): void
    {
        $this->logger->info($msg);
    }

    /**
     * Log an error message.
     *
     * @param string $msg The message to log.
     * @return void
     */
    public function logError(string $msg): void
    {
        $this->logger->error($msg);
    }

    /**
     * Retrieve information about log files.
     *
     * @return array Information about log files.
     */
    public function getInfo(): array
    {
        $files = glob($this->logDir . '/*.log');
        if (!$files) {
            return [
                'dir'      => $this->logDir,
                'length'   => 0,
                'logs'     => [],
                'last'     => null
            ];
        }
        $files = array_combine($files, array_map('filemtime', $files));
        arsort($files);

        $logs = [];
        foreach ($files as $key => $value) {
            $logs[] = basename($key);
        }

        return [
            'dir'      => $this->logDir,
            'last'     => basename(key($files)),
            'logs'     => $logs,
            'length'   => count($logs)
        ];
    }

    /**
     * Retrieve details about a specific log file.
     *
     * @param string $filename The filename of the log file.
     * @param bool $replaceNewline Whether to replace newlines in log content.
     * @return array Details about the log file.
     */
    public function getLogDetail(string $filename, bool $replaceNewline = false): array
    {
        if ($filename == '') {
            return [];
        }
        $fle = $this->logDir . '/' . $filename;
        $content = file_get_contents($fle);
        if ($replaceNewline && $content !== false) {
            $content = str_replace("\n", '#@#', $content);
        }
        return [
            'filename'  => $fle,
            'content'   => $content,
            'size'      => $this->formatBytes($fle),
            'lines'    => count(file($fle)),
        ];
    }

    /**
     * Format file size in bytes to human-readable format.
     *
     * @param string $path The path to the file.
     * @return string The formatted file size.
     */
    private function formatBytes(string $path): string
    {
        $bytes = sprintf('%u', filesize($path));
        if ($bytes > 0) {
            $unit = intval(log($bytes, 1024));
            $units = ['B', 'KB', 'MB', 'GB'];
            if (array_key_exists($unit, $units) === true) {
                return sprintf('%d %s', $bytes / pow(1024, $unit), $units[$unit]);
            }
        }
        return $bytes;
    }
}
