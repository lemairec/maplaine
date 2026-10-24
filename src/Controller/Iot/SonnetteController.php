<?php

namespace App\Controller\Iot;

use Symfony\Component\Routing\Annotation\Route;
use App\Controller\CommonController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use DateTime;

use App\Entity\Company;
use App\Entity\Iot\Sonnette;
use App\Entity\Iot\SonnetteHist;

use App\Form\Iot\SonnetteType;

class SonnetteController extends CommonController
{
    //une demande plus vieille que ca n'est plus sonnee (esp32 hors ligne)
    const RING_TIMEOUT_S = 30;

    public function getSonnetteApi($em, Request $request){
        $company_str = $request->query->get("company");
        $sonnette_str = $request->query->get("sonnette");

        $company = $em->getRepository(Company::class)->findOneByName($company_str);
        if($company == null){
            throw new \Exception("not found Company : ".$company_str.",".$sonnette_str);
        }
        if($sonnette_str == null){
            throw new \Exception("not found sonnette_str : ".$company_str.",".$sonnette_str);
        }

        return $em->getRepository(Sonnette::class)->getOrCreate($company, $sonnette_str);
    }

    public function ring($em, $sonnette){
        $now = new DateTime();
        $sonnette->ring_request = $now;

        $sonnette_hist = new SonnetteHist();
        $sonnette_hist->sonnette = $sonnette;
        $sonnette_hist->datetime = $now;
        $em->persist($sonnette_hist);
        $em->persist($sonnette);
        $em->flush();
    }

    //appele toutes les secondes par l'esp32, repond $SON;RING une seule fois par demande
    //http://localhost:8000/sonnette/api_poll?company=dizy&sonnette=portail
    #[Route(path: '/sonnette/api_poll', name: 'sonnette_api_poll')]
    public function apiPoll(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $sonnette = $this->getSonnetteApi($em, $request);

        $now = new DateTime();
        $sonnette->last_update = $now;

        $res = "\$SON;OFF";
        if($sonnette->ring_request){
            if($now->getTimestamp() - $sonnette->ring_request->getTimestamp() < self::RING_TIMEOUT_S){
                $res = "\$SON;RING";
                $sonnette->last_ring = $now;
                $sonnette_hist = $em->getRepository(SonnetteHist::class)->getLastPending($sonnette);
                if($sonnette_hist){
                    $sonnette_hist->datetime_ring = $now;
                }
            }
            $sonnette->ring_request = NULL;
        }

        $em->flush();

        return new Response($res);
    }

    //declenche la sonnerie, l'esp32 la recupere au prochain poll
    //http://localhost:8000/sonnette/api_ring?company=dizy&sonnette=portail
    #[Route(path: '/sonnette/api_ring', name: 'sonnette_api_ring')]
    public function apiRing(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $sonnette = $this->getSonnetteApi($em, $request);
        $this->ring($em, $sonnette);

        return new Response("\$SON;OK");
    }

    #[Route(path: '/silo/sonnette/{id}/ring', name: 'silo_sonnette_ring')]
    public function siloSonnetteRing($id, Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();

        $sonnette = $em->getRepository(Sonnette::class)->find($id);
        if ($sonnette) {
            $this->ring($em, $sonnette);
        }

        return $this->redirectToRoute('silo_sonnette', ['id' => $id]);
    }

    #[Route(path: '/silo/sonnettes', name: 'silo_sonnettes')]
    public function siloSonnettes(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $this->check_user($request);
        $sonnettes = $em->getRepository(Sonnette::class)->getAllForCompany($this->company);

        foreach($sonnettes as $sonnette){
            $sonnette->calculate();
        }

        return $this->render('Iot/sonnettes.html.twig', array(
            'sonnettes' => $sonnettes
        ));
    }

    #[Route(path: '/silo/sonnette/{id}', name: 'silo_sonnette')]
    public function siloSonnette($id, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $duree = $request->query->get('duree');

        $this->check_user($request);
        $sonnette = $em->getRepository(Sonnette::class)->find($id);

        if($sonnette == NULL){
            $sonnette = new Sonnette();
            $sonnette->company = $this->company;
        } else {
            $sonnette->calculate();
        }

        $form = $this->createForm(SonnetteType::class, $sonnette);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $em->persist($sonnette);
            $em->flush();
            return $this->redirectToRoute('silo_sonnettes');
        }

        $sonnette_hists = [];
        if($sonnette->last_update){
            $sonnette_hists = $em->getRepository(SonnetteHist::class)->getForSonnette($sonnette, $duree);
        }

        return $this->render('Iot/sonnette.html.twig', array(
            'form' => $form->createView(),
            'sonnette' => $sonnette,
            'sonnette_hists' => $sonnette_hists
        ));
    }

    #[Route(path: '/silo/sonnette/{id}/delete', name: 'silo_sonnette_delete')]
    public function siloSonnetteDelete($id, Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();

        $sonnette = $em->getRepository(Sonnette::class)->find($id);
        if ($sonnette) {
            $em->remove($sonnette);
            $em->flush();
        }

        return $this->redirectToRoute('silo_sonnettes');
    }
}
