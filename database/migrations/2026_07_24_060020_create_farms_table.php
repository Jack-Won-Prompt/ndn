<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 농가 (CLAUDE.md §5).
 *
 * owner_user_id 는 farm_owner 역할 사용자와 연결된다.
 * 주의(§7-2): 농가 주소는 두되 근로자 위치정보 컬럼(lat/lng)은 두지 않는다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('main_crop', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farms');
    }
};
