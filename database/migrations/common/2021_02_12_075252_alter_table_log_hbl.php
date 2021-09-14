<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableLogHbl extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         Schema::connection('common')->table('payment_logs', function (Blueprint $table) {
             $table->text('order_request')->nullable();
             $table->text('hbl_response')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('common')->table('payment_logs', function (Blueprint $table) {
            $table->dropColumn(['order_request', 'hbl_response']);
        });
    }
}
