<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
            $table->string('first_name', 120)->nullable()->after('tenant_id');
            $table->string('last_name', 120)->nullable()->after('first_name');
            $table->string('role', 32)->default('CLIENT')->after('password');
            $table->index(['tenant_id', 'role']);
        });

        DB::table('tenants')->whereNull('tenant_id')->update([
            'tenant_id' => DB::raw('id'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'role']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn(['first_name', 'last_name', 'role']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
    }
};
