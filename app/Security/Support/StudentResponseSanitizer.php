<?php

namespace App\Security\Support;

use App\Application\Student\DTOs\StudentDetailDTO;
use App\Application\Student\DTOs\StudentListItemDTO;
use App\Models\User;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\Permission;

final class StudentResponseSanitizer
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function sanitizeDetail(StudentDetailDTO $detail, ?User $user): array
    {
        $data = $detail->toArray();

        if ($user === null || ! $this->authorization->userHasPermission($user, Permission::STUDENTS_VIEW_PII)) {
            unset($data['national_id']);
        }

        return $data;
    }

    /**
     * @param  list<StudentListItemDTO>  $items
     * @return list<array<string, mixed>>
     */
    public function sanitizeList(array $items): array
    {
        return array_map(
            fn (StudentListItemDTO $item): array => $item->toArray(),
            $items,
        );
    }
}
