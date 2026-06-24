<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageDealer() ?? false;
    }

    public function rules(): array
    {
        $dealerId = (int) ($this->user()?->dealer_id ?? 0);

        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'landmark' => ['nullable', 'string', 'max:500'],
            'region' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'inn' => ['nullable', 'string', 'regex:/^\d{9}$/'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'map_provider' => ['nullable', 'string', 'in:yandex,google,osm'],
            'photo' => ['nullable', 'image', 'max:4096'],
            'photo_source_path' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'deliveryman_id' => ['nullable', 'integer'],
            'parent_shop_id' => [
                'nullable',
                'integer',
                Rule::exists('shops', 'id')
                    ->where('dealer_id', $dealerId)
                    ->whereNull('parent_shop_id'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'parent_shop_id' => 'bosh filial',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'latitude.required' => 'Mijoz joylashuvini xaritadan belgilang.',
            'longitude.required' => 'Mijoz joylashuvini xaritadan belgilang.',
        ];
    }
}
