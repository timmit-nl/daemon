<?php

/**
 * A very simple class for using Daemons.
 *
 * PHP version 7.4+
 *
 * @package TiMMiT/Daemon
 * @author  Tim Schoondergang <tim.schoondergang@TiMMiT.nl>
 */

namespace TiMMiT;

/**
 * Daemon class.
 */
class Daemon
{
    /**
     * Daemonize a callback
     *
     * @param array $options Set of options
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

        if (($lock = @fopen($options['pid'], 'c+')) === false) {
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

        if (posix_setsid() === -1) {
            throw new \Exception('failed to setsid');
        }

        if (defined('STDIN') && is_resource(STDIN)) {
            fclose(STDIN);
        }
        if (defined('STDOUT') && is_resource(STDOUT)) {
            fclose(STDOUT);
        }
        if (defined('STDERR') && is_resource(STDERR)) {
            fclose(STDERR);
        }

        if (!($stdin  = fopen($options['stdin'], 'r'))) {
            throw new \Exception('failed to open STDIN ' . $options['stdin']);
        }
        if (!defined('DAEMON_STDIN')) {
            define('DAEMON_STDIN', $stdin);
        }

        if (!($stdout = fopen($options['stdout'], 'w'))) {
            throw new \Exception('failed to open STDOUT ' . $options['stdout']);
        }
        if (!defined('DAEMON_STDOUT')) {
            define('DAEMON_STDOUT', $stdout);
        }

        if (!($stderr = fopen($options['stderr'], 'w'))) {
            throw new \Exception('failed to open STDERR ' . $options['stderr']);
        }
        if (!defined('DAEMON_STDERR')) {
            define('DAEMON_STDERR', $stderr);
        }

        pcntl_signal(SIGTSTP, SIG_IGN);
        pcntl_signal(SIGTTOU, SIG_IGN);
        pcntl_signal(SIGTTIN, SIG_IGN);
        pcntl_signal(SIGHUP,  SIG_IGN);

        call_user_func($callable, $stdin, $stdout, $stderr);
    }

    /**
     * Checks whether a daemon process specified by its PID file is running.
     *
     * @param string $file   Daemon PID file
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

        if (($lock = @fopen($file, 'c+')) === false) {
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
     *
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
        if ($pid !== false) {
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
