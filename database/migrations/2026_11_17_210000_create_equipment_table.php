<?php

use App\Models\Equipment;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Materiaal als stamgegevens (net als spiergroepen): user_id = null is de standaardlijst,
 * gebruikers maken er eigen materiaal bij. De standaardlijst is wat eerst de vaste lijst
 * (App\Enums\Equipment) was, met dezelfde slugs; exercises.equipment wordt exercises.equipment_id.
 */
return new class extends Migration
{
    /** slug => naam (Engels, wordt vertaald met __()) */
    private const STANDARD = [
        'none' => 'None (bodyweight)',
        'machine' => 'Machine',
        'cable' => 'Cable',
        'plate' => 'Plate-loaded',
        'barbell' => 'Barbell',
        'dumbbell' => 'Dumbbell',
        'kettlebell' => 'Kettlebell',
        'band' => 'Resistance band',
        'suspension' => 'Suspension (TRX)',
        'cardio_machine' => 'Cardio machine',
        'other' => 'Other equipment',
    ];

    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('slug', 80);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
        });

        $now = now();
        DB::table('equipment')->insert(collect(self::STANDARD)->keys()->map(fn (string $slug, int $i) => [
            'name' => self::STANDARD[$slug], 'slug' => $slug, 'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now,
        ])->all());

        Schema::table('exercises', function (Blueprint $table) {
            $table->foreignIdFor(Equipment::class)->nullable()->after('type')->constrained('equipment')->nullOnDelete();
        });

        foreach (DB::table('equipment')->pluck('id', 'slug') as $slug => $id) {
            DB::table('exercises')->where('equipment', $slug)->update(['equipment_id' => $id]);
        }

        Schema::table('exercises', function (Blueprint $table) {
            $table->dropColumn('equipment');
        });
    }

    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->string('equipment', 30)->nullable()->after('type');
        });

        // Eigen materiaal bestond toen niet: die oefeningen krijgen 'other'
        foreach (DB::table('equipment')->get(['id', 'slug', 'user_id']) as $equipment) {
            DB::table('exercises')->where('equipment_id', $equipment->id)
                ->update(['equipment' => $equipment->user_id === null && isset(self::STANDARD[$equipment->slug]) ? $equipment->slug : 'other']);
        }

        Schema::table('exercises', function (Blueprint $table) {
            $table->dropConstrainedForeignIdFor(Equipment::class);
        });

        Schema::dropIfExists('equipment');
    }
};
