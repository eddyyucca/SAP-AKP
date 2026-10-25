<?php

namespace App\Http\Controllers;

use App\Services\HrisEmployees;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Pencarian karyawan HRIS untuk kolom pilih orang (pelapor, PIC, korban, saksi). Hanya NIK, nama, departemen, jabatan, email. */
class EmployeeController extends Controller
{
    public function search(Request $request, HrisEmployees $hris): JsonResponse
    {
        $term = (string) $request->query('q', '');

        return response()->json([
            'configured' => HrisEmployees::configured(),
            'data' => $hris->search($term),
        ]);
    }
}
