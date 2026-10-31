<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminsRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'job_title' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email,'.$this->id,
            'phone' => 'required|unique:admins,phone,'.$this->id,
            'password' => ['nullable','min:8',Rule::requiredIf($this->routeIs('admin.admins.store'))],
            'is_active' => 'nullable|in:active,inactive,suspended',
            'image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg'],
            'role_id' => 'required|exists:roles,id',
        ];
    }
}
