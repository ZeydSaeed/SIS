<?php

namespace App\Domain\Student\ValueObjects;

final class StudentDocumentType
{
    public const Identity = 1;

    public const Certificate = 2;

    public const Qualification = 3;

    public const Medical = 4;

    public const Other = 9;

    public const StudentIdFront = 11;

    public const StudentIdBack = 12;

    public const FatherIdFront = 13;

    public const FatherIdBack = 14;

    public const MotherIdFront = 15;

    public const MotherIdBack = 16;

    public const ResidenceFront = 17;

    public const ResidenceBack = 18;

    public const GraduationCertificate = 19;

    public const PersonalPhoto = 20;

    public static function isValid(int $type): bool
    {
        return in_array($type, [
            self::Identity,
            self::Certificate,
            self::Qualification,
            self::Medical,
            self::Other,
            self::StudentIdFront,
            self::StudentIdBack,
            self::FatherIdFront,
            self::FatherIdBack,
            self::MotherIdFront,
            self::MotherIdBack,
            self::ResidenceFront,
            self::ResidenceBack,
            self::GraduationCertificate,
            self::PersonalPhoto,
        ], true);
    }
}
