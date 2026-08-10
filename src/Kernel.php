<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

// Noyau minimal — le projet n'en avait aucun : les tests instanciaient le
// contrôleur directement, donc l'application n'avait jamais démarré. Sans
// noyau, aucun contrôle de fumée n'est possible : on ne peut pas vérifier
// qu'une version servie répond si rien ne peut être servi.
class Kernel extends BaseKernel
{
    use MicroKernelTrait;
}
