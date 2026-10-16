<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('workplaces', function (Blueprint $t) {
        $t->id(); $t->string('name',150); $t->string('sgk_sicil_no',26)->nullable()->unique(); $t->string('address',500)->nullable();
        $t->boolean('is_active')->default(true)->index(); $t->timestamps();
    }); }
    public function down(): void { Schema::dropIfExists('workplaces'); }
};
