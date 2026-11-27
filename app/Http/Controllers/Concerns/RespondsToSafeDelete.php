<?php

namespace App\Http\Controllers\Concerns;

use App\Application\Shared\Commands\DeleteRecordCommand;
use App\Application\Shared\Commands\DeleteRecordHandler;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * «حذف» from a page — one shape for every module: run the safe delete for the current school, audit it,
 * and come back with the success flash or the refusal (e.g. `delete.linked?with=…`) under `$errorKey`.
 */
trait RespondsToSafeDelete
{
    protected function safeDelete(
        Request $request,
        string $entity,
        int $id,
        string $errorKey,
        string $flash,
        SecurityEventType $event,
        /** Where to go once deleted when «back» would be the deleted record's own page (e.g. /exams). */
        ?string $redirectTo = null,
    ): RedirectResponse {
        $result = app(DeleteRecordHandler::class)->handle(
            new DeleteRecordCommand($entity, app(SchoolContext::class)->requireId(), $id),
        );
        if ($result->failed()) {
            return redirect()->back()->withErrors([$errorKey => $result->errors[0]]);
        }

        $user = $request->user();
        assert($user !== null);
        app(SecurityAuditLoggerInterface::class)->record($event, 'web.'.$entity.'.delete', 'deleted', $user, $entity.':'.$id);

        return ($redirectTo !== null ? redirect($redirectTo) : redirect()->back())->with('success', $flash);
    }
}
