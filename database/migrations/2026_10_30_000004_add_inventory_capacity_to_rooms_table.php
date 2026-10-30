<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->unsignedSmallInteger('inventory_capacity')->default(1)->after('capacity');
        });

        DB::table('rooms')->where('id', 'R101')->update(['inventory_capacity' => 15]);
        DB::table('rooms')->where('id', 'R202')->update(['inventory_capacity' => 10]);
        DB::table('rooms')->where('id', 'R303')->update(['inventory_capacity' => 15]);
        DB::table('rooms')->where('id', 'R404')->update(['inventory_capacity' => 6]);
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('inventory_capacity');
        });
    }
};
