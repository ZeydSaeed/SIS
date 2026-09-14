<?php

namespace App\Http\Controllers;

use App\Domain\Academic\Repositories\AcademicYearRepositoryInterface;
use App\Security\Context\SchoolContextResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class SchoolContextController extends Controller
{
    public function __construct(
        private readonly SchoolContextResolver $resolver,
        private readonly AcademicYearRepositoryInterface $years,
    ) {}

    public function updateSchool(Request $request): RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        $validated = $request->validate([
            'school_id' => ['required', 'integer', 'min:1'],
        ]);

        $schoolId = (int) $validated['school_id'];
        if (! $this->resolver->userCanAccessSchool($user, $schoolId)) {
            throw ValidationException::withMessages([
                'school_id' => 'المدرسة غير مسموحة لهذا المستخدم.',
            ]);
        }

        $request->session()->put('current_school_id', $schoolId);

        return back();
    }

    public function updateYear(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'academic_year_id' => ['required', 'integer', 'min:1'],
        ]);

        $yearId = (int) $validated['academic_year_id'];
        if ($this->years->findById($yearId) === null) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'السنة الدراسية غير موجودة.',
            ]);
        }

        $request->session()->put('current_academic_year_id', $yearId);

        return back();
    }
}
