<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table): void {
            $table->boolean('is_rich_text')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table): void {
            $table->dropColumn('is_rich_text');
        });
    }
};
