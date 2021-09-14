<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateUser extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('Areferral_userid')->after('referal_count')->nullable();
            $table->integer('Breferral_userid')->after('Areferral_userid')->nullable();            
            $table->integer('Creferral_userid')->after('Breferral_userid')->nullable();
            $table->integer('referral_status')->after('Creferral_userid')->default(0)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('Areferral_userid');
            $table->dropColumn('Breferral_userid');
            $table->dropColumn('Creferral_userid');
            $table->dropColumn('referral_status');
        });
    }
}
