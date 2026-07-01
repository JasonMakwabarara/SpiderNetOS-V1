<?php

namespace App\Support;

use App\Models\Attribute;
use App\Models\ObjectType;
use App\Models\Record;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Validates and normalises a record's `data` payload against the typed attribute
 * definitions of its {@see ObjectType}. This is what makes the flexible data
 * model strongly typed (Attio-style) rather than a free-form JSON blob.
 */
class RecordDataValidator
{
    /**
     * @param  array  $input  attribute slug => value
     * @param  bool  $partial  true for PATCH/PUT updates (only validate provided keys)
     * @return array  the cleaned, type-cast data keyed by attribute slug
     *
     * @throws ValidationException
     */
    public function validate(ObjectType $objectType, array $input, bool $partial = false): array
    {
        $attributes = $objectType->attributes()->get();
        $rules = [];
        $clean = [];

        foreach ($attributes as $attribute) {
            // AI-computed attributes are never written by the client.
            if ($attribute->isComputed()) {
                continue;
            }

            $key = "data.{$attribute->slug}";
            $present = array_key_exists($attribute->slug, $input);

            if (! $present) {
                if ($attribute->is_required && ! $partial) {
                    $rules[$key] = ['required'];
                }

                continue;
            }

            $rules[$key] = $this->rulesFor($attribute);
        }

        $validator = Validator::make(['data' => $input], $rules);
        $validator->validate();

        // Keep only known, non-computed attribute keys and cast to canonical types.
        $bySlug = $attributes->keyBy('slug');
        foreach ($input as $slug => $value) {
            /** @var Attribute|null $attribute */
            $attribute = $bySlug->get($slug);
            if (! $attribute || $attribute->isComputed()) {
                continue;
            }
            $clean[$slug] = $this->cast($attribute, $value);
        }

        $this->assertUnique($objectType, $bySlug, $clean, $partial);

        return $clean;
    }

    /** @return array<int, mixed> */
    protected function rulesFor(Attribute $attribute): array
    {
        $base = $attribute->is_required ? ['required'] : ['nullable'];

        return match ($attribute->type) {
            Attribute::TYPE_NUMBER, Attribute::TYPE_CURRENCY => array_merge($base, ['numeric']),
            Attribute::TYPE_DATE, Attribute::TYPE_DATETIME => array_merge($base, ['date']),
            Attribute::TYPE_CHECKBOX => array_merge($base, ['boolean']),
            Attribute::TYPE_EMAIL => array_merge($base, ['email']),
            Attribute::TYPE_URL => array_merge($base, ['url']),
            Attribute::TYPE_SELECT => array_merge($base, [function ($attr, $value, $fail) use ($attribute) {
                if ($value !== null && ! in_array($value, $attribute->options(), true)) {
                    $fail("The selected {$attribute->name} is invalid.");
                }
            }]),
            Attribute::TYPE_MULTISELECT => array_merge($base, ['array', function ($attr, $value, $fail) use ($attribute) {
                $options = $attribute->options();
                foreach ((array) $value as $item) {
                    if (! in_array($item, $options, true)) {
                        $fail("The {$attribute->name} contains an invalid option.");

                        return;
                    }
                }
            }]),
            Attribute::TYPE_RELATIONSHIP => $attribute->isMultiRelationship()
                ? array_merge($base, ['array'])
                : array_merge($base, ['integer']),
            default => array_merge($base, ['string']),
        };
    }

    protected function cast(Attribute $attribute, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($attribute->type) {
            Attribute::TYPE_NUMBER, Attribute::TYPE_CURRENCY => $value + 0,
            Attribute::TYPE_CHECKBOX => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            Attribute::TYPE_MULTISELECT => array_values((array) $value),
            Attribute::TYPE_RELATIONSHIP => $attribute->isMultiRelationship()
                ? array_values(array_map('intval', (array) $value))
                : (int) $value,
            default => $value,
        };
    }

    /**
     * Enforce attribute-level uniqueness within the tenant + object using a
     * JSON path comparison (works on sqlite/pgsql/mysql).
     *
     * @param  Collection<string, Attribute>  $bySlug
     */
    protected function assertUnique(ObjectType $objectType, Collection $bySlug, array $clean, bool $partial): void
    {
        foreach ($clean as $slug => $value) {
            /** @var Attribute|null $attribute */
            $attribute = $bySlug->get($slug);
            if (! $attribute || ! $attribute->is_unique || $value === null || $value === '') {
                continue;
            }

            $exists = Record::query()
                ->where('object_type_id', $objectType->id)
                ->where("data->{$slug}", $value)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    "data.{$slug}" => "A record with this {$attribute->name} already exists.",
                ]);
            }
        }
    }
}
