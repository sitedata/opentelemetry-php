<?php

declare(strict_types=1);

namespace OpenTelemetry\SDK\Common\Export\Stream;

use BadMethodCallException;
use ErrorException;
use function fflush;
use function fwrite;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Common\Future\CompletedFuture;
use OpenTelemetry\SDK\Common\Future\ErrorFuture;
use OpenTelemetry\SDK\Common\Future\FutureInterface;
use function restore_error_handler;
use RuntimeException;
use function set_error_handler;
use function strlen;
use Throwable;

/**
 * @psalm-internal \OpenTelemetry
 */
final class StreamTransport implements TransportInterface
{

    /**
     * @var resource|null
     */
    private $stream;

    /**
     * @param resource $stream
     */
    public function __construct($stream)
    {
        $this->stream = $stream;
    }

    public function send(string $payload, string $contentType, ?CancellationInterface $cancellation = null): FutureInterface
    {
        if (!$this->stream) {
            return new ErrorFuture(new BadMethodCallException('Transport closed'));
        }

        set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        });

        try {
            $bytesWritten = fwrite($this->stream, $payload);
        } catch (Throwable $e) {
            return new ErrorFuture($e);
        } finally {
            restore_error_handler();
        }

        if ($bytesWritten !== strlen($payload)) {
            return new ErrorFuture(new RuntimeException(sprintf('Write failure, wrote %d of %d bytes', $bytesWritten, strlen($payload))));
        }

        return new CompletedFuture(null);
    }

    public function shutdown(?CancellationInterface $cancellation = null): bool
    {
        if (!$this->stream) {
            return false;
        }

        $flush = @fflush($this->stream);
        $this->stream = null;

        return $flush;
    }

    public function forceFlush(?CancellationInterface $cancellation = null): bool
    {
        if (!$this->stream) {
            return false;
        }

        return @fflush($this->stream);
    }
}
