<?php

namespace App\Models;

use App\Models\Concerns\OwnedOrGlobal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Materiaal (stamgegevens): user_id = null is de standaardlijst, anders eigen materiaal.
 * Standaardmateriaal heeft een vaste slug (zie de constanten); de schijvencalculator gebruikt die.
 */
#[Fillable(['user_id', 'name', 'slug', 'image_path', 'sort_order'])]
class Equipment extends Model
{
    use OwnedOrGlobal;

    public const NONE = 'none';

    public const MACHINE = 'machine';

    public const PLATE = 'plate';

    public const BARBELL = 'barbell';

    public const CARDIO_MACHINE = 'cardio_machine';

    protected $table = 'equipment';

    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class);
    }

    /** Geüpload plaatje, anders storage/app/public/equipment/{slug}.png (of .jpg) voor de standaardlijst */
    public function imageUrl(): ?string
    {
        if ($this->image_path) {
            return Storage::disk('public')->url($this->image_path);
        }

        if (! $this->isStandard()) {
            return null;
        }

        $path = collect(["equipment/{$this->slug}.png", "equipment/{$this->slug}.jpg"])->first(fn ($path) => Storage::disk('public')->exists($path));

        return $path ? Storage::disk('public')->url($path) : null;
    }

    /** Standaardmateriaal in de taal van de gebruiker; eigen materiaal zoals je het noemde */
    public function label(): string
    {
        return $this->isStandard() ? __($this->name) : $this->name;
    }

    /** Is dit dit standaardmateriaal? Bijv. isStandardKind(Equipment::BARBELL) */
    public function isStandardKind(string $slug): bool
    {
        return $this->isStandard() && $this->slug === $slug;
    }

    /** id van standaardmateriaal, bijv. Equipment::standardId(Equipment::MACHINE) */
    public static function standardId(string $slug): ?int
    {
        return static::whereNull('user_id')->where('slug', $slug)->value('id');
    }
}
