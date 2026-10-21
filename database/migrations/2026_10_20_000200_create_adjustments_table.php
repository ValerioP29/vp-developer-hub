<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adjustments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $t->date('month');
            $t->string('label');
            $t->string('kind');
            $t->bigInteger('amount_cents');
            $t->text('note')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adjustments');
    }
};
