<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('users', function (Blueprint $t) {
        $t->id(); $t->string('name',100); $t->string('surname',100); $t->string('email')->unique();
        $t->string('password'); $t->enum('role',['admin','doctor'])->index(); $t->boolean('is_active')->default(true)->index();
        $t->timestamp('email_verified_at')->nullable(); $t->rememberToken(); $t->timestamps();
    }); }
    public function down(): void { Schema::dropIfExists('users'); }
};
