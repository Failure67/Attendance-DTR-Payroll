<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'wage_type' => ['required', 'in:Hourly,Daily,Weekly,Monthly,Piece rate'],
            'min_wage' => ['required', 'numeric', 'min:0'],
            'units_worked' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:Pending,Completed,Cancelled'],
            'deductions' => ['nullable', 'array'],
            'deductions.*.name' => ['required_with:deductions', 'string', 'max:30'],
            'deductions.*.amount' => ['required_with:deductions', 'numeric', 'min:0'],
        ];
    }
}
