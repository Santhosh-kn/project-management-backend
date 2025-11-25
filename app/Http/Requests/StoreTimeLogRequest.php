<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTimeLogRequest extends FormRequest
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
            'task_id' => ['required', 'exists:tasks,id'],
            'hours' => ['required', 'numeric', 'min:0.01', 'max:24'],
            'description' => ['nullable', 'string', 'max:1000'],
            'log_date' => ['required', 'date'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'task_id.required' => 'Task is required',
            'task_id.exists' => 'Invalid task selected',
            'hours.required' => 'Hours are required',
            'hours.numeric' => 'Hours must be a number',
            'hours.min' => 'Hours must be at least 0.01',
            'hours.max' => 'Hours cannot exceed 24 per entry',
            'log_date.required' => 'Log date is required',
            'log_date.date' => 'Log date must be a valid date',
        ];
    }
}
