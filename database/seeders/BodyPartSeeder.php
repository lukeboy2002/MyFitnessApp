<?php

namespace Database\Seeders;

use App\Models\BodyPart;
use App\Models\Muscle;
use App\Models\MuscleGroup;
use Illuminate\Database\Seeder;

/**
 * Standaardlijst: lichaamsdelen → spiergroepen → spieren (user_id = null).
 * Veilig om vaker te draaien: bestaande items worden bijgewerkt, niets wordt dubbel.
 *
 *   php artisan db:seed --class=BodyPartSeeder
 */
class BodyPartSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->data() as $bpOrder => [$bodyPartName, $groups, $bodyPartIcon]) {
            $bodyPart = BodyPart::updateOrCreate(
                ['user_id' => null, 'slug' => str($bodyPartName)->slug()],
                ['name' => $bodyPartName, 'icon' => $bodyPartIcon, 'sort_order' => $bpOrder],
            );

            foreach ($groups as $mgOrder => [$groupName, $color, $icon, $muscles]) {
                $group = MuscleGroup::updateOrCreate(
                    ['user_id' => null, 'slug' => str($groupName)->slug()],
                    ['body_part_id' => $bodyPart->id, 'name' => $groupName, 'color' => $color, 'icon' => $icon, 'sort_order' => $mgOrder],
                );

                foreach ($muscles as $mOrder => [$muscleName, $latin]) {
                    Muscle::updateOrCreate(
                        ['user_id' => null, 'slug' => str($muscleName)->slug()],
                        ['muscle_group_id' => $group->id, 'name' => $muscleName, 'latin_name' => $latin, 'sort_order' => $mOrder],
                    );
                }
            }
        }
    }

    /**
     * [lichaamsdeel, [[spiergroep, kleur, icoon, [[spier, latijnse naam], ...]], ...], icoon]
     * De spieren hebben al een Latijnse naam, daarom is latin_name leeg.
     */
    private function data(): array
    {
        return [
            ['Neck', [
                ['Neck', '#64748b', 'fiber', [
                    ['Sternocleidomastoideus', null],
                    ['Levator scapulae', null],
                ]],
            ], 'fiber'],
            ['Shoulders', [
                ['Front Delts', '#8b5cf6', 'arm', [
                    ['Deltoideus (anterior)', null],
                ]],
                ['Side Delts', '#8b5cf6', 'arm', [
                    ['Deltoideus (lateral)', null],
                ]],
                ['Rear Delts', '#8b5cf6', 'arm', [
                    ['Deltoideus (posterior)', null],
                ]],
                ['Rotator Cuff', '#8b5cf6', 'arm', [
                    ['Supraspinatus', null],
                    ['Infraspinatus', null],
                    ['Teres minor', null],
                    ['Subscapularis', null],
                ]],
            ], 'arm'],
            ['Chest', [
                ['Chest', '#e5650f', 'dumbbell', [
                    ['Pectoralis major', null],
                    ['Pectoralis minor', null],
                ]],
            ], 'dumbbell'],
            ['Back', [
                ['Upper Back', '#1d63c5', 'body', [
                    ['Teres major', null],
                ]],
                ['Traps', '#1d63c5', 'body', [
                    ['Trapezius', null],
                ]],
                ['Rhomboids', '#1d63c5', 'body', [
                    ['Rhomboideus', null],
                ]],
                ['Lats', '#1d63c5', 'body', [
                    ['Latissimus dorsi', null],
                ]],
                ['Lower Back', '#1d63c5', 'body', [
                    ['Erector spinae', null],
                    ['Multifidus', null],
                ]],
            ], 'body'],
            ['Arms', [
                ['Biceps', '#0e9f8a', 'arm', [
                    ['Biceps brachii', null],
                    ['Brachialis', null],
                ]],
                ['Triceps', '#0e9f8a', 'arm', [
                    ['Triceps brachii', null],
                ]],
                ['Forearm Flexors', '#0e9f8a', 'arm', [
                    ['Brachioradialis', null],
                ]],
                ['Forearm Extensors', '#0e9f8a', 'arm', [
                ]],
            ], 'arm'],
            ['Core', [
                ['Abs', '#db3f6b', 'fiber', [
                    ['Rectus abdominis', null],
                    ['Transversus abdominis', null],
                ]],
                ['Obliques', '#db3f6b', 'fiber', [
                    ['Obliquus externus', null],
                    ['Obliquus internus', null],
                ]],
                ['Serratus Anterior', '#db3f6b', 'fiber', [
                    ['Serratus anterior', null],
                ]],
            ], 'fiber'],
            ['Glutes', [
                ['Glutes', '#65a30d', 'body', [
                    ['Gluteus maximus', null],
                ]],
                ['Abductors', '#65a30d', 'body', [
                    ['Gluteus medius', null],
                    ['Gluteus minimus', null],
                    ['Tensor fasciae latae', null],
                ]],
            ], 'body'],
            ['Legs', [
                ['Hip Flexors', '#d4a106', 'run', [
                    ['Iliopsoas', null],
                ]],
                ['Quadriceps', '#d4a106', 'run', [
                    ['Rectus femoris', null],
                    ['Vastus lateralis', null],
                    ['Vastus medialis', null],
                    ['Vastus intermedius', null],
                ]],
                ['Hamstrings', '#d4a106', 'run', [
                    ['Biceps femoris', null],
                    ['Semitendinosus', null],
                    ['Semimembranosus', null],
                ]],
                ['Adductors', '#d4a106', 'run', [
                    ['Adductor longus', null],
                    ['Adductor brevis', null],
                    ['Adductor magnus', null],
                    ['Gracilis', null],
                    ['Pectineus', null],
                ]],
                ['Calves', '#d4a106', 'run', [
                    ['Gastrocnemius', null],
                    ['Soleus', null],
                ]],
                ['Tibialis Anterior', '#d4a106', 'run', [
                    ['Tibialis anterior', null],
                ]],
            ], 'run'],
            ['Full Body', [
                ['Full Body', '#0e8ad6', 'bolt', []],
                ['Cardio', '#f97316', 'heart', []],
            ], 'bolt'],
        ];
    }
}
