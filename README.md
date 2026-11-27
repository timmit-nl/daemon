# timmit/daemon

Helpers for starting and stopping a daemonized PHP process. This package is a fork of [clio](https://github.com/nramenta/clio), reduced to process control. Console input and color live in [timmit/console](https://github.com/timmit-nl/console).

## Install

```bash
composer require timmit/daemon
```

Requires PHP >= 8.0, `ext-pcntl`, and `ext-posix`. It is meant for POSIX systems.

## Daemon::isRunning(string $file): bool

Returns whether the process in a PID file is still running. The argument is the path of the PID file, not a process id. A missing file returns `false`.

```php
use TiMMiT\Daemon;

if (Daemon::isRunning('/path/to/process.pid')) {
    echo "daemon is running.\n";
}
```

## Daemon::work(array $options, callable $callable): void

Forks `$callable` into the background. `$options['pid']` is required and is the PID file path. `stdin` and `stdout` default to `/dev/null`. `stderr` defaults to `php://stdout`.

In the child, the opened streams are also defined as `DAEMON_STDIN`, `DAEMON_STDOUT`, and `DAEMON_STDERR` when those constants do not exist yet. `timmit/console` reads those constants.

A throwable from the child is written to the stderr stream and the error log. The child then exits with status 1.

```php
use TiMMiT\Daemon;

if (!Daemon::isRunning('/path/to/process.pid')) {
    Daemon::work(
        [
            'pid' => '/path/to/process.pid',
            'stdin' => '/dev/null',
            'stdout' => '/path/to/stdout.txt',
            'stderr' => '/path/to/stderr.txt',
        ],
        function ($stdin, $stdout, $stderr): void {
            while (true) {
                sleep(1);
            }
        }
    );
}
```

The parent writes the child process id into the PID file and returns. The three callback parameters are optional.

## Daemon::kill(string $file, bool $delete = false, int $signal = SIGTERM): bool

Sends `$signal` to the process id stored in the PID file. When `$delete` is `true`, the PID file is removed after the signal is sent. Throws when the file cannot be read or the process is not running.

```php
use TiMMiT\Daemon;

if (Daemon::isRunning('/path/to/process.pid')) {
    Daemon::kill('/path/to/process.pid', true);
}
```

## License

MIT.

## Credits

- Nofriandi Ramenta, author of the original [clio](https://github.com/nramenta/clio) source
- oNeDaL, author of the first fork
- Tim Schoondergang
