<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'assigned_to' => ['sometimes', 'nullable', 'exists:users,id'],
            'status' => ['sometimes', 'required', 'in:todo,in_progress,review,done'],
            'priority' => ['sometimes', 'required', 'in:low,medium,high,critical'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'estimated_hours' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'actual_hours' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['exists:tags,id'],
        ];
    }
}
