<?php

namespace App\Http\Controllers\Organization;

use App\Application\Organization\Queries\GetDirectorateSchoolStructureHandler;
use App\Application\Organization\Queries\GetDirectorateSchoolStructureQuery;
use App\Http\Controllers\Controller;
use App\Security\Authorization\SchoolScopeService;
use App\Security\Context\SchoolContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «المديريات والمدارس»: directorates, the user's schools in each, and each school's
 * branches and departments. Writes go to SchoolRegistryController / DirectorateRegistryController.
 */
final class DirectorateSchoolPageController extends Controller
{
    public function __construct(
        private readonly SchoolScopeService $schoolScope,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function index(Request $request, GetDirectorateSchoolStructureHandler $handler): Response
    {
        $user = $request->user();
        abort_unless($user !== null && ($user->can('manageSchools') || $user->can('manageDirectorates')), 403);

        $focusSchoolId = $request->integer('school');

        return Inertia::render('organization/directorates-schools', [
            'directorates' => $handler->handle(new GetDirectorateSchoolStructureQuery(
                allowedSchoolIds: $this->schoolScope->allowedSchoolIds($user),
            )),
            'current_school_id' => $this->schoolContext->id(),
            // Edit → تعديل المدرسة on the admission page opens a school here (?school=ID&edit=1).
            'focus' => [
                'school_id' => $focusSchoolId > 0 ? $focusSchoolId : null,
                'edit' => $request->boolean('edit'),
            ],
            'authorization' => [
                'can_manage_schools' => $user->can('manageSchools'),
                'can_manage_directorates' => $user->can('manageDirectorates'),
            ],
        ]);
    }
}
