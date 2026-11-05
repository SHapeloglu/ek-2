<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('assignments', function (Blueprint $t) {
            $t->id();
            $t->uuid('seri_id')->nullable()->index();
            $t->foreignId('doctor_id')->constrained('users');
            $t->foreignId('workplace_id')->constrained('workplaces');
            $t->dateTime('starts_at');
            $t->dateTime('ends_at');
            $t->string('note', 300)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['doctor_id', 'starts_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('assignments'); }
};
