<?php

/**
 * A very simple class for using Daemons.
 *
 * PHP version 8.0+
 *
 * @package TiMMiT/Daemon
 * @author  Tim Schoondergang <tim.schoondergang@TiMMiT.nl>
 */

declare(strict_types=1);

namespace TiMMiT;

/**
 * Daemon class.
 */
class Daemon
{
    /**
     * Daemonize a callback
     *
     * @param string[] $options  Set of options
     * @param callable $callable Callback to daemonize
     *
     * @return void on success, throws an Exception otherwise
     *
     * @throws \Exception If there is an error.
     */
    public static function work(array $options, callable $callable): void
    {
        if (!isset($options['pid'])) {
            throw new \Exception('pid not specified');
        }

        $options = $options + array(
            'stdin'  => '/dev/null',
            'stdout' => '/dev/null',
            'stderr' => 'php://stdout',
        );

        if (false === ($lock = @fopen($options['pid'], 'c+'))) {
            throw new \Exception('unable to open pid file ' . $options['pid']);
        }

        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            throw new \Exception('could not acquire lock for ' . $options['pid']);
        }

        switch ($pid = pcntl_fork()) {
            case -1:
                throw new \Exception('unable to fork');
            case 0:
                break;
            default:
                fseek($lock, 0);
                ftruncate($lock, 0);
                fwrite($lock, (string)$pid);
                fflush($lock);
                return;
        }

        $stderr = null;
        try {
            if (-1 === posix_setsid()) {
                throw new \Exception('failed to setsid');
            }

            if (defined('STDIN') && 'resource' === gettype(STDIN)) {
                fclose(STDIN);
            }
            if (defined('STDOUT') && 'resource' === gettype(STDOUT)) {
                fclose(STDOUT);
            }
            if (defined('STDERR') && 'resource' === gettype(STDERR)) {
                fclose(STDERR);
            }

            $stdin  = fopen($options['stdin'], 'r');
            if (!is_resource($stdin)) {
                throw new \Exception('failed to open STDIN ' . $options['stdin']);
            }
            if (!defined('DAEMON_STDIN')) {
                define('DAEMON_STDIN', $stdin);
            }

            $stdout = fopen($options['stdout'], 'w');
            if (!is_resource($stdout)) {
                throw new \Exception('failed to open STDOUT ' . $options['stdout']);
            }
            if (!defined('DAEMON_STDOUT')) {
                define('DAEMON_STDOUT', $stdout);
            }

            $stderr = fopen($options['stderr'], 'w');
            if (!is_resource($stderr)) {
                throw new \Exception('failed to open STDERR ' . $options['stderr']);
            }
            if (!defined('DAEMON_STDERR')) {
                define('DAEMON_STDERR', $stderr);
            }

            pcntl_signal(SIGTSTP, SIG_IGN);
            pcntl_signal(SIGTTOU, SIG_IGN);
            pcntl_signal(SIGTTIN, SIG_IGN);
            pcntl_signal(SIGHUP, SIG_IGN);

            call_user_func($callable, $stdin, $stdout, $stderr);
        } catch (\Throwable $e) {
            self::reportChildFailure($e, $stderr);
            exit(1);
        }
    }

    /**
     * Report failures that happen after fork but before or during the callback.
     *
     * @param \Throwable $e
     * @param mixed $stderr
     *
     * @return void
     */
    private static function reportChildFailure(\Throwable $e, $stderr = null): void
    {
        $pid = getmypid();
        $message = sprintf(
            '[daemon child%s] %s: %s in %s:%d',
            false === $pid ? '' : ' ' . $pid,
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
        );

        if (is_resource($stderr)) {
            fwrite($stderr, $message . PHP_EOL);
        }
        error_log($message);
    }

    /**
     * Checks whether a daemon process specified by its PID file is running.
     *
     * @param string $file Daemon PID file
     *
     * @return bool True if the daemon is still running, false otherwise
     *
     * @throws \Exception If pid file is not readable.
     */
    public static function isRunning(string $file): bool
    {
        if (!is_readable($file)) {
            return false;
        }

        if (false === ($lock = @fopen($file, 'c+'))) {
            throw new \Exception('unable to open pid file ' . $file);
        }

        if (flock($lock, LOCK_EX | LOCK_NB)) {
            return false;
        } else {
            flock($lock, LOCK_UN);
            return true;
        }
    }

    /**
     * Kills a daemon process specified by its PID file.
     *
     * @param string $file   Daemon PID file
     * @param bool   $delete Flag to delete PID file after killing
     * @param int $signal Signal
     *
     * @link http://php.net/manual/en/pcntl.constants.php PCNTL signals
     * @return bool TRUE on success, FALSE otherwise
     *
     * @throws \Exception On error.
     */
    public static function kill(string $file, bool $delete = false, int $signal = SIGTERM): bool
    {
        if (!is_readable($file)) {
            throw new \Exception('unreadable pid file ' . $file);
        }

        if (($lock = @fopen($file, 'c+')) === false) {
            throw new \Exception('unable to open pid file ' . $file);
        }

        if (flock($lock, LOCK_EX | LOCK_NB)) {
            flock($lock, LOCK_UN);
            throw new \Exception('process not running');
        }

        $pid = fgets($lock);
        if (false !== $pid) {
            if (posix_kill((int)$pid, $signal)) {
                if ($delete) {
                    unlink($file);
                }
                return true;
            }
        }
        return false;
    }
}
