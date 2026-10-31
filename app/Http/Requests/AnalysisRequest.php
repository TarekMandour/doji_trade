<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalysisRequest extends FormRequest
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
            'title' => 'nullable|string|max:255',
            'watchlist_id' => 'required|exists:watchlists,id',
            'resolution' => 'required|string|in:'.implode(',', array_keys(config('thndr.resolutions', []))),
            'candles_count' => 'required|integer|min:10|max:2000',
        ];
    }
}
