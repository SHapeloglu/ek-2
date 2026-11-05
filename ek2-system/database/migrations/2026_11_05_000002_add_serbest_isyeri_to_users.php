<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('serbest_isyeri')->default(false);
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $t) { $t->dropColumn('serbest_isyeri'); });
    }
};
