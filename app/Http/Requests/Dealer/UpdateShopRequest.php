<?php

declare(strict_types=1);

namespace App\Http\Requests\Dealer;

use App\Models\Shop;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageDealer() ?? false;
    }

    public function rules(): array
    {
        $dealerId = (int) ($this->user()?->dealer_id ?? 0);
        $currentShopId = $this->route('shop')?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:32'],
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
            'remove_photo' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'parent_shop_id' => [
                'nullable',
                'integer',
                $currentShopId !== null ? "not_in:{$currentShopId}" : 'integer',
                Rule::exists('shops', 'id')
                    ->where('dealer_id', $dealerId)
                    ->whereNull('parent_shop_id'),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $shop = $this->route('shop');

            if (! $shop instanceof Shop) {
                return;
            }

            if (! $this->has('parent_shop_id')) {
                return;
            }

            $newParent = $this->input('parent_shop_id');

            if ($newParent !== null && $shop->branches()->exists()) {
                $v->errors()->add(
                    'parent_shop_id',
                    "Bu mijoz o'z filiallariga ega — uni boshqa mijozga filial sifatida biriktira olmaysiz."
                );
            }
        });
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
            'parent_shop_id.not_in' => "Mijoz o'ziga filial bo'la olmaydi.",
        ];
    }
}
