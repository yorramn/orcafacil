<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind')->default('person');
            $table->string('document')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('address')->nullable();
            $table->string('address_number')->nullable();
            $table->string('address_complement')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('active');
            $table->softDeletes();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'name']);
        });

        Schema::create('catalog_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('service');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('unit')->default('un');
            $table->unsignedBigInteger('default_unit_price_cents')->default(0);
            $table->unsignedBigInteger('default_cost_cents')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'active']);
            $table->index(['tenant_id', 'type']);
        });

        Schema::create('quotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->date('valid_until')->nullable();
            $table->unsignedBigInteger('subtotal_cents')->default(0);
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('extra_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->text('payment_terms')->nullable();
            $table->unsignedInteger('estimated_days')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('public_notes')->nullable();
            $table->string('public_token_hash', 64)->nullable()->unique();
            $table->timestamp('public_token_expires_at')->nullable();
            $table->timestamp('public_revoked_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('quote_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description_snapshot');
            $table->string('unit')->default('un');
            $table->decimal('quantity', 12, 3);
            $table->unsignedBigInteger('unit_price_cents');
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->unsignedBigInteger('subtotal_cents');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['tenant_id', 'quote_id']);
        });

        Schema::create('quote_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot_json');
            $table->unsignedBigInteger('total_cents');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['quote_id', 'version']);
        });

        Schema::create('quote_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event');
            $table->json('metadata_json')->nullable();
            $table->timestamp('occurred_at');
            $table->index(['tenant_id', 'quote_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_events');
        Schema::dropIfExists('quote_versions');
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('catalog_items');
        Schema::dropIfExists('clients');
    }
};
