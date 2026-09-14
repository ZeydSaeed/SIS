<?php

namespace App\Http\Controllers;

use App\Security\Authorization\SchoolScopeService;
use App\Security\Context\SchoolContextResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class SchoolContextController extends Controller
{
    public function __construct(
        private readonly SchoolScopeService $schoolScope,
        private readonly SchoolContextResolver $resolver,
    ) {}

    public function update(Request $request): RedirectResponse
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
}
