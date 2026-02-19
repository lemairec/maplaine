<?php

namespace App\Controller\Gestion;

use Symfony\Component\Routing\Annotation\Route;
use App\Controller\CommonController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use DateTime;
use App\Entity\Gestion\Compte;
use App\Entity\Gestion\Ecriture;
use App\Entity\Gestion\Operation;
use App\Entity\Gestion\Emprunt;
use App\Form\Gestion\CompteType;
use App\Form\Gestion\EcritureType;
use App\Form\Gestion\OperationType;
use App\Form\Gestion\EmpruntType;
use App\Form\Gestion\EmpruntAnnuiteType;
use Symfony\Component\HttpFoundation\File\File;

//COMPTE
//ECRITURE
//OPERATION


class EmpruntController extends CommonController
{
    #[Route(path: '/emprunts', name: 'emprunts')]
    public function empruntsAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $c = $this->getCurrentCampagne($request);

        $emprunts = $em->getRepository(Emprunt::class)->getAllForCompany($this->company);
       
        foreach($emprunts as $emprunt){
            $emprunt->reste = 0;

            if($emprunt->enable){
                $operations = $em->getRepository(Operation::class)->findByEmprunt($emprunt);
                foreach($operations as $o){
                    foreach($o->ecritures as $e){
                        if($e->compte->id == $emprunt->compteEmprunt->id){
                            $emprunt->reste = $emprunt->reste + $e->value;
                        }
                    }
                }
            }
        }

        return $this->render('Gestion/emprunts.html.twig', array(
            'emprunts' => $emprunts,
        ));
    }

    #[Route(path: '/emprunt/{emprunt_id}', name: 'emprunt')]
    public function compteEditAction($emprunt_id, Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();
        $campagne = $this->getCurrentCampagne($request);
        $session = $request->getSession();
        $operations=[]; 

        if($emprunt_id == '0'){
            $emprunt = new Emprunt();
            $emprunt->company = $this->company;
        } else {
            $emprunt = $em->getRepository(Emprunt::class)->find($emprunt_id);
            $operations = $em->getRepository(Operation::class)->getAllForEmprunt($emprunt);
        }
        $form = $this->createForm(EmpruntType::class, $emprunt, array(
            'comptes' => $em->getRepository(Compte::class)->getAllForCompany($this->company),
        ));
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $emprunt->campagne = $campagne;
            $em->getRepository(Emprunt::class)->save($emprunt);
            return $this->redirectToRoute('emprunts');
        }
        return $this->render('Gestion/emprunt.html.twig', array(
            'campagnes' => $this->campagnes,
            'campagne_id' => $campagne->id,
            'form' => $form->createView(),
            'operations' => $operations,
            'emprunt_id' => $emprunt_id
        ));
    }

    #[Route(path: '/emprunt/{emprunt_id}/annuite', name: 'emprunt_annuite')]
    public function compte2EditAction($emprunt_id, Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();
        $campagne = $this->getCurrentCampagne($request);
        $emprunt = $em->getRepository(Emprunt::class)->find($emprunt_id);
        
        $form = $this->createForm(EmpruntAnnuiteType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $data = $form->getData();
            $annuite = $this->parseFloat($data["annuite"]);
            $assurance = $this->parseFloat($data["assurance"]);
            $interet = $this->parseFloat($data["interet"]);
            

            $operation = new Operation();
            $operation->company = $this->company;
            $operation->name = $emprunt->name." ".$campagne->name;
            $operation->date = $data["date"];
            $operation->emprunt = $emprunt;

            $em->persist($operation);
            $em->flush();
    
            $ecriture = new Ecriture();
            $ecriture->compte = $emprunt->banque;
            $ecriture->operation = $operation;
            $ecriture->value = $annuite + $assurance + $interet;
            $em->persist($ecriture);
    
            $ecriture = new Ecriture();
            $ecriture->compte = $emprunt->compteEmprunt;
            $ecriture->campagne = $campagne;
            $ecriture->operation = $operation;
            $ecriture->value = -$annuite;
            $em->persist($ecriture);

            
            if($interet != 0){
                $ecriture = new Ecriture();
                $ecriture->compte = $emprunt->compteInteret;
                $ecriture->campagne = $campagne;
                $ecriture->operation = $operation;
                $ecriture->value = -$interet;
                $em->persist($ecriture);
            }

            if($assurance != 0){
                $ecriture = new Ecriture();
                $ecriture->compte = $emprunt->compteInteret;
                $ecriture->campagne = $campagne;
                $ecriture->operation = $operation;
                $ecriture->value = -$assurance;
                $em->persist($ecriture);
            }
    
            $em->flush();

            return $this->redirectToRoute('emprunt', ["emprunt_id" => $emprunt->id]);
        }
        return $this->render('base_form.html.twig', array(
            'campagnes' => $this->campagnes,
            'campagne_id' => $campagne->id,
            'form' => $form->createView()
        ));
    }

}