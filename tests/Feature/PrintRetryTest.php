<?php

namespace Tests\Feature;

use App\Exceptions\PrinterUnavailableException;
use App\Jobs\PrintReceipt;
use App\Models\Receipt;
use App\Services\ReceiptPrinter;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PrintRetryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The suite defaults to the sync queue; these tests need the real
        // database queue so retries, delays and failures actually happen.
        config(['queue.default' => 'database']);
    }

    /**
     * A stand-in printer that fails the first $failures attempts, then works.
     */
    private function fakePrinter(int $failures): ReceiptPrinter
    {
        $printer = new class($failures) extends ReceiptPrinter
        {
            public int $attempts = 0;

            public int $printed = 0;

            public function __construct(private int $failuresLeft) {}

            public function print(Receipt $receipt): void
            {
                $this->attempts++;

                if ($this->failuresLeft > 0) {
                    $this->failuresLeft--;

                    throw new PrinterUnavailableException('printer offline');
                }

                $this->printed++;
            }
        };

        $this->app->instance(ReceiptPrinter::class, $printer);

        return $printer;
    }

    private function newReceipt(): Receipt
    {
        return Receipt::create(['transaction' => '01234', 'message' => 'hello']);
    }

    /** Run the queue worker for a single job, like the container's worker does. */
    private function runWorkerOnce(): void
    {
        Artisan::call('queue:work', ['--once' => true, '--sleep' => 0]);
    }

    /** Skip the waiting: make every delayed job due right now. */
    private function makeQueuedJobsDue(): void
    {
        DB::table('jobs')->update(['available_at' => now()->getTimestamp()]);
    }

    public function test_a_failed_print_is_retried_later_and_prints_once_the_printer_is_back(): void
    {
        Log::spy();
        $printer = $this->fakePrinter(failures: 2);
        $receipt = $this->newReceipt();

        PrintReceipt::dispatch($receipt);
        $this->assertDatabaseCount('jobs', 1);

        // Attempt 1: printer offline. The job is kept, delayed, not failed.
        $this->runWorkerOnce();
        $this->assertSame(1, $printer->attempts);
        $this->assertFalse($receipt->fresh()->has_printed);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertGreaterThanOrEqual(
            now()->getTimestamp() + 9,
            DB::table('jobs')->value('available_at'),
            'The retry should be delayed by the first backoff step (10s).',
        );

        // While it is delayed the worker must not pick it up again.
        $this->runWorkerOnce();
        $this->assertSame(1, $printer->attempts);

        // Attempt 2: still offline.
        $this->makeQueuedJobsDue();
        $this->runWorkerOnce();
        $this->assertSame(2, $printer->attempts);
        $this->assertFalse($receipt->fresh()->has_printed);

        // Attempt 3: the printer is back, so it prints and the job is done.
        $this->makeQueuedJobsDue();
        $this->runWorkerOnce();
        $this->assertSame(3, $printer->attempts);
        $this->assertSame(1, $printer->printed);
        $this->assertTrue($receipt->fresh()->has_printed);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);

        // The log stays quiet while retrying: one warning per failed attempt,
        // no error entries with stack traces, and a line once it finally prints.
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message) => $message === 'Printing failed, will retry.')
            ->twice();
        Log::shouldNotHaveReceived('error');
        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message, array $context = []) => $message === 'Printed receipt.' && ($context['attempts'] ?? null) === 3)
            ->once();
    }

    public function test_it_gives_up_when_the_retry_window_closes(): void
    {
        Log::spy();
        $printer = $this->fakePrinter(failures: 99);
        $receipt = $this->newReceipt();

        PrintReceipt::dispatch($receipt);

        $this->runWorkerOnce();
        $this->assertSame(1, $printer->attempts);

        // Past the 6 hour default window: no further attempt is made.
        $this->travel(7)->hours();
        $this->makeQueuedJobsDue();
        $this->runWorkerOnce();

        $this->assertSame(1, $printer->attempts);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertFalse($receipt->fresh()->has_printed);
        // Laravel's worker also logs job exceptions, so look for our own message.
        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message) => str_contains($message, 'Gave up printing receipt'))
            ->once();
    }

    public function test_the_retry_window_follows_the_configured_hours(): void
    {
        $this->freezeTime();
        config(['printer.retry_hours' => 2]);

        $job = new PrintReceipt($this->newReceipt());

        $this->assertSame(now()->addHours(2)->getTimestamp(), $job->retryUntil()->getTimestamp());
    }

    public function test_an_already_printed_receipt_is_never_printed_again(): void
    {
        $printer = $this->fakePrinter(failures: 0);
        $receipt = $this->newReceipt();
        $receipt->has_printed = true;
        $receipt->save();

        (new PrintReceipt($receipt))->handle($printer);

        $this->assertSame(0, $printer->attempts);
    }

    public function test_an_unreachable_printer_is_not_reported_but_other_errors_still_are(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        $this->assertFalse($handler->shouldReport(new PrinterUnavailableException('offline')));
        $this->assertTrue($handler->shouldReport(new \RuntimeException('something else')));
    }

    public function test_the_printer_service_sends_the_receipt_to_a_network_printer(): void
    {
        // A local TCP listener plays the part of the printer on port 9100.
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $this->assertNotFalse($server, "Could not open a test listener: {$errstr}");

        [$host, $port] = explode(':', stream_socket_get_name($server, false));
        config([
            'printer.host' => $host,
            'printer.port' => (int) $port,
            'printer.recipient' => 'MESSAGE FOR TESTING',
        ]);

        $receipt = new Receipt(['transaction' => '01234', 'message' => "Hello from the test\nsecond line"]);
        $receipt->created_at = now();

        (new ReceiptPrinter)->print($receipt);

        $connection = stream_socket_accept($server, 2);
        $this->assertNotFalse($connection, 'The printer service never connected.');
        stream_set_timeout($connection, 2);
        $bytes = stream_get_contents($connection);

        $this->assertStringContainsString('PING', $bytes);
        $this->assertStringContainsString('MESSAGE FOR TESTING', $bytes);
        $this->assertStringContainsString("Hello from the test\nsecond line", $bytes);
        $this->assertStringContainsString("\x1b@", $bytes, 'Printer should be initialised (ESC @).');
        $this->assertStringContainsString("\x1dV", $bytes, 'The paper should be cut (GS V).');

        // The label/value rows are padded to exactly the printer width.
        $this->assertSame(1, preg_match('/TRANSACTION #: +01234/', $bytes, $row));
        $this->assertSame(48, strlen($row[0]));
    }

    public function test_the_printer_service_gives_up_quickly_when_the_printer_is_unreachable(): void
    {
        // Nothing listens on port 1. Whether the connection is refused at once
        // or left hanging, it must fail within the configured 1 second timeout
        // rather than the library's 60 second default.
        config(['printer.host' => '127.0.0.1', 'printer.port' => 1, 'printer.timeout' => 1]);

        $started = microtime(true);

        try {
            (new ReceiptPrinter)->print($this->newReceipt());
            $this->fail('Printing to an unreachable printer must throw so the queue can retry.');
        } catch (PrinterUnavailableException $e) {
            $this->assertLessThan(4, microtime(true) - $started);
            $this->assertStringContainsString('127.0.0.1:1', $e->getMessage());
            $this->assertNotNull($e->getPrevious(), 'The original connection error should be kept.');
        }
    }
}
