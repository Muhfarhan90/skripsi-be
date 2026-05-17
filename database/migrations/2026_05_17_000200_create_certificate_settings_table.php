<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_settings', function (Blueprint $table) {
            $table->id();
            $table->string('organization_name')->default('OpenLearning LMS');
            $table->string('certificate_title')->default('Certificate of Completion');
            $table->string('certificate_prefix')->default('CERT');
            $table->string('signatory_name')->nullable();
            $table->string('signatory_title')->nullable();
            $table->string('signature_image')->nullable();
            $table->string('background_image')->nullable();
            $table->text('footer_note')->nullable();
            $table->unsignedInteger('expires_after_months')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_settings');
    }
};
