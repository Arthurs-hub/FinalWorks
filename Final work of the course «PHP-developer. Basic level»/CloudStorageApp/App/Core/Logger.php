<?php

namespace App\Core;

use Exception;

/**
 * Logger class for application logging
 *
 * @method static void info(string $message, array $context = [])
 * @method static void warning(string $message, array $context = [])
 * @method static void error(string $message, array $context = [])
 * @method static void debug(string $message, array $context = [])
 * @method static array getRecentLogs(int $limit = 100, string $level = 'all')
 * @method static string getLogFileSize()
 * @method static bool clearLogs()
 * @method static array getLogStats()
 * @method static void accessLog(string $method, string $uri, int $responseCode, float $executionTime)
 */
class Logger
{
    private static string $logFile;
    private static string $logDir;

    public static function init(): void
    {
        self::$logDir = __DIR__ . '/../logs/';
        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }
        self::$logFile = self::$logDir . 'app_' . date('Y-m-d') . '.log';
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log('DEBUG', $message, $context);
    }

    private static function log(string $level, string $message, array $context = []): void
    {
        if (!isset(self::$logFile)) {
            self::init();
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logEntry = "[$timestamp] [$level] $message$contextStr" . PHP_EOL;

        file_put_contents(self::$logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public static function getRecentLogs(int $limit = 100, string $level = 'all'): array
    {
        try {
            if (!isset(self::$logDir)) {
                self::init();
            }

            $logs = [];
            $logFiles = glob(self::$logDir . 'app_*.log');

            rsort($logFiles);

            foreach ($logFiles as $logFile) {
                if (!file_exists($logFile)) {
                    continue;
                }

                $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (!$lines) {
                    continue;
                }

                $lines = array_reverse($lines);

                foreach ($lines as $line) {
                    if (count($logs) >= $limit) {
                        break 2;
                    }

                    $parsed = self::parseLogLine($line);
                    if ($parsed && ($level === 'all' || strtolower($parsed['level']) === strtolower($level))) {
                        $logs[] = $parsed;
                    }
                }
            }

            return $logs;
        } catch (Exception $e) {
            return [];
        }
    }

    private static function parseLogLine(string $line): ?array
    {
        if (preg_match('/^\[([^\]]+)\] \[([^\]]+)\] (.+)$/', $line, $matches)) {
            $timestamp = $matches[1];
            $level = $matches[2];
            $messageAndContext = $matches[3];

            $message = $messageAndContext;
            $context = [];

            if (preg_match('/^(.+?) (\{.+\})$/', $messageAndContext, $contextMatches)) {
                $message = $contextMatches[1];
                $jsonContext = $contextMatches[2];
                $decodedContext = json_decode($jsonContext, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $context = $decodedContext;
                }
            }

            return [
                'timestamp' => $timestamp,
                'level' => strtolower($level),
                'message' => $message,
                'context' => $context,
            ];
        }

        return null;
    }

    public static function clearLogs(): bool
    {
        try {
            if (!isset(self::$logDir)) {
                self::init();
            }

            $logFiles = glob(self::$logDir . '*.log');
            $deletedCount = 0;

            foreach ($logFiles as $logFile) {
                if (unlink($logFile)) {
                    $deletedCount++;
                }
            }

            self::info("Logs cleared", ['deleted_files' => $deletedCount]);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function getLogFileSize(): string
    {
        try {
            if (!isset(self::$logDir)) {
                self::init();
            }

            $totalSize = 0;
            $logFiles = glob(self::$logDir . '*.log');

            foreach ($logFiles as $logFile) {
                if (file_exists($logFile)) {
                    $totalSize += filesize($logFile);
                }
            }

            return self::formatFileSize($totalSize);
        } catch (Exception $e) {
            return '0 B';
        }
    }

    private static function formatFileSize(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public static function getLogStats(): array
    {
        try {
            if (!isset(self::$logDir)) {
                self::init();
            }

            $stats = [
                'total_files' => 0,
                'total_size' => 0,
                'by_level' => [
                    'info' => 0,
                    'warning' => 0,
                    'error' => 0,
                    'debug' => 0,
                ],
                'recent_errors' => [],
            ];

            $logFiles = glob(self::$logDir . '*.log');
            $stats['total_files'] = count($logFiles);

            foreach ($logFiles as $logFile) {
                if (file_exists($logFile)) {
                    $stats['total_size'] += filesize($logFile);

                    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    if ($lines) {
                        foreach ($lines as $line) {
                            $parsed = self::parseLogLine($line);
                            if ($parsed) {
                                $level = strtolower($parsed['level']);
                                if (isset($stats['by_level'][$level])) {
                                    $stats['by_level'][$level]++;
                                }

                                if ($level === 'error' && count($stats['recent_errors']) < 10) {
                                    $stats['recent_errors'][] = $parsed;
                                }
                            }
                        }
                    }
                }
            }

            $stats['total_size_formatted'] = self::formatFileSize($stats['total_size']);

            return $stats;
        } catch (Exception $e) {
            return [
                'total_files' => 0,
                'total_size' => 0,
                'total_size_formatted' => '0 B',
                'by_level' => ['info' => 0, 'warning' => 0, 'error' => 0, 'debug' => 0],
                'recent_errors' => [],
            ];
        }
    }

    public static function accessLog(string $method, string $uri, int $responseCode, float $executionTime): void
    {
        $message = sprintf(
            'Access log: %s %s - %d (%.3f sec)',
            $method,
            $uri,
            $responseCode,
            $executionTime
        );

        self::info($message);
    }
}
