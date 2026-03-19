<?php

namespace App\Entity;

use App\Repository\EffectRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Catalogue des effets applicables par les attaques (buffs, debuffs, utilitaires).
 *
 * durationType : 'duration' = reste X tours | 'instant' = s'applique immédiatement
 * polarity     : 'positive' = bénéfique | 'negative' = négatif
 */
#[ORM\Entity(repositoryClass: EffectRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_effect_name', columns: ['name'])]
class Effect
{
    // ── Constantes ────────────────────────────────────────────────────────────

    public const DURATION_TYPES = ['duration', 'instant'];
    public const POLARITIES     = ['positive', 'negative'];

    /**
     * Tous les effets avec leurs métadonnées par défaut.
     * Format : name => [label, durationType, polarity, description, defaultValue|null]
     */
    public const CATALOGUE = [
        // ── Effets sur la durée — bénéfiques ──────────────────────────────────
        'aug_defense'    => ['Augmentation défense',  'duration', 'positive', 'Augmente la défense de la cible de X%',          40],
        'aug_vitesse'    => ['Augmentation vitesse',  'duration', 'positive', 'Augmente la vitesse de la cible de X%',           20],
        'aug_attaque'    => ['Augmentation attaque',  'duration', 'positive', "Augmente l'attaque de la cible de X%",            30],
        'protection'     => ['Protection',            'duration', 'positive', 'Immunise aux effets négatifs (hors Suppression)',  null],
        'bouclier'       => ['Bouclier',              'duration', 'positive', 'Protège de X% des PV max',                       25],
        'recuperation'   => ['Récupération',          'duration', 'positive', 'Regagne X% des PV max à chaque début de tour',   10],
        'invincibilite'  => ['Invincibilité',         'duration', 'positive', 'La cible ne subit aucun dégât',                 null],
        'soif_sang'      => ['Soif de sang',          'duration', 'positive', 'Récupère X% des dégâts infligés comme PV',       30],

        // ── Effets sur la durée — négatifs ────────────────────────────────────
        'etourdissement' => ['Étourdissement',        'duration', 'negative', 'La cible ne peut pas attaquer',                 null],
        'sommeil'        => ['Sommeil',               'duration', 'negative', 'La cible est endormie ; se réveille si attaquée', null],
        'brulure'        => ['Brûlure',               'duration', 'negative', 'La cible subit X% de ses PV max en dégâts par tour', 3],
        'red_defense'    => ['Réduction défense',     'duration', 'negative', 'Réduit la défense de la cible de X%',           40],
        'red_vitesse'    => ['Réduction vitesse',     'duration', 'negative', 'Réduit la vitesse de la cible de X%',           20],
        'red_attaque'    => ['Réduction attaque',     'duration', 'negative', "Réduit l'attaque de la cible de X%",            30],
        'bloqueur'       => ['Bloqueur',              'duration', 'negative', "La cible ne peut pas recevoir d'effet bénéfique", null],
        'silence'        => ['Silence',               'duration', 'negative', 'La cible ne peut utiliser que son sort 1',      null],
        'provocation'    => ['Provocation',           'duration', 'negative', "La cible est obligée d'attaquer le lanceur avec le sort 1", null],

        // ── Effets instantanés — bénéfiques ──────────────────────────────────
        'soins'          => ['Soins',                 'instant',  'positive', 'Soigne la cible de X% de ses PV max',           20],
        'purification'   => ['Purification',          'instant',  'positive', 'Supprime les effets négatifs de la cible',      null],
        'ignore_defense' => ['Ignore défense',        'instant',  'positive', 'Ignore la défense de la cible pour ce hit',     null],

        // ── Effets instantanés — négatifs ─────────────────────────────────────
        'suppression'         => ['Suppression',          'instant',  'negative', 'Supprime les effets bénéfiques de la cible',    null],
        'activation_brulure'  => ['Activation brûlure',   'instant',  'negative', 'Fait proc toutes les brûlures de la cible (dégâts sans réduire la durée)', null],

        // ── Effets instantanés — bénéfiques (suite) ───────────────────────────
        'vampirisme'     => ['Vampirisme',            'instant',  'positive', 'Le lanceur récupère X% des dégâts infligés par ce hit comme PV', 15],
        // ── Effets spéciaux ───────────────────────────────────────────────────
        'regain_tour'    => ['Regain de tour',        'instant',  'positive', 'L\'acteur rejoue immédiatement après cette action',             null],    ];

    // ── Champs ────────────────────────────────────────────────────────────────

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Identifiant fonctionnel unique (ex: 'brulure', 'etourdissement'). */
    #[ORM\Column(length: 50)]
    private string $name = '';

    /** Libellé affiché en jeu. */
    #[ORM\Column(length: 80)]
    private string $label = '';

    /** 'duration' | 'instant' */
    #[ORM\Column(length: 10)]
    private string $durationType = 'duration';

    /** 'positive' | 'negative' */
    #[ORM\Column(length: 10)]
    private string $polarity = 'negative';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * Valeur par défaut de l'effet (% ou flat selon l'effet).
     * Exemple : brulure = 3 (3% PV max), bouclier = 25 (25% PV max).
     * Peut être surchargé par AttackEffect.value.
     */
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $defaultValue = null;

    // ── Getters / Setters ─────────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): self { $this->label = $label; return $this; }

    public function getDurationType(): string { return $this->durationType; }
    public function setDurationType(string $t): self { $this->durationType = $t; return $this; }

    public function getPolarity(): string { return $this->polarity; }
    public function setPolarity(string $p): self { $this->polarity = $p; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): self { $this->description = $d; return $this; }

    public function getDefaultValue(): ?float { return $this->defaultValue; }
    public function setDefaultValue(?float $v): self { $this->defaultValue = $v; return $this; }
}
