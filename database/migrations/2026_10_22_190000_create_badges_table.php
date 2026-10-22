<?php

use App\Models\Exercise;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Badges met niveaus (drempels), te beheren door een admin. Gevuld met de standaardbadges.
     */
    public function up(): void
    {
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('icon', 30);
            $table->string('metric', 30);                     // App\Enums\BadgeMetric
            $table->foreignIdFor(Exercise::class)->nullable()->constrained()->nullOnDelete(); // alleen bij oefening-badges
            $table->json('thresholds');                        // oplopend, bv. [1, 10, 25]
            $table->string('label', 80);                       // naam van een niveau, {n} = het getal
            $table->string('first_label', 80)->nullable();     // andere naam voor het eerste niveau, bv. "Eerste training"
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $badges = [
            ['Trainingen', 'dumbbell', 'sessions', [1, 10, 25, 50, 100, 250, 500], '{n} trainingen', 'Eerste training'],
            ['Trainingstijd', 'clock', 'hours', [10, 25, 50, 100, 250, 500], '{n} uur getraind', null],
            ['Volume', 'bolt', 'volume', [10000, 50000, 100000, 250000, 500000, 1000000, 2500000], '{n} kg getild', null],
            ['Cardio-afstand', 'run', 'distance', [10, 50, 100, 250, 500, 1000], '{n} km cardio', null],
            ['Records', 'trophy', 'records', [1, 10, 25, 50, 100, 250], '{n} records', 'Eerste record'],
            ['Weekreeks', 'calendar', 'streak', [2, 4, 8, 12, 26, 52], '{n} weken op rij', null],
            ['Volle week', 'check', 'full_week', [3, 4, 5, 6], '{n} trainingen in één week', null],
            ['Zwaargewicht', 'dumbbell', 'session_volume', [5000, 10000, 15000, 20000], '{n} kg in één training', null],
            ['Duurloper', 'run', 'session_distance', [3, 5, 10, 21.1], '{n} km in één training', null],
            ['Lange adem', 'clock', 'long_sessions', [1, 10, 25, 50], '{n} trainingen van 1,5 uur', 'Training van 1,5 uur'],
            ['Vroege vogel', 'sun', 'early', [1, 10, 25], '{n} keer vóór 7:00', 'Training vóór 7:00'],
            ['Nachtuil', 'moon', 'late', [1, 10, 25], '{n} keer na 21:00', 'Training na 21:00'],
            ['Weekendstrijder', 'calendar', 'weekend', [5, 25, 50, 100], '{n} weekendtrainingen', null],
        ];

        DB::table('badges')->insert(array_map(fn (array $badge, int $i) => [
            'name' => $badge[0], 'icon' => $badge[1], 'metric' => $badge[2], 'thresholds' => json_encode($badge[3]),
            'label' => $badge[4], 'first_label' => $badge[5], 'sort_order' => $i + 1, 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ], $badges, array_keys($badges)));
    }

    public function down(): void
    {
        Schema::dropIfExists('badges');
    }
};
