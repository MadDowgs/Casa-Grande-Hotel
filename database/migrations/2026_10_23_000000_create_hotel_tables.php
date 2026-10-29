<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $t) {
            $t->string('id', 10)->primary();
            $t->string('name', 60);
            $t->string('type', 30);
            $t->unsignedInteger('price_per_night');
            $t->unsignedTinyInteger('capacity');
            $t->boolean('available')->default(true);
            $t->timestamps();
        });
        Schema::create('reservations', function (Blueprint $t) {
            $t->id();
            $t->string('room_id', 10);
            $t->foreign('room_id')->references('id')->on('rooms');
            $t->string('guest_name', 100);
            $t->string('guest_email', 120);
            $t->date('check_in');
            $t->date('check_out');
            $t->unsignedTinyInteger('guests');
            $t->unsignedSmallInteger('nights');
            $t->decimal('total_amount', 10, 2);
            $t->string('currency', 3)->default('PHP');
            $t->string('status', 12)->default('PENDING');
            $t->timestamps();
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $t->decimal('amount', 10, 2);
            $t->string('method', 10);
            $t->string('status', 10);
            $t->string('transaction_id', 30)->nullable();
            $t->string('details', 100)->nullable();   // e.g. "BDO Unibank Visa **** 4242" (never the full card number)
            $t->timestamps();
        });
        Schema::create('api_logs', function (Blueprint $t) {
            $t->id();
            $t->string('method', 10);
            $t->text('url');
            $t->json('headers')->nullable();
            $t->json('request')->nullable();
            $t->unsignedSmallInteger('status')->default(0);
            $t->json('response')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_logs');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('rooms');
    }
};
