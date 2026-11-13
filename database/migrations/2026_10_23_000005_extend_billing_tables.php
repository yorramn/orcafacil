<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('provider_customer_reference')->nullable()->after('provider_reference');
            $table->text('metadata_json')->nullable()->after('canceled_at');
            $table->index(['tenant_id', 'plan', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'plan', 'status']);
            $table->dropColumn(['provider_customer_reference', 'metadata_json']);
        });
    }
};
