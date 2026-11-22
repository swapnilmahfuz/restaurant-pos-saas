<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('approval_status', 16)->default('approved');
            $table->string('tenant_type', 16)->default('production');
            $table->timestamp('access_expires_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->index(['tenant_type', 'approval_status']);
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->string('approval_status', 16)->default('approved');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->string('role', 24)->default('cashier');
        });

        Schema::create('erp_modules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('tier', 16);
            $table->boolean('is_enabled')->default(true);
        });

        Schema::create('restaurant_entitlements', function (Blueprint $table) {
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->foreignId('erp_module_id')->constrained()->restrictOnDelete();
            $table->boolean('is_enabled');
            $table->primary(['restaurant_id', 'erp_module_id']);
        });

        Schema::create('branch_entitlements', function (Blueprint $table) {
            $table->foreignId('restaurant_id');
            $table->foreignId('branch_id');
            $table->foreignId('erp_module_id')->constrained()->restrictOnDelete();
            $table->boolean('is_enabled');
            $table->primary(['restaurant_id', 'branch_id', 'erp_module_id']);
            $table->foreign(['restaurant_id', 'branch_id'])
                ->references(['restaurant_id', 'id'])->on('branches')->restrictOnDelete();
        });

        Schema::create('commercial_audit_events', function (Blueprint $table) {
            $table->id();
            $table->string('event', 48);
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->unsignedBigInteger('restaurant_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->json('details');
            $table->timestamp('created_at');
            $table->index(['restaurant_id', 'created_at']);
        });

        foreach ([
            ['pos', 'POS and billing', 'basic'],
            ['tables', 'Tables and guest QR', 'basic'],
            ['kitchen', 'Kitchen display', 'basic'],
            ['receipts', 'Digital receipts', 'advanced'],
            ['reports', 'Sales and financial reporting', 'basic'],
            ['crm', 'Customer management', 'advanced'],
            ['reservations', 'Reservations', 'advanced'],
            ['inventory', 'Recipes and inventory', 'advanced'],
            ['procurement', 'Procurement and forecasting', 'advanced'],
            ['payroll', 'HR and payroll', 'advanced'],
            ['operations', 'Advanced operations', 'advanced'],
        ] as [$code, $name, $tier]) {
            DB::table('erp_modules')->insert(compact('code', 'name', 'tier'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_audit_events');
        Schema::dropIfExists('branch_entitlements');
        Schema::dropIfExists('restaurant_entitlements');
        Schema::dropIfExists('erp_modules');

        Schema::table('staff', fn (Blueprint $table) => $table->dropColumn('role'));
        Schema::table('branches', fn (Blueprint $table) => $table->dropColumn('approval_status'));
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropIndex(['tenant_type', 'approval_status']);
            $table->dropColumn(['approval_status', 'tenant_type', 'access_expires_at', 'retired_at']);
        });
    }
};
