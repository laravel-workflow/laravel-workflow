<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkflowSignalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('workflow_signals', function (Blueprint $table) {
            $table->id('id');
            $table->foreignId('stored_workflow_id')->index();
            $table->text('method');
            $table->text('arguments')->nullable();
            $table->timestamps();

            $table->index(['stored_workflow_id', 'created_at']);

            $table->foreign('stored_workflow_id')->references('id')->on('workflows')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('workflow_signals');
    }
}
