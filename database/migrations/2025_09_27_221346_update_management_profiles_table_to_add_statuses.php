<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('management_profiles',function(Blueprint $table){
            /**
             * Added lateral status,hostel status to the profile status
             */
            $table->boolean('lateral_status')->default(false)->after('gender');
            $table->boolean('hostel_status')->default(false)->after('lateral_status');
            $table->boolean('college_hostel_status')->default(false)->after('hostel_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('management_profiles',function(Blueprint $table){

            // checking columns whether they exist or not
            if(Schema::hasColumns('management_profiles',['lateral_status','hostel_status','college_hostel_status'])){
                $table->dropColumn('lateral_status');
                $table->dropColumn('hostel_status');
                $table->dropColumn('college_hostel_status');
            }
        });
    }
};
