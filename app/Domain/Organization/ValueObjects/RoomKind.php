<?php

namespace App\Domain\Organization\ValueObjects;

/**
 * `organization.room_types.kind` — what a managed room type is (the type itself is user data: «مختبر حاسوب»,
 * «ورشة صناعية» …). Labs and workshops are where practical lessons are held by default.
 */
enum RoomKind: int
{
    case Classroom = 1;
    case Lab = 2;
    case Workshop = 3;
    case Hall = 4;
    case Other = 9;

    public function practicalByDefault(): bool
    {
        return $this === self::Lab || $this === self::Workshop;
    }

    /**
     * The solver's legacy room class (`organization.rooms.room_type`): 2 = a room practical lessons may use,
     * 1 = any other room. Kept in step with the managed type so placement keeps working unchanged.
     */
    public static function legacyRoomType(bool $supportsPractical): int
    {
        return $supportsPractical ? 2 : 1;
    }
}
