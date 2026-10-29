<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('legal_name')->nullable();
            $t->string('tax_id')->nullable();
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->text('address')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('role')->default('client');
            $t->boolean('active')->default(true);
            $t->softDeletes();
        });
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin','agent','client') AND (role <> 'client' OR company_id IS NOT NULL))");
        Schema::create('ticket_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('tickets', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->string('ticket_number')->unique();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('category_id')->constrained('ticket_categories')->restrictOnDelete();
            $t->string('subject');
            $t->text('description');
            $t->string('priority');
            $t->string('status')->default('new');
            $t->string('source')->default('portal');
            $t->timestampTz('first_responded_at')->nullable();
            $t->timestampTz('resolved_at')->nullable();
            $t->timestampTz('closed_at')->nullable();
            $t->timestampsTz();
            $t->softDeletesTz();
            $t->index('status');
            $t->index('priority');
            $t->index('created_at');
            $t->index(['company_id', 'status']);
            $t->index(['assigned_to', 'status']);
            $t->index(['category_id', 'status']);
        });
        DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_status_check CHECK (status IN ('new','assigned','in_progress','waiting_customer','resolved','closed','cancelled'))");
        DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_priority_check CHECK (priority IN ('low','medium','high','critical'))");
        Schema::create('ticket_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->text('body');
            $t->boolean('is_internal')->default(false);
            $t->timestampsTz();
            $t->softDeletesTz();
            $t->index(['ticket_id', 'created_at']);
        });
        Schema::create('ticket_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('event_type');
            $t->jsonb('old_values')->nullable();
            $t->jsonb('new_values')->nullable();
            $t->jsonb('metadata')->nullable();
            $t->timestampTz('created_at')->useCurrent();
            $t->index(['ticket_id', 'created_at']);
        });
        DB::unprepared("CREATE OR REPLACE FUNCTION protect_ticket_events() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Ticket audit is append-only'; END; $$; CREATE TRIGGER ticket_events_immutable BEFORE UPDATE OR DELETE ON ticket_events FOR EACH ROW EXECUTE FUNCTION protect_ticket_events();");
        Schema::create('ticket_attachments', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('path');
            $t->string('original_name');
            $t->string('mime_type');
            $t->unsignedBigInteger('size');
            $t->timestampsTz();
            $t->softDeletesTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_attachments');
        Schema::dropIfExists('ticket_events');
        DB::statement('DROP FUNCTION IF EXISTS protect_ticket_events()');
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_categories');
        Schema::table('users', function (Blueprint $t) {
            $t->dropForeign(['company_id']);
            $t->dropColumn(['company_id', 'role', 'active', 'deleted_at']);
        });
        Schema::dropIfExists('companies');
    }
};
