<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->after('id')->constrained('tenants')->cascadeOnDelete();
            $table->string('first_name', 120)->nullable()->after('tenant_id');
            $table->string('last_name', 120)->nullable()->after('first_name');
            $table->string('role', 32)->default('CLIENT')->after('password');
            $table->index(['tenant_id', 'role']);
        });
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
    }
};
