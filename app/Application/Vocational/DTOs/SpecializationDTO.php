<?php

namespace App\Application\Vocational\DTOs;

final readonly class SpecializationDTO
{
    /**
     * @param  list<array{id:int,code:string,name:string,status:int}>  $tracks
     * @param  list<array{id:int,subject_id:int,is_required:bool,credit_hours:?int,status:int}>  $subjectLinks
     */
    public function __construct(
        public int $id,
        public int $schoolId,
        public string $code,
        public string $name,
        public ?string $description,
        public int $status,
        public array $tracks = [],
        public array $subjectLinks = [],
    ) {}
}
