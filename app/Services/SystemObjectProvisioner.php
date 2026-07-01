<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\ObjectType;
use App\Support\TenantContext;

/**
 * Idempotently provisions the built-in system objects (People, Companies, Deals)
 * and their typed attributes for a tenant. Safe to run repeatedly; existing
 * objects/attributes are left untouched.
 *
 * Run for the default tenant from DatabaseSeeder, or for any tenant via
 * `php artisan spidernet:provision-objects {tenant}`.
 */
class SystemObjectProvisioner
{
    /**
     * Object definitions in dependency order (companies before the relationship
     * attributes that target it).
     */
    protected function definitions(): array
    {
        return [
            [
                'slug' => 'companies', 'name' => 'Companies', 'icon' => 'building',
                'attributes' => [
                    ['slug' => 'name', 'name' => 'Name', 'type' => Attribute::TYPE_TEXT, 'is_required' => true, 'is_title' => true, 'is_unique' => true],
                    ['slug' => 'domain', 'name' => 'Domain', 'type' => Attribute::TYPE_URL],
                    ['slug' => 'industry', 'name' => 'Industry', 'type' => Attribute::TYPE_SELECT, 'config' => ['options' => ['SaaS', 'Fintech', 'Healthcare', 'E-commerce', 'Other']]],
                    ['slug' => 'employees', 'name' => 'Employees', 'type' => Attribute::TYPE_NUMBER],
                ],
            ],
            [
                'slug' => 'people', 'name' => 'People', 'icon' => 'user',
                'attributes' => [
                    ['slug' => 'name', 'name' => 'Name', 'type' => Attribute::TYPE_TEXT, 'is_required' => true, 'is_title' => true],
                    ['slug' => 'email', 'name' => 'Email', 'type' => Attribute::TYPE_EMAIL, 'is_unique' => true],
                    ['slug' => 'phone', 'name' => 'Phone', 'type' => Attribute::TYPE_TEXT],
                    ['slug' => 'company', 'name' => 'Company', 'type' => Attribute::TYPE_RELATIONSHIP, 'config' => ['target_object' => 'companies']],
                ],
            ],
            [
                'slug' => 'deals', 'name' => 'Deals', 'icon' => 'target',
                'attributes' => [
                    ['slug' => 'name', 'name' => 'Name', 'type' => Attribute::TYPE_TEXT, 'is_required' => true, 'is_title' => true],
                    ['slug' => 'value', 'name' => 'Value', 'type' => Attribute::TYPE_CURRENCY, 'config' => ['currency' => 'USD']],
                    ['slug' => 'stage', 'name' => 'Stage', 'type' => Attribute::TYPE_SELECT, 'config' => ['options' => ['Lead', 'Qualified', 'Proposal', 'Won', 'Lost']]],
                    ['slug' => 'close_date', 'name' => 'Close Date', 'type' => Attribute::TYPE_DATE],
                    ['slug' => 'company', 'name' => 'Company', 'type' => Attribute::TYPE_RELATIONSHIP, 'config' => ['target_object' => 'companies']],
                ],
            ],
        ];
    }

    public function provision(int $tenantId): void
    {
        TenantContext::withTenant($tenantId, function () use ($tenantId) {
            foreach ($this->definitions() as $definition) {
                $objectType = ObjectType::firstOrCreate(
                    ['tenant_id' => $tenantId, 'slug' => $definition['slug']],
                    ['name' => $definition['name'], 'icon' => $definition['icon'], 'is_system' => true]
                );

                $position = 0;
                foreach ($definition['attributes'] as $attr) {
                    Attribute::firstOrCreate(
                        ['object_type_id' => $objectType->id, 'slug' => $attr['slug']],
                        [
                            'tenant_id' => $tenantId,
                            'name' => $attr['name'],
                            'type' => $attr['type'],
                            'config' => $attr['config'] ?? null,
                            'is_required' => $attr['is_required'] ?? false,
                            'is_unique' => $attr['is_unique'] ?? false,
                            'is_title' => $attr['is_title'] ?? false,
                            'position' => $position,
                        ]
                    );
                    $position++;
                }
            }
        });
    }
}
