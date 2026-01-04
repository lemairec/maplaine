<?php

namespace App\Controller\Gestion;

use Symfony\Component\Routing\Annotation\Route;
use App\Controller\CommonController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\File;
use DateTime;

use App\Entity\Culture;
use App\Entity\Gestion\Commercialisation;
use App\Entity\Cotation\Cotation;
use App\Entity\Cotation\CotationProduit;
use App\Entity\Cotation\PrixMoyen;
use App\Entity\Gestion\FactureFournisseur;
use App\Entity\Gestion\Compte;

use App\Form\Gestion\CommercialisationType;
use App\Form\Cotation\CotationsCajType;
use App\Form\Cotation\CotationAddType;
use App\Form\Cotation\CotationType;
use App\Form\Cotation\CotationProduitType;
use App\Form\Cotation\PrixMoyenType;


//COMPTE
//ECRITURE
//OPERATION


class CotationController extends CommonController
{

    #[Route(path: '/cotations', name: 'cotations')]
    public function cotationAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $cotations = $em->getRepository(Cotation::class)->getLasts();
        return $this->render('Cotation/cotations.html.twig', array(
            'cotations' => $cotations,
        ));
    }

    function updatecharjss($produits){
        $em = $this->getDoctrine()->getManager();
        
        $chartjss = [];
        
        $campagne = "2024";
        foreach($produits as $produit){
            $culture = $produit->label;
            $cotations = $em->getRepository(Cotation::class)->getAllProduitAndCampagne($culture, $campagne);
            $data = [];
            foreach ($cotations as $cotation) {
                $data[] = ["date"=>$cotation->date->format('d/m/y'), "value"=>$cotation->value];
            }
            $chartjss[] = ["annee"=>"$produit->name"." ".$campagne, "color"=> "#".$produit->color, "data"=>$data];
        }

        $campagne = "2025";
        foreach($produits as $produit){
            $culture = $produit->label;
            $cotations = $em->getRepository(Cotation::class)->getAllProduitAndCampagne($culture, $campagne);
            $data = [];
            foreach ($cotations as $cotation) {
                $data[] = ["date"=>$cotation->date->format('d/m/y'), "value"=>$cotation->value];
            }
            $chartjss[] = ["annee"=>"$produit->name"." ".$campagne, "color"=> "#".$produit->color, "data"=>$data];
        }
        return $chartjss;
    }

    #[Route(path: '/cotation_home', name: 'cotation_home')]
    public function cotationHomeAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $produits = $em->getRepository(CotationProduit::class)->findByCategorie("cereales");
        
        $chartjss = $this->updatecharjss($produits);

        $produits = $em->getRepository(CotationProduit::class)->findByCategorie("oleagineux");
        $chartjss_o = $this->updatecharjss($produits);

        $produits = $em->getRepository(CotationProduit::class)->findByCategorie("autre");
        $chartjss_ot = $this->updatecharjss($produits);

        $produits = $em->getRepository(CotationProduit::class)->findByCategorie("c2");
        $chartjss_c2 = $this->updatecharjss($produits);

        $cotations = $em->getRepository(Cotation::class)->getLasts();
        return $this->render('Cotation/home.html.twig', array(
            'cotations' => $cotations,
            'chartjss' => $chartjss,
            'chartjss_o' => $chartjss_o,
            'chartjss_ot' => $chartjss_ot,
            'chartjss_c2' => $chartjss_c2,
        ));
    }


    #[Route(path: '/cotations/produits', name: 'cotation_produits')]
    public function cotationProduitAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $produits = $em->getRepository(CotationProduit::class)->findAll();
        return $this->render('Cotation/produits.html.twig', array(
            'produits' => $produits,
        ));
    }


    #[Route(path: '/cotation_produit/{id}', name: 'cotation_produit')]
    public function cotationProduitEditAction($id, Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();
        if($id == '0'){
            $produit = new CotationProduit();
        } else {
            $produit = $em->getRepository(CotationProduit::class)->find($id);
        }
        $form = $this->createForm(CotationProduitType::class, $produit);
        $form->handleRequest($request);


        if ($form->isSubmitted()) {
            $em->persist($produit);
            $em->flush();
            return $this->redirectToRoute('cotation_produits');
        }
        return $this->render('base_form.html.twig', array(
            'form' => $form->createView(),
        ));
    }


    #[Route(path: '/cotations_all', name: 'cotations_all')]
    public function cotationAllAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $cotations = $em->getRepository(Cotation::class)->getAlls();

        return $this->render('Cotation/cotations.html.twig', array(
            'cotations' => $cotations
        ));
    }

    #[Route(path: '/cotation/{id}', name: 'cotation')]
    public function cotationEditAction($id, Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();
        if($id == '0'){
            $cotation = new Cotation();
            $cotation->date = new \DateTime();
        } else {
            $cotation = $em->getRepository(Cotation::class)->find($id);
        }
        $form = $this->createForm(CotationType::class, $cotation);
        $form->handleRequest($request);


        if ($form->isSubmitted()) {
            $em->persist($cotation);
            if($cotation->produit){
                $cotation->produit_str = $cotation->produit->label;
            }
            $em->flush();
            return $this->redirectToRoute('cotations_all');
        }
        return $this->render('base_form.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    #[Route(path: '/cotation_add', name: 'cotation_add')]
    public function cotationAddEditAction(Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(CotationAddType::class);
        $form->handleRequest($request);


        if ($form->isSubmitted()) {

            if($form["value1"]){
                $cotation = new Cotation();
                $cotation->source = $form->get('source')->getData();
                $cotation->campagne = $form->get('campagne')->getData();
                $cotation->date = $form->get('date')->getData();
                $cotation->produit = $form->get('produit1')->getData();; 
                $cotation->value = $form->get('value1')->getData();; 
                $cotation->produit_str = $cotation->produit->label;
                $em->persist($cotation);
                $em->flush();
            }

            if($form->get('value2')->getData()){
                $cotation = new Cotation();
                $cotation->source = $form->get('source')->getData();
                $cotation->campagne = $form->get('campagne')->getData();
                $cotation->date = $form->get('date')->getData();
                $cotation->produit = $form->get('produit2')->getData();; 
                $cotation->value = $form->get('value2')->getData();; 
                $cotation->produit_str = $cotation->produit->label;
                $em->persist($cotation);
                $em->flush();
            }

            if($form->get('value3')->getData()){
                $cotation = new Cotation();
                $cotation->source = $form->get('source')->getData();
                $cotation->campagne = $form->get('campagne')->getData();
                $cotation->date = $form->get('date')->getData();
                $cotation->produit = $form->get('produit3')->getData();; 
                $cotation->value = $form->get('value3')->getData();; 
                $cotation->produit_str = $cotation->produit->label;
                $em->persist($cotation);
                $em->flush();
            }

            if($form->get('value4')->getData()){
                $cotation = new Cotation();
                $cotation->source = $form->get('source')->getData();
                $cotation->campagne = $form->get('campagne')->getData();
                $cotation->date = $form->get('date')->getData();
                $cotation->produit = $form->get('produit4')->getData();; 
                $cotation->value = $form->get('value4')->getData();; 
                $cotation->produit_str = $cotation->produit->label;
                $em->persist($cotation);
                $em->flush();
            }

            if($form->get('value5')->getData()){
                $cotation = new Cotation();
                $cotation->source = $form->get('source')->getData();
                $cotation->campagne = $form->get('campagne')->getData();
                $cotation->date = $form->get('date')->getData();
                $cotation->produit = $form->get('produit5')->getData();; 
                $cotation->value = $form->get('value5')->getData();; 
                $cotation->produit_str = $cotation->produit->label;
                $em->persist($cotation);
                $em->flush();
            }
            
            return $this->redirectToRoute('cotations_all');
        }
        return $this->render('base_form.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    #[Route(path: '/cotation/{id}/delete', name: 'cotation_delete')]
    public function deleteAction($id, Request $request)
    {

        $em = $this->getDoctrine()->getManager();
        $cotation= $em->getRepository(Cotation::class)->find($id);

        $em->remove($cotation);
        $em->flush();

        return $this->redirectToRoute('cotations_all');
    }

    #[Route(path: '/cotations_hist', name: 'cotations_hist')]
    public function cotationHistAction(Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();
        $campagne = $this->getCurrentCampagne($request);

        $rep = $em->getRepository(Cotation::class);
        

        $em = $this->getDoctrine()->getManager();

        $RAW_QUERY = "SELECT distinct c.produit FROM cotation c WHERE c.date >='2021-12-01 00:00:00';";
        
        $statement = $em->getConnection()->prepare($RAW_QUERY);
        $resultSet = $statement->executeQuery();
        $result = $resultSet->fetchAllAssociative();

        $data = []; 
        
        $chartjss = [];
        foreach($result as $r){
            $culture = $r["produit"];
            $cotations = $em->getRepository(Cotation::class)->getAllProduit($culture);
            $data = [];
            foreach ($cotations as $cotation) {
                $data[] = ["date"=>$cotation->date->format('d/m/y'), "value"=>$cotation->value];
            }
            $chartjss[] = ["annee"=>"$culture", "color"=> "", "data"=>$data];
        }

        return $this->render('Gestion/commercialisations_bilan.html.twig', array(
            'campagnes' => $this->campagnes,
            'campagne_id' => $campagne->id,
            'cultures' => [],
            'total_obj' => [],
            'total_today' => [],
            'total_realise' => [],
            'chartjss' => $chartjss,
            'chartjss2' => []
        ));
    }

    #[Route(path: '/prix_moyens', name: 'prix_moyens')]
    public function prixMoyenAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $source = $request->query->get('source');
        

        $prix_moyens = $em->getRepository(PrixMoyen::class)->getAlls($source);

        return $this->render('Cotation/prix_moyens.html.twig', array(
            'prix_moyens' => $prix_moyens
        ));
    }

    #[Route(path: '/prix_moyen/{id}', name: 'prix_moyen')]
    public function prixMoyenEditAction($id, Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();
        if($id == '0'){
            $prix_moyen = new PrixMoyen();
        } else {
            $prix_moyen = $em->getRepository(PrixMoyen::class)->find($id);
        }
        $form = $this->createForm(PrixMoyenType::class, $prix_moyen);
        $form->handleRequest($request);


        if ($form->isSubmitted()) {
            $em->persist($prix_moyen);
            $em->flush();
            return $this->redirectToRoute('prix_moyens');
        }
        return $this->render('base_form.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    #[Route(path: '/cotation_produit/{id}/{campagne}', name: 'cotation_produit2')]
    public function produitEditAction($id, $campagne, Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();
        $produit = $em->getRepository(CotationProduit::class)->find($id);
        $culture = $produit->label;
        $cotations = $em->getRepository(Cotation::class)->getAllProduitAndCampagne($culture, $campagne);
        $prix_moyens = $em->getRepository(PrixMoyen::class)->getAllProduitAndCampagne($produit, $campagne);

        dump($produit);
        dump($prix_moyens);
        $data = [];
        foreach ($cotations as $cotation) {
            $data[] = ["date"=>$cotation->date->format('d/m/y'), "value"=>$cotation->value];
        }
        $chartjss[] = ["annee"=>"$produit->name"." ".$campagne, "color"=> "#".$produit->color, "data"=>$data];
        
        return $this->render('Cotation/produit_bilan.html.twig', array(
            'cotations' => $cotations,
            'prix_moyens' => $prix_moyens,
            'chartjss' => $chartjss
        ));
    }

    #[Route(path: '/cotation/bilan/{campagne}', name: 'bilan_cotation')]
    public function bilanAction($campagne, Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();
        $produits = $em->getRepository(CotationProduit::class)->findAll();
        $sources_c = [];
        $sources_m = [];
        $ress = [];
        foreach($produits as $produit){
            $cotations = $em->getRepository(Cotation::class)->getAllProduitAndCampagne($produit->label, $campagne);
            $res = [];
            $res["name"] = $produit->name;
            //dump($cotations);
            foreach($cotations as $c){
                if(!in_array($c->source,$sources_c)){
                    $sources_c[] = $c->source;
                }
            }

            foreach($cotations as $c){
                if(!array_key_exists($c->source."_sum",$res)){
                    $res[$c->source."_sum"] = $c->value;
                    $res[$c->source."_moy"] = $c->value;
                    $res[$c->source."_count"] = 1;
                    $res[$c->source."_max"] = $c->value;
                    $res[$c->source."_min"] = $c->value;
                } else {
                    $res[$c->source."_sum"] = $res[$c->source."_sum"] + $c->value;
                    $res[$c->source."_count"] = $res[$c->source."_count"]+1;
                    $res[$c->source."_moy"] = $res[$c->source."_sum"]/$res[$c->source."_count"];
                    if($c->value > $res[$c->source."_max"]){
                        $res[$c->source."_max"] = $c->value;
                    }
                    if($c->value < $res[$c->source."_min"]){
                        $res[$c->source."_min"] = $c->value;
                    }
                }
            }

            $prix_moyens = $em->getRepository(PrixMoyen::class)->getAllProduitAndCampagne($produit, $campagne);
            foreach($prix_moyens as $c){
                if(!in_array($c->source,$sources_m)){
                    $sources_m[] = $c->source;
                }
                $res[$c->source] = $c->getPrixTotal();
            }


            $ress[] = $res;
        }

        $ress2 = [];
        foreach($ress as $res){
            foreach($sources_c as $source){
                if(!array_key_exists($source."_min",$res)){
                    $res[$source."_sum"] = 0;
                    $res[$source."_moy"] = 0;
                    $res[$source."_count"] = 1;
                    $res[$source."_max"] = 0;
                    $res[$source."_min"] = 0;
                }
            }

            foreach($sources_m as $source){
                if(!array_key_exists($source,$res)){
                    $res[$source] = 0;
                }
            }
            $ress2[] = $res;
        }

        $data = [];
        foreach ($cotations as $cotation) {
            $data[] = ["date"=>$cotation->date->format('d/m/y'), "value"=>$cotation->value];
        }
        $chartjss[] = ["annee"=>"$produit->name"." ".$campagne, "color"=> "#".$produit->color, "data"=>$data];
        
        return $this->render('Cotation/cotation_bilan.html.twig', array(
            'sources_c' => $sources_c,
            'sources_m' => $sources_m,
            'ress' => $ress2
        ));
    }

}
