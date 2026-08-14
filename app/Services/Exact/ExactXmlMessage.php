<?php

namespace App\Services\Exact;

/**
 * Eén melding uit het `<Messages>`-blok van de XML API.
 *
 * Exact antwoordt altijd met HTTP 200; de ernst staat in het `type`-attribuut:
 * 0 = fout, 1 = waarschuwing, 2 = informatie, 3 = fatale fout. Een geslaagde
 * aflettering meldt zichzelf als type 2 ("... 2 Regels Afgeletterd.").
 */
readonly class ExactXmlMessage
{
    public const TYPE_ERROR = 0;

    public const TYPE_WARNING = 1;

    public const TYPE_INFO = 2;

    public const TYPE_FATAL = 3;

    public function __construct(
        public int $type,
        public string $description,
    ) {}

    public function isError(): bool
    {
        return in_array($this->type, [self::TYPE_ERROR, self::TYPE_FATAL], true);
    }
}
