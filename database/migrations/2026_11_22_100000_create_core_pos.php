<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('pos_billing_mode', 12)->default('pay_first');
            $table->json('pos_payment_methods')->nullable();
        });

        Schema::create('menu_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['restaurant_id', 'id']);
            $table->unique(['restaurant_id', 'name']);
        });

        Schema::create('menu_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id');
            $table->foreignId('category_id');
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->unsignedBigInteger('price_minor');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['restaurant_id', 'id']);
            $table->index(['restaurant_id', 'name']);
            $table->foreign(['restaurant_id', 'category_id'])->references(['restaurant_id', 'id'])->on('menu_categories')->restrictOnDelete();
        });

        Schema::create('branch_products', function (Blueprint $table) {
            $this->branchOwner($table);
            $table->foreignId('product_id');
            $table->boolean('available');
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->primary(['restaurant_id', 'branch_id', 'product_id']);
            $table->foreign(['restaurant_id', 'product_id'])->references(['restaurant_id', 'id'])->on('menu_products')->restrictOnDelete();
        });

        Schema::create('pos_bills', function (Blueprint $table) {
            $table->id();
            $this->branchOwner($table);
            $table->string('mode', 12);
            $table->string('status', 12);
            $table->boolean('simulated');
            $table->string('visit_label', 80)->nullable();
            $table->unsignedBigInteger('settled_total_minor')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->unique(['restaurant_id', 'branch_id', 'id']);
            $table->index(['restaurant_id', 'branch_id', 'status', 'id']);
            $table->index(['restaurant_id', 'branch_id', 'simulated', 'settled_at'], 'pos_sales_index');
        });

        Schema::create('pos_orders', function (Blueprint $table) {
            $table->id();
            $this->billOwner($table);
            $table->string('number', 36)->unique();
            $table->string('collection_token', 40)->nullable();
            $table->string('status', 12);
            $table->string('preparation_status', 12);
            $table->foreignId('staff_id');
            $table->unsignedBigInteger('subtotal_minor');
            foreach (['discount', 'vat', 'sd', 'service', 'total'] as $amount) {
                $table->unsignedBigInteger($amount.'_minor');
            }
            $table->timestamps();
            $table->unique(['restaurant_id', 'branch_id', 'id']);
            $table->foreign(['restaurant_id', 'staff_id'])->references(['restaurant_id', 'id'])->on('staff')->restrictOnDelete();
        });

        Schema::create('pos_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id');
            $table->foreignId('branch_id');
            $table->foreignId('order_id');
            $table->foreignId('product_id');
            $table->string('product_name', 120);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_minor');
            $table->string('note', 250)->nullable();
            $table->foreign(['restaurant_id', 'branch_id', 'order_id'])->references(['restaurant_id', 'branch_id', 'id'])->on('pos_orders')->restrictOnDelete();
            $table->foreign(['restaurant_id', 'product_id'])->references(['restaurant_id', 'id'])->on('menu_products')->restrictOnDelete();
        });

        Schema::create('pos_payments', function (Blueprint $table) {
            $table->id();
            $this->billOwner($table);
            $table->string('kind', 12);
            $table->string('method', 12);
            $table->unsignedBigInteger('amount_minor');
            $table->string('reference', 120)->nullable();
            $table->foreignId('staff_id');
            $table->timestamp('created_at');
            $table->unique(['restaurant_id', 'branch_id', 'bill_id', 'kind'], 'pos_payment_once_per_kind');
            $table->foreign(['restaurant_id', 'staff_id'])->references(['restaurant_id', 'id'])->on('staff')->restrictOnDelete();
        });

        Schema::create('pos_requests', function (Blueprint $table) {
            $this->billOwner($table);
            $table->uuid('request_key');
            $table->string('fingerprint', 64);
            $table->foreignId('staff_id');
            $table->timestamp('created_at');
            $table->primary(['restaurant_id', 'branch_id', 'request_key']);
            $table->foreign(['restaurant_id', 'staff_id'])->references(['restaurant_id', 'id'])->on('staff')->restrictOnDelete();
        });

        Schema::create('pos_audit_events', function (Blueprint $table) {
            $table->id();
            $this->branchOwner($table);
            $table->foreignId('staff_id');
            $table->string('event', 32);
            $table->json('details');
            $table->timestamp('created_at');
            $table->foreign(['restaurant_id', 'staff_id'])->references(['restaurant_id', 'id'])->on('staff')->restrictOnDelete();
        });
    }

    private function branchOwner(Blueprint $table): void
    {
        $table->foreignId('restaurant_id');
        $table->foreignId('branch_id');
        $table->foreign(['restaurant_id', 'branch_id'])->references(['restaurant_id', 'id'])->on('branches')->restrictOnDelete();
    }

    private function billOwner(Blueprint $table): void
    {
        $this->branchOwner($table);
        $table->foreignId('bill_id');
        $table->foreign(['restaurant_id', 'branch_id', 'bill_id'])->references(['restaurant_id', 'branch_id', 'id'])->on('pos_bills')->restrictOnDelete();
    }

    public function down(): void
    {
        foreach (['pos_audit_events', 'pos_requests', 'pos_payments', 'pos_order_lines', 'pos_orders', 'pos_bills', 'branch_products', 'menu_products', 'menu_categories'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('branches', fn (Blueprint $table) => $table->dropColumn(['pos_billing_mode', 'pos_payment_methods']));
    }
};
