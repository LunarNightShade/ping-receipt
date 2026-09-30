<?php

namespace Tests\Feature;

use App\Jobs\PrintReceipt;
use App\Models\Receipt;
use App\Services\ReceiptPrinter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SendMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_form_page_loads(): void
    {
        $this->withoutVite();

        $this->get('/')->assertOk();
    }

    public function test_a_valid_message_is_stored_and_queued_for_printing(): void
    {
        Bus::fake();

        $response = $this->post('/send-message', ['message' => 'Hello, desk!']);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('receipts', ['message' => 'Hello, desk!']);
        Bus::assertDispatchedAfterResponse(PrintReceipt::class);
    }

    public function test_a_transaction_number_is_generated_server_side(): void
    {
        Bus::fake();

        // A transaction sent by the client should be ignored.
        $this->post('/send-message', ['message' => 'hi', 'transaction' => 'HACKED']);

        $receipt = Receipt::first();
        $this->assertNotNull($receipt);
        $this->assertNotSame('HACKED', $receipt->transaction);
        $this->assertMatchesRegularExpression('/^\d{5}$/', $receipt->transaction);
    }

    public function test_an_empty_message_is_rejected(): void
    {
        $response = $this->post('/send-message', ['message' => '']);

        $response->assertSessionHasErrors('message');
        $this->assertDatabaseCount('receipts', 0);
    }

    public function test_non_ascii_characters_are_rejected(): void
    {
        $response = $this->post('/send-message', ['message' => 'emoji 😀 here']);

        $response->assertSessionHasErrors('message');
        $this->assertDatabaseCount('receipts', 0);
    }

    public function test_smart_quotes_are_converted_and_accepted(): void
    {
        Bus::fake();

        $response = $this->post('/send-message', ['message' => '“curly” ‘quotes’']);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('receipts', ['message' => '"curly" \'quotes\'']);
    }

    public function test_the_printing_job_marks_the_receipt_as_printed(): void
    {
        $receipt = Receipt::create(['transaction' => '01234', 'message' => 'test']);

        $spy = new class extends ReceiptPrinter
        {
            public int $calls = 0;

            public function print(Receipt $receipt): void
            {
                $this->calls++;
            }
        };

        (new PrintReceipt($receipt))->handle($spy);

        $this->assertSame(1, $spy->calls);
        $this->assertTrue($receipt->fresh()->has_printed);
    }

    public function test_a_printer_failure_is_swallowed_and_leaves_the_receipt_unprinted(): void
    {
        $receipt = Receipt::create(['transaction' => '01234', 'message' => 'test']);

        $failing = new class extends ReceiptPrinter
        {
            public function print(Receipt $receipt): void
            {
                throw new \RuntimeException('printer offline');
            }
        };

        // handle() must not re-throw (the visitor already got a response).
        (new PrintReceipt($receipt))->handle($failing);

        $this->assertFalse($receipt->fresh()->has_printed);
    }
}
