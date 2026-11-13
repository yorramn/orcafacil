<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_billings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('billed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('total_cents');
            $table->timestamp('billed_at');
            $table->timestamps();
        });

        Schema::table('receivables', function (Blueprint $table): void {
            $table->foreignId('service_billing_id')->nullable()->after('service_id')->constrained('service_billings')->nullOnDelete();
            $table->text('cancellation_reason')->nullable()->after('canceled_at');
            $table->unique(['service_billing_id', 'installment_number']);
        });
    }

    public function down(): void
    {
        Schema::table('receivables', function (Blueprint $table): void {
            $table->dropUnique(['service_billing_id', 'installment_number']);
            $table->dropConstrainedForeignId('service_billing_id');
            $table->dropColumn('cancellation_reason');
        });
        Schema::dropIfExists('service_billings');
    }
};
