<?php

namespace App\Controller\Iot;

use Symfony\Component\Routing\Annotation\Route;
use App\Controller\CommonController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use DateTime;

use App\Entity\Company;
use App\Entity\Iot\Balise;
use App\Entity\Iot\Temperature;

use App\Form\Iot\BaliseType;

//COMPTE
//ECRITURE
//OPERATION


class SiloController extends CommonController
{

    public function addTemperature($em, $t, $balise_str, $company){
        if($t){
            $balise_ = $em->getRepository(Balise::class)->getOrCreate($company, $balise_str);
            $temperature = new Temperature();
            $temperature->temp = $t;
            $temperature->balise = $balise_;
            $temperature->datetime = new DateTime();
            if($t > -100){
                $em->getRepository(Temperature::class)->addTemperature($temperature);
            }
            $balise_->last_temp = $t;
            $balise_->last_update = new DateTime();
            $balise_->calculateBalise();
            $em->persist($balise_);
            $em->flush();
        }
    }

    #[Route(path: '/silo/api_sonde', name: 'silo_api')]
    public function silo_api(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $t1 = $request->query->get("t1");
        $t2 = $request->query->get("t2");
        $t3 = $request->query->get("t3");
        $t4 = $request->query->get("t4");
        $t5 = $request->query->get("t5");
        $t6 = $request->query->get("t6");
        $t7 = $request->query->get("t7");
        $t8 = $request->query->get("t8");
        $t9 = $request->query->get("t9");
        $te = $request->query->get("te");
        $balise_str = $request->query->get("balise");
        $company = $request->query->get("company");

        $company = $em->getRepository(Company::class)->findOneByName($company);
        if($company == null){
            throw new \Exception("not found Company : ".$company.",".$balise_str);
        }

        $this->addTemperature($em,$t1,$balise_str."_1", $company);
        $this->addTemperature($em,$t2,$balise_str."_2", $company);
        $this->addTemperature($em,$t3,$balise_str."_3", $company);
        $this->addTemperature($em,$t4,$balise_str."_4", $company);
        $this->addTemperature($em,$t5,$balise_str."_5", $company);
        $this->addTemperature($em,$t6,$balise_str."_6", $company);
        $this->addTemperature($em,$t7,$balise_str."_7", $company);
        $this->addTemperature($em,$t8,$balise_str."_8", $company);
        $this->addTemperature($em,$t9,$balise_str."_9", $company);
        $this->addTemperature($em,$te,$balise_str."_e", $company);

        return new Response("ok");
    }


    #[Route(path: '/silo/balises', name: 'silo_balises')]
    public function siloBalises(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $this->check_user($request);
        $balises = $em->getRepository(Balise::class)->getAllForCompany($this->company);

        $balises_names = [];
        $balises_others = [];

        foreach($balises as $b){
            
            $b->calculateBalise();
            if($b->label){
                $balises_names[] = $b;
            } else {
                $balises_others[] = $b;
            }


        }

        return $this->render('Iot/balises.html.twig', array(
            'balises_names' => $balises_names,
            'balises_others' => $balises_others
        ));
    }

    #[Route(path: '/silo/balise/{id}', name: 'silo_balise')]
    public function siloBalise($id, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $duree = $request->query->get('duree');

        $this->check_user($request);
        $balise = $em->getRepository(Balise::class)->find($id);
        $temperatures = $em->getRepository(Temperature::class)->getForBalise($balise, $duree);
        
        if($balise){
            $balise->calculateBalise();
        }

        $form = $this->createForm(BaliseType::class, $balise);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $em->persist($balise);
            $em->flush();
            return $this->redirectToRoute('silo_balises');
        }

        $chartjs_min = ['annee'=> 'min', 'data' => [], 'color' => "", 'hidden' => false];
        $chartjs_max = ['annee'=> 'min', 'data' => [], 'color' => "", 'hidden' => false];

        foreach($temperatures as $temperature){
            $temperature->calculate = $balise->calculFor($temperature->temp);
            if($balise->isInRange($temperature->calculate)){
                $chartjs_min['data'][] = ['date' => $temperature->datetime->format("Y-m-d H:i:s"), 'value' => $temperature->calculate, 'name' => "" ];
            }
        }
        $chartjss[] = $chartjs_min;
        //dump($chartjss);

        return $this->render('Iot/balise.html.twig', array(
            'form' => $form->createView(),
            'balise' => $balise,
            'temperatures' => $temperatures,
            'chartjss' => $chartjss
        ));
    }

    #[Route(path: '/silo/balise/{id}/delete', name: 'silo_balise_d')]
    public function siloBaliseDelete($id, Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();

        $balise = $em->getRepository(Balise::class)->find($id);
        if ($balise) {
            $temperatures = $em->getRepository(Temperature::class)->findBy(['balise' => $balise]);
            foreach ($temperatures as $temperature) {
                $em->remove($temperature);
            }
            $em->remove($balise);
            $em->flush();
        }

        return $this->redirectToRoute('silo_balises');
    }

}
