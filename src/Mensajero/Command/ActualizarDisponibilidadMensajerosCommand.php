<?php

declare(strict_types=1);

namespace App\Mensajero\Command;

use App\Mensajero\Service\MensajeroService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:mensajeros:actualizar-disponibilidad',
    description: 'Actualiza la disponibilidad de todos los mensajeros basado en sus heartbeats recientes',
)]
class ActualizarDisponibilidadMensajerosCommand extends Command
{
    public function __construct(
        private readonly MensajeroService $mensajeroService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Actualizando disponibilidad de mensajeros basado en heartbeats...');

        $actualizados = $this->mensajeroService->actualizarDisponibilidadGlobal();

        $io->success(sprintf('%d mensajeros actualizados.', $actualizados));

        return Command::SUCCESS;
    }
}
