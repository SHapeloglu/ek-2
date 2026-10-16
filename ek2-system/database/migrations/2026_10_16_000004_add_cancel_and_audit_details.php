<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        // change() mevcut status indeksini korur; ->index() eklenirse indeks yeniden yaratılmaya çalışılır ve hata verir
        Schema::table('ek2_records', function (Blueprint $t) {
            $t->enum('status',['draft','completed','cancelled'])->default('draft')->change();
            if (!Schema::hasColumn('ek2_records','cancelled_at')) $t->timestamp('cancelled_at')->nullable()->after('completed_at');
            if (!Schema::hasColumn('ek2_records','cancel_reason')) $t->string('cancel_reason',500)->nullable()->after('cancelled_at');
        });
        if (!Schema::hasColumn('audit_logs','details')) Schema::table('audit_logs', function (Blueprint $t) { $t->json('details')->nullable()->after('entity_id'); });
    }
    public function down(): void {
        Schema::table('audit_logs', function (Blueprint $t) { $t->dropColumn('details'); });
        Schema::table('ek2_records', function (Blueprint $t) {
            $t->dropColumn(['cancelled_at','cancel_reason']); $t->enum('status',['draft','completed'])->default('draft')->change();
        });
    }
};
