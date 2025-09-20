<?php

namespace App\Controller\Iot;

use Symfony\Component\Routing\Annotation\Route;
use App\Controller\CommonController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use DateTime;

use App\Entity\Company;
use App\Entity\Iot\Iot;
use App\Entity\Iot\Temperature;
use App\Entity\Iot\Sechoir;

use App\Form\Iot\IotType;

//COMPTE
//ECRITURE
//OPERATION


class SechoirController extends CommonController
{
    #[Route(path: '/iot/api_sechoir', name: 'api_sechoir')]
    public function apiSechoirBalise(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $sechoir = new Sechoir();
        $sechoir->datetime = new Datetime();
        $sechoir->description = $request->query->get("description");
        $sechoir->t_hot = $request->query->get("t_hot");
        $sechoir->t_cons = $request->query->get("t_cons");
        $sechoir->t_out = $request->query->get("t_out");
        $sechoir->bruleur = $request->query->get("bruleur");
        $sechoir->m_hot = $request->query->get("m_hot");
        $sechoir->m_cold = $request->query->get("m_cold");
        $sechoir->nb_cycle = $request->query->get("nb_cycle");

        
        $sechoir->my_data = $request->query->all();

        $em->persist($sechoir);
        $em->flush();

        $sechoirs = $em->getRepository(Sechoir::class)->findAll();
        //dump($sechoirs);

        return new Response("ok");
    }

    #[Route(path: '/silo/sechoir', name: 'silo_sechoir')]
    public function sechoirBalise(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        
        $begin = $request->query->get('begin');
        $end = $request->query->get('end');
        $duree = $request->query->get('duree');
        
        if($begin == null){
            $end = new Datetime();
            $begin = new Datetime();
            if($duree == "all"){
                $begin->modify('-24 month');
            } else if($duree == "2m"){
                $begin->modify('-2 month');
            } else if($duree == "1m"){
                $begin->modify('-1 month');
            } else if($duree == "6m"){
                $begin->modify('-6 month');
            } else if($duree == "1w"){
                $begin->modify('-7 day');
            } else if($duree == "1d"){
                $begin->modify('-1 day');
            } else {
                $begin->modify('-1 day');
            }
            dump($begin);
            dump($end);
            
        }
        
        
        $sechoirs = $em->getRepository(Sechoir::class)->getAllBE($begin, $end);
        //dump($sechoirs);

        $chartjss = [];
        
        $data = [];
        foreach ($sechoirs as $sechoir) {
            if($sechoir->t_out && $sechoir->t_out > 0){
                $data[] = ["date"=>$sechoir->datetime->format('Y-m-d H:i:s'), "value"=>$sechoir->t_out];
            }
        }
        $chartjss[] = ["annee"=>"out", "color"=> "#6600ff", "data"=>$data];

        $data = [];
        foreach ($sechoirs as $sechoir) {
            if($sechoir->t_hot && $sechoir->t_hot > 0){
                $data[] = ["date"=>$sechoir->datetime->format('Y-m-d H:i:s'), "value"=>$sechoir->t_hot];
            }
        }
        $chartjss[] = ["annee"=>"chaud", "color"=> "#ff0066", "data"=>$data];

        //dump($chartjss);

        
        return $this->render('Iot/sechoirs.html.twig', array(
            'sechoirs' => $sechoirs,
            'chartjss' => $chartjss
        ));
    }

}


//http://localhost:8000/iot/api_config?company=dizy&name=iot&version=----&config=$C,MIN,10,TEMP,5,*