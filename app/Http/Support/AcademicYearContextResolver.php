<?php

namespace App\Http\Support;

use App\Domain\Academic\Repositories\AcademicYearRepositoryInterface;
use Illuminate\Http\Request;

final class AcademicYearContextResolver
{
    public function __construct(
        private readonly AcademicYearRepositoryInterface $years,
    ) {}

    public function resolve(?int $requestedId, ?Request $request = null): ?int
    {
        if ($requestedId !== null && $requestedId >= 1) {
            $found = $this->years->findById($requestedId)?->id;
            if ($found !== null) {
                if ($request?->hasSession()) {
                    $request->session()->put('current_academic_year_id', $found);
                }

                return $found;
            }
        }

        $request ??= request();
        if ($request instanceof Request && $request->hasSession()) {
            $fromSession = $request->session()->get('current_academic_year_id');
            if (is_numeric($fromSession)) {
                $candidate = (int) $fromSession;
                $found = $this->years->findById($candidate)?->id;
                if ($found !== null) {
                    return $found;
                }
                $request->session()->forget('current_academic_year_id');
            }
        }

        $fallback = null;
        foreach ($this->years->listAll() as $year) {
            if ($year->isCurrent) {
                if ($request instanceof Request && $request->hasSession()) {
                    $request->session()->put('current_academic_year_id', $year->id);
                }

                return $year->id;
            }
            $fallback ??= $year->id;
        }

        if ($fallback !== null && $request instanceof Request && $request->hasSession()) {
            $request->session()->put('current_academic_year_id', $fallback);
        }

        return $fallback;
    }
}
