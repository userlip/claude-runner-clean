<?php

use App\Enums\SiteStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->string('path')->nullable();
            $table->string('ploi_site_id')->nullable();
            $table->string('php_version')->default('8.4');
            $table->string('web_directory')->default('/public');
            $table->boolean('isolated_user')->default(false);
            $table->string('database_name')->nullable();
            $table->text('deploy_script')->nullable();
            $table->string('status')->default(SiteStatus::Pending->value);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
