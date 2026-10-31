<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'symbol' => 'required|string|max:20|unique:stocks,symbol,'.$this->id,
            'isin' => 'nullable|string|max:20|unique:stocks,isin,'.$this->id,
            'name' => 'required|string|max:255',
            'arabic_name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'market' => 'nullable|string|max:30',
            'exchange' => 'nullable|string|max:30',
            'asset_class' => 'nullable|string|max:30',
            'industry' => 'nullable|string|max:255',
            'reuters_symbol' => 'nullable|string|max:255',
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg'],
            'is_tradable' => 'nullable|boolean',
            'is_visible' => 'nullable|boolean',
            'is_otc' => 'nullable|boolean',
            'is_right' => 'nullable|boolean',
            'is_ipo' => 'nullable|boolean',
            'is_same_day' => 'nullable|boolean',
            'is_sharia_compliant' => 'nullable|boolean',
            'is_egx30' => 'nullable|boolean',
            'is_egx70' => 'nullable|boolean',
            'is_egx100' => 'nullable|boolean',
        ];
    }
}
