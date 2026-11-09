<?php

namespace App\Enums;

enum AbsenceType: string
{
    case Conge = 'conge';
    case Absence = 'absence';
    case Formation = 'formation';
    case Autre = 'autre';
    /** Fermeture / indisponibilité exceptionnelle saisie par le salon. */
    case Exception = 'exception';

    /** Types qu'un professionnel peut demander depuis l'application. */
    public static function requestable(): array
    {
        return ['conge', 'absence', 'formation', 'autre'];
    }

    public function label(): string
    {
        return match ($this) {
            self::Conge => 'Congé',
            self::Absence => 'Absence',
            self::Formation => 'Formation',
            self::Autre => 'Autre',
            self::Exception => 'Indisponibilité exceptionnelle',
        };
    }
}
