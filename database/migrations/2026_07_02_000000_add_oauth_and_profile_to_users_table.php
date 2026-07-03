<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('avatar_url')->nullable()->after('google_id');
            $table->timestamp('profile_completed_at')->nullable()->after('email_verified_at');
            $table->string('faculty')->nullable()->after('account_id');
            $table->string('department')->nullable()->after('faculty');
            $table->string('student_id')->nullable()->unique()->after('department');
            $table->string('employee_id')->nullable()->unique()->after('student_id');
            $table->string('research_affiliation')->nullable()->after('employee_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'google_id',
                'avatar_url',
                'profile_completed_at',
                'faculty',
                'department',
                'student_id',
                'employee_id',
                'research_affiliation',
            ]);
        });
    }
};
