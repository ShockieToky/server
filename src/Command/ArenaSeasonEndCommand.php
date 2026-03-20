<?php

namespace App\Command;

use App\Entity\ArenaSeason;
use App\Repository\ArenaSeasonPlayerRepository;
use App\Repository\ArenaSeasonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * php bin/console app:arena:season-end [--next-name="Saison 2"] [--next-days=14]
 *
 * Clôture la saison active, affiche le top 3, et crée automatiquement
 * la prochaine saison (sauf si --no-create est passé).
 */
#[AsCommand(
    name: 'app:arena:season-end',
    description: 'Clôture la saison d\'arène active et démarre la suivante',
)]
class ArenaSeasonEndCommand extends Command
{
    public function __construct(
        private readonly ArenaSeasonRepository       $seasonRepository,
        private readonly ArenaSeasonPlayerRepository $playerRepository,
        private readonly EntityManagerInterface      $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('next-name', null, InputOption::VALUE_OPTIONAL,
                'Nom de la prochaine saison', null)
            ->addOption('next-days', null, InputOption::VALUE_OPTIONAL,
                'Durée en jours de la prochaine saison', 14)
            ->addOption('no-create', null, InputOption::VALUE_NONE,
                'Ne pas créer de prochaine saison')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $season = $this->seasonRepository->findActive();
        if (!$season) {
            $io->error('Aucune saison active trouvée.');
            return Command::FAILURE;
        }

        $io->title("Clôture de la saison : « {$season->getName()} »");

        // ── Statistiques ──────────────────────────────────────────────────────
        $ranking = $this->playerRepository->findRanking($season, 50);
        $total   = count($ranking);
        $io->info("Joueurs ayant participé : $total");

        $top = array_slice($ranking, 0, 3);
        if (!empty($top)) {
            $io->section('Podium');
            $table = [];
            foreach ($top as $i => $entry) {
                $table[] = [
                    $i + 1,
                    $entry->getUser()?->getPseudo() ?? '?',
                    $entry->getWins(),
                    $entry->getLosses(),
                ];
            }
            $io->table(['Rang', 'Joueur', 'Victoires', 'Défaites'], $table);
        }

        // ── Clôture ───────────────────────────────────────────────────────────
        $season->setIsActive(false);
        $io->success("Saison « {$season->getName()} » clôturée.");

        // ── Création de la prochaine saison ────────────────────────────────────
        if (!$input->getOption('no-create')) {
            $nextDays = max(1, (int) $input->getOption('next-days'));
            $nextName = $input->getOption('next-name');

            if ($nextName === null) {
                // Auto-incrémente le numéro de saison si le nom suit le pattern "Saison N"
                if (preg_match('/Saison\s+(\d+)/i', $season->getName(), $m)) {
                    $nextName = 'Saison ' . ((int) $m[1] + 1);
                } else {
                    $nextName = $season->getName() . ' (suite)';
                }
            }

            $nextEndsAt = new \DateTimeImmutable("+{$nextDays} days");

            $next = (new ArenaSeason())
                ->setName($nextName)
                ->setEndsAt($nextEndsAt);

            $this->em->persist($next);
            $this->em->flush();

            $io->success(sprintf(
                "Nouvelle saison créée : « %s » (fin prévue : %s)",
                $nextName,
                $nextEndsAt->format('d/m/Y'),
            ));
        } else {
            $this->em->flush();
        }

        return Command::SUCCESS;
    }
}
