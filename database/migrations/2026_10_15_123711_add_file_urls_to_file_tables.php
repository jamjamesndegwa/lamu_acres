<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->columns() as $name => $columns) {
            Schema::table($name, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    $table->text($column)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->columns() as $name => $columns) {
            Schema::table($name, function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    /** @return array<string, list<string>> */
    private function columns(): array
    {
        return [
            'users' => ['avatar_url'],
            'property_media' => ['url', 'public_url'],
            'ownership_documents' => ['url'],
            'evidence_files' => ['url'],
            'intelligence_files' => ['url'],
            'broker_introductions' => ['consent_url', 'evidence_url'],
        ];
    }
};
