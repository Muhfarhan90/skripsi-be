<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->string('status')->default('active')->after('certificate_url');
            $table->string('template_version')->nullable()->after('status');
            $table->string('verification_code')->nullable()->unique()->after('template_version');
            $table->json('snapshot_data')->nullable()->after('verification_code');
            $table->timestamp('revoked_at')->nullable()->after('expired_at');
            $table->text('revoked_reason')->nullable()->after('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropUnique(['verification_code']);
            $table->dropColumn([
                'status',
                'template_version',
                'verification_code',
                'snapshot_data',
                'revoked_at',
                'revoked_reason',
            ]);
        });
    }
};
