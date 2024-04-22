<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class AddTagsWorkflowsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workflows', static function (Blueprint $blueprint) {
            $blueprint->json('tags')
                ->nullable()
                ->after('output');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflows', static function (Blueprint $blueprint) {
            $blueprint->dropColumn('tags');
        });
    }
}
