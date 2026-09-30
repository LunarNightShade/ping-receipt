<?php

namespace Tests\Feature;

use App\Models\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkExistingReceiptsPrintedTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_receipts_are_marked_printed_so_they_are_not_reprinted(): void
    {
        // Two receipts as they exist in a database from before the flag was used.
        Receipt::create(['transaction' => '00001', 'message' => 'old one']);
        Receipt::create(['transaction' => '00002', 'message' => 'old two']);
        $this->assertSame(2, Receipt::where('has_printed', false)->count());

        $migration = require database_path('migrations/2026_09_29_230000_mark_existing_receipts_as_printed.php');
        $migration->up();

        $this->assertSame(0, Receipt::where('has_printed', false)->count());
        $this->assertSame(2, Receipt::where('has_printed', true)->count());
    }

    public function test_the_reprint_command_then_has_nothing_to_do_for_old_receipts(): void
    {
        Receipt::create(['transaction' => '00001', 'message' => 'old one']);

        $migration = require database_path('migrations/2026_09_29_230000_mark_existing_receipts_as_printed.php');
        $migration->up();

        $this->artisan('receipts:reprint')
            ->expectsOutput('Nothing to reprint.')
            ->assertSuccessful();
    }
}
