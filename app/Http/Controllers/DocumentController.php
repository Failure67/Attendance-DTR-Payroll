<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class DocumentController extends Controller
{

    public function generateDocument()
    {
        $employees = [];

        $data = [
            'title' => 'Payroll Report',
            'date' => date('F j, Y'),
            'time' => date('h:i A'),
            'employees' => $employees
        ];

        $pdf = Pdf::loadView('pdf.payroll', $data);
        return $pdf->download('payroll.pdf');
    }

}
