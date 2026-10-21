<?php

namespace App\Controller\Iot;

use Symfony\Component\Routing\Annotation\Route;
use App\Controller\CommonController;
use Symfony\Component\HttpFoundation\Request;

use App\Entity\Iot\Emplacement;
use App\Entity\Iot\Temperature;

use App\Form\Iot\EmplacementType;

class EmplacementController extends CommonController
{
    #[Route(path: '/silo/emplacements', name: 'silo_emplacements')]
    public function siloEmplacements(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $this->check_user($request);
        $emplacements = $em->getRepository(Emplacement::class)->getAllForCompany($this->company);

        foreach($emplacements as $emplacement){
            $emplacement->calculate();
        }

        return $this->render('Iot/emplacements.html.twig', array(
            'emplacements' => $emplacements
        ));
    }

    #[Route(path: '/silo/emplacement/{id}', name: 'silo_emplacement')]
    public function siloEmplacement($id, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $duree = $request->query->get('duree');

        $this->check_user($request);
        $emplacement = $em->getRepository(Emplacement::class)->find($id);

        if($emplacement == NULL){
            $emplacement = new Emplacement();
            $emplacement->company = $this->company;
        } else {
            $emplacement->calculate();
        }

        $form = $this->createForm(EmplacementType::class, $emplacement, ['company' => $this->company]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $em->persist($emplacement);
            $em->flush();
            return $this->redirectToRoute('silo_emplacements');
        }

        $chartjss = [];
        if($emplacement->balise_ext){
            $chartjss[] = $this->getChartjsBalise($em, $emplacement->balise_ext, "red", $duree);
        }
        $colors = ["blue", "green", "orange", "purple", "brown", "teal", "magenta", "olive", "navy", "gray", "gold", "black"];
        foreach($emplacement->balises as $i => $balise){
            $chartjss[] = $this->getChartjsBalise($em, $balise, $colors[$i % count($colors)], $duree);
        }

        return $this->render('Iot/emplacement.html.twig', array(
            'form' => $form->createView(),
            'emplacement' => $emplacement,
            'chartjss' => $chartjss
        ));
    }

    public function getChartjsBalise($em, $balise, $color, $duree){
        $chartjs = ['annee'=> $balise->label ?: $balise->name, 'data' => [], 'color' => $color, 'hidden' => false];
        $temperatures = $em->getRepository(Temperature::class)->getForBalise($balise, $duree);
        foreach($temperatures as $temperature){
            $value = $balise->calculFor($temperature->temp);
            if($balise->isInRange($value)){
                $chartjs['data'][] = ['date' => $temperature->datetime->format("Y-m-d H:i:s"), 'value' => $value, 'name' => "" ];
            }
        }
        return $chartjs;
    }

    #[Route(path: '/silo/emplacement/{id}/delete', name: 'silo_emplacement_delete')]
    public function siloEmplacementDelete($id, Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();

        $emplacement = $em->getRepository(Emplacement::class)->find($id);
        if ($emplacement) {
            $em->remove($emplacement);
            $em->flush();
        }

        return $this->redirectToRoute('silo_emplacements');
    }
}
