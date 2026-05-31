<?php

namespace App\Controller\Materiel;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

use App\Controller\CommonController;
use App\Entity\Materiel\MaterielTravail;

class MaterielTravailController extends CommonController
{
    #[Route(path: '/materiels-travail', name: 'materiels_travail')]
    public function listAction(Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();

        $travaux = $em->getRepository(MaterielTravail::class)->getAllByCompany($this->company);

        return $this->render('Materiel/materiels_travail.html.twig', [
            'travaux' => $travaux,
            'navs' => ['Materiels' => 'materiels', 'Travaux' => 'materiels_travail'],
        ]);
    }
}
