<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slots', function (Blueprint $table) {
            $table->id();
            $table->timestampTz('starts_at')->unique();
            $table->timestampTz('ends_at');
        });
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_id')->constrained()->restrictOnDelete();
            $table->string('customer_name', 150);
            $table->string('customer_email', 254);
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampsTz();
        });
        DB::statement('CREATE UNIQUE INDEX bookings_active_slot_unique ON bookings (slot_id) WHERE cancelled_at IS NULL');
        DB::statement('ALTER TABLE slots ADD CONSTRAINT slots_positive_duration CHECK (ends_at > starts_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('slots');
    }
};
