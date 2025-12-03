<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class RunProcessPayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'rows' => ['required', 'array'],
            'rows.*.user_id' => ['required', 'exists:users,id'],
            'rows.*.wage_type' => ['required', 'in:Hourly,Daily,Weekly,Monthly,Piece rate'],
            'rows.*.min_wage' => ['required', 'numeric', 'min:0'],
            'rows.*.regular_hours' => ['required', 'numeric', 'min:0'],
            'rows.*.overtime_hours' => ['required', 'numeric', 'min:0'],
            'rows.*.absent_days' => ['required', 'numeric', 'min:0'],
            'rows.*.present_days' => ['required', 'numeric', 'min:0'],
            'rows.*.include' => ['nullable', 'boolean'],
            'rows.*.ca_deduction' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
