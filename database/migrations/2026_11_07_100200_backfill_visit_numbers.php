<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Visits made before visit numbers existed get theirs: 1, 2, 3… per visitor in date order (MySQL 8 / MariaDB 10.2+). */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            UPDATE visitor_visits v
            JOIN (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY visitor_id ORDER BY entered_at, id) AS n
                FROM visitor_visits
                WHERE deleted_at IS NULL
            ) numbered ON numbered.id = v.id
            SET v.visit_number = numbered.n
            WHERE v.visit_number IS NULL
        ');
    }

    public function down(): void
    {
        //
    }
};
