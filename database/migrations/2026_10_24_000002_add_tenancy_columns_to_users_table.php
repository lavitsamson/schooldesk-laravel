<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Invited users have no password until they accept their invitation.
            $table->string('password')->nullable()->change();
            $table->string('locale', 5)->nullable()->after('password');
            $table->boolean('is_platform_admin')->default(false)->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['locale', 'is_platform_admin']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
