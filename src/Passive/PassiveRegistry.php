<?php

namespace App\Passive;

use App\Passive\Faction\BeastClanPassive;
use App\Passive\Faction\DinosaurClanPassive;
use App\Passive\Faction\FireOrderPassive;
use App\Passive\Faction\MoonCultPassive;
use App\Passive\Faction\ProtocoleAssautPassive;
use App\Passive\Faction\SleepingCultPassive;
use App\Passive\Faction\WaterMasterPassive;
use App\Passive\Origine\AncientFracturePassive;
use App\Passive\Origine\AridePassive;
use App\Passive\Origine\CrealiaPassive;
use App\Passive\Origine\DesertPassive;
use App\Passive\Origine\EnclavePassive;
use App\Passive\Origine\GodsFracturePassive;
use App\Passive\Origine\HeritageNomadePassive;
use App\Passive\Origine\KilimaPassive;
use App\Passive\Origine\MoonIslandPassive;
use App\Passive\Origine\MystiquePassive;
use App\Passive\Origine\SamoraPassive;

/**
 * Registre de tous les passifs de faction et d'origine.
 *
 * Usage :
 *   $passive = PassiveRegistry::get('fire_order');
 *   if ($passive !== null) $passive->apply($ctx);
 */
class PassiveRegistry
{
    /** @var array<string, PassiveInterface> */
    private static array $map = [];

    private static bool $initialized = false;

    private static function init(): void
    {
        if (self::$initialized) return;
        self::$initialized = true;

        $passives = [
            // Faction
            new BeastClanPassive(),
            new DinosaurClanPassive(),
            new FireOrderPassive(),
            new MoonCultPassive(),
            new ProtocoleAssautPassive(),
            new SleepingCultPassive(),
            new WaterMasterPassive(),
            // Origine
            new AncientFracturePassive(),
            new AridePassive(),
            new CrealiaPassive(),
            new DesertPassive(),
            new EnclavePassive(),
            new GodsFracturePassive(),
            new HeritageNomadePassive(),
            new KilimaPassive(),
            new MoonIslandPassive(),
            new MystiquePassive(),
            new SamoraPassive(),
        ];

        foreach ($passives as $passive) {
            self::$map[$passive->getSlug()] = $passive;
        }
    }

    public static function get(string $slug): ?PassiveInterface
    {
        self::init();
        return self::$map[$slug] ?? null;
    }

    /** @return array<string, PassiveInterface> */
    public static function all(): array
    {
        self::init();
        return self::$map;
    }
}
