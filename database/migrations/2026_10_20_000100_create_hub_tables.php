<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('vat_number')->nullable();
            $t->string('tax_code')->nullable();
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->text('address')->nullable();
            $t->string('contact_name')->nullable();
            $t->string('status')->default('active');
            $t->boolean('email_notifications')->default(false);
            $t->timestamps();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('role')->default('client_user');
            $t->boolean('active')->default(true);
            $t->json('permissions')->nullable();
            $t->text('two_factor_secret')->nullable();
            $t->text('two_factor_recovery_codes')->nullable();
            $t->timestamp('two_factor_confirmed_at')->nullable();
        });
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->boolean('maintenance')->default(true);
            $t->timestamps();
        });
        Schema::create('packages', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->unsignedInteger('included_minutes');
            $t->unsignedBigInteger('price_cents');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('projects', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('name');
            $t->text('description')->nullable();
            $t->string('status')->default('active');
            $t->timestamps();
        });
        Schema::create('applications', function (Blueprint $t) {
            $this->tenant($t);
            $this->link($t, 'project', 'projects');
            $t->string('name');
            $t->text('description')->nullable();
            $t->string('status')->default('active');
            $t->timestamps();
        });
        Schema::create('services', function (Blueprint $t) {
            $this->tenant($t);
            $this->link($t, 'application', 'applications');
            $t->string('name');
            $t->text('description')->nullable();
            $t->string('status')->default('active');
            $t->timestamps();
        });
        Schema::create('contracts', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('name');
            $t->string('model');
            $t->date('starts_on');
            $t->date('ends_on')->nullable();
            $t->unsignedBigInteger('fee_cents')->default(0);
            $t->unsignedBigInteger('hourly_rate_cents')->default(0);
            $t->unsignedInteger('included_minutes')->default(0);
            $t->boolean('carryover')->default(false);
            $t->text('included_activities')->nullable();
            $t->text('excluded_activities')->nullable();
            $t->text('extra_terms')->nullable();
            $t->string('status')->default('active');
            $t->timestamps();
        });
        Schema::create('activations', function (Blueprint $t) {
            $this->tenant($t);
            $this->link($t, 'contract', 'contracts', false);
            $t->foreignId('package_id')->nullable()->constrained()->restrictOnDelete();
            $t->date('month');
            $t->string('label');
            $t->string('model');
            $t->unsignedInteger('included_minutes');
            $t->unsignedInteger('carried_minutes')->default(0);
            $t->unsignedBigInteger('fee_cents');
            $t->unsignedBigInteger('hourly_rate_cents');
            $t->boolean('carryover')->default(false);
            $t->text('terms')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['tenant_id', 'month']);
        });
        Schema::create('tickets', function (Blueprint $t) {
            $this->tenant($t);
            $this->link($t, 'application', 'applications');
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('title');
            $t->text('description');
            $t->string('status')->default('new');
            $t->string('priority')->default('normal');
            $t->timestamps();
        });
        Schema::create('ticket_comments', function (Blueprint $t) {
            $this->tenant($t);
            $this->link($t, 'ticket', 'tickets', false);
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->text('body');
            $t->boolean('internal')->default(false);
            $t->timestamps();
        });
        Schema::create('interventions', function (Blueprint $t) {
            $this->tenant($t);
            $this->link($t, 'project', 'projects');
            $this->link($t, 'application', 'applications');
            $this->link($t, 'ticket', 'tickets');
            $t->foreignId('category_id')->constrained()->restrictOnDelete();
            $t->date('performed_on');
            $t->string('title');
            $t->text('description')->nullable();
            $t->text('public_notes')->nullable();
            $t->text('internal_notes')->nullable();
            $t->string('status')->default('planned');
            $t->boolean('billable')->default(true);
            $t->boolean('uses_package')->default(true);
            $t->timestamps();
        });
        Schema::create('time_entries', function (Blueprint $t) {
            $this->tenant($t);
            $this->link($t, 'intervention', 'interventions', false);
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->timestampTz('started_at');
            $t->timestampTz('ended_at');
            $t->unsignedInteger('seconds');
            $t->unsignedInteger('version')->default(1);
            $t->string('source')->default('manual');
            $t->text('correction_reason')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'started_at', 'ended_at']);
        });
        Schema::create('timers', function (Blueprint $t) {
            $this->tenant($t);
            $this->link($t, 'intervention', 'interventions', false);
            $t->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $t->timestampTz('started_at')->nullable();
            $t->timestamps();
        });
        Schema::create('approvals', function (Blueprint $t) {
            $this->tenant($t);
            $this->link($t, 'ticket', 'tickets');
            $t->date('month');
            $t->string('title');
            $t->text('description');
            $t->unsignedInteger('minutes');
            $t->unsignedBigInteger('amount_cents');
            $t->string('status')->default('pending');
            $t->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->text('decision_note')->nullable();
            $t->timestamps();
        });
        Schema::create('documents', function (Blueprint $t) {
            $this->tenant($t);
            $this->link($t, 'ticket', 'tickets');
            $this->link($t, 'contract', 'contracts');
            $t->uuid('series');
            $t->unsignedInteger('version')->default(1);
            $t->string('title');
            $t->string('category');
            $t->string('original_name');
            $t->string('path');
            $t->string('mime');
            $t->unsignedBigInteger('size');
            $t->boolean('client_visible')->default(true);
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['series', 'version']);
        });
        Schema::create('summaries', function (Blueprint $t) {
            $this->tenant($t);
            $t->date('month');
            $t->json('snapshot');
            $t->unsignedBigInteger('total_cents');
            $t->string('status')->default('to_invoice');
            $t->string('invoice_number')->nullable();
            $t->date('invoice_date')->nullable();
            $t->date('paid_on')->nullable();
            $t->timestamp('finalized_at');
            $t->timestamps();
            $t->unique(['tenant_id', 'month']);
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('event');
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['tenant_id', 'created_at']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE time_entries ADD CONSTRAINT positive_time CHECK (ended_at > started_at AND seconds > 0 AND seconds = EXTRACT(EPOCH FROM ended_at - started_at))');
            DB::statement("ALTER TABLE users ADD CONSTRAINT valid_user_tenant CHECK ((role = 'super_admin' AND tenant_id IS NULL) OR (role IN ('client_admin', 'client_user') AND tenant_id IS NOT NULL))");
            DB::statement('ALTER TABLE activations ADD CONSTRAINT activation_month CHECK (EXTRACT(DAY FROM month) = 1)');
            DB::statement('ALTER TABLE summaries ADD CONSTRAINT summary_month CHECK (EXTRACT(DAY FROM month) = 1)');
        }
    }

    private function tenant(Blueprint $t): void
    {
        $t->id();
        $t->foreignId('tenant_id')->constrained()->restrictOnDelete();
        $t->unique(['id', 'tenant_id']);
    }

    private function link(Blueprint $t, string $name, string $table, bool $nullable = true): void
    {
        $column = $t->unsignedBigInteger($name.'_id');
        if ($nullable) {
            $column->nullable();
        }
        $t->foreign([$name.'_id', 'tenant_id'])->references(['id', 'tenant_id'])->on($table)->restrictOnDelete();
    }

    public function down(): void
    {
        foreach (['audit_logs', 'summaries', 'documents', 'approvals', 'timers', 'time_entries', 'interventions', 'ticket_comments', 'tickets', 'activations', 'contracts', 'services', 'applications', 'projects', 'packages', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('tenant_id');
            $t->dropColumn(['role', 'active', 'permissions', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
        Schema::dropIfExists('tenants');
    }
};
