<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_events', function (Blueprint $table): void {
            $table->string('request_id')->nullable()->after('occurred_at');
            $table->string('ip_hash', 64)->nullable()->after('request_id');
            $table->index(['tenant_id', 'request_id']);
        });
    }

    public function down(): void
    {
        Schema::table('quote_events', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'request_id']);
            $table->dropColumn(['request_id', 'ip_hash']);
        });
    }
};
