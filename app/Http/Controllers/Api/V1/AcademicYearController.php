<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AcademicYearResource;
use App\Models\AcademicYear;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AcademicYearController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AcademicYearResource::collection(AcademicYear::query()->orderByDesc('start_date')->get());
    }

    public function current(): AcademicYearResource
    {
        $year = AcademicYear::current();

        abort_if($year === null, 404, 'Belum ada tahun ajaran aktif.');

        return new AcademicYearResource($year);
    }
}
