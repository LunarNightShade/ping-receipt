<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mark every receipt that already exists as printed.
     *
     * The has_printed flag was added without anything setting it, so all
     * receipts stored before automatic printing and retries existed are
     * flagged "not printed". They were sent to the printer by the old flow,
     * so treat them as done. Otherwise `receipts:reprint` would consider the
     * whole message history unprinted and print every old message again.
     */
    public function up(): void
    {
        DB::table('receipts')->update(['has_printed' => true]);
    }

    /**
     * Nothing to undo: which receipts were really printed before this point
     * cannot be worked out afterwards.
     */
    public function down(): void
    {
        //
    }
};
