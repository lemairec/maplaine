<?php

namespace App\Controller\Gestion;

use Symfony\Component\Routing\Annotation\Route;
use App\Controller\CommonController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use DateTime;

use App\Entity\Campagne;

use App\Entity\Gestion\Compte;
use App\Entity\Gestion\Ecriture;
use App\Entity\Gestion\Operation;
use App\Entity\Gestion\FactureFournisseur;
use App\Entity\Gestion\Cours;

use App\Form\Gestion\CompteType;
use App\Form\Gestion\EcritureType;
use App\Form\Gestion\OperationType;
use App\Form\Gestion\FactureFournisseurType;
use Symfony\Component\HttpFoundation\File\File;

//COMPTE
//ECRITURE
//OPERATION
function array_sort($array, $on, $order=SORT_ASC)
{
    $new_array = array();
    $sortable_array = array();

    if (count($array) > 0) {
        foreach ($array as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $k2 => $v2) {
                    if ($k2 == $on) {
                        $sortable_array[$k] = $v2;
                    }
                }
            } else {
                $sortable_array[$k] = $v;
            }
        }

        switch ($order) {
            case SORT_ASC:
                asort($sortable_array);
            break;
            case SORT_DESC:
                arsort($sortable_array);
            break;
        }

        foreach ($sortable_array as $k => $v) {
            $new_array[$k] = $array[$k];
        }
    }

    return $new_array;
}

class GestionController extends CommonController
{
    public function getColor(){
        return ["#99ccff", "#ff9966", "#B6E17B", ""];
    }

    public function getDateColor(){
        return ["" => "", "2014" => "", "2015" => "", "2016" => "#99ccff", "2017" => "#ff9966", "2018" => "#B6E17B", "2019"=> "#00909e", "2020"=> "#fcba03", "2021"=> "#99ccff", "2022"=> "#ff9966", "2023" => "#B6E17B", "2024"=> "#00909e", "2025"=> "#fcba03", "2026"=> "#99ccff"];
    }

    public function getHidden(){
        return ["" => false, "2014" => true, "2015" => true, "2016" => true, "2017" => true, "2018" => true, "2019"=> true, "2020"=> true, "2021"=> true, "2022"=> false, "2023"=> false, "2024"=> false, "2025"=> false, "2026"=> false];
    }

    public function getDataCampagne($campagne, $ecritures){
        $data = [];

        $value = 0;
        foreach($ecritures as $ecriture){
            $year = intVal($ecriture['date']->format("Y"))-intVal($campagne)+2017;
            $value += $ecriture['value'];
            $data[] = ['date' => $ecriture['date']->format("d-m")."-".$year, 'value' => $value, 'name' => $ecriture['name']];
        }
        return ['annee'=> $campagne, 'data' => $data, 'color' => $this->getDateColor()[$campagne], 'hidden' => $this->getHidden()[$campagne]];
    }

    public function getDataWithDates($ecritures){
        $chartjss = [];
        $chartjs = NULL;
        $year = 0;

        foreach($ecritures as $ecriture){
            $new_year = $ecriture['date']->format('Y');
            if($year != $new_year){
                $year = $new_year;
                if($chartjs){
                    $chartjss[] = $chartjs;
                }
                $chartjs = ['annee'=> $year, 'data' => [], 'color' => $this->getDateColor()[$year], 'hidden' => $this->getHidden()[$year]];
            }

            $chartjs['data'][] = ['date' => $ecriture['date']->format("d-m")."-2017", 'value' => $ecriture['sum_value'], 'name' => $ecriture['name'] ];
        }
        $chartjss[] = $chartjs;
        return $chartjss;
    }

    public function getDataWithoutDates($ecritures){
       $chartjss = [];
        $chartjs = NULL;
        $year = 0;

        foreach($ecritures as $ecriture){
            $new_year = 2025;
            if($year != $new_year){
                $year = $new_year;
                if($chartjs){
                    $chartjss[] = $chartjs;
                }
                $chartjs = ['annee'=> $year, 'data' => [], 'color' => $this->getDateColor()[$year], 'hidden' => $this->getHidden()[$year]];
            }

            $chartjs['data'][] = ['date' => $ecriture['date']->format("d-m-Y"), 'value' => $ecriture['sum_value'], 'name' => $ecriture['name'] ];
        }
        $chartjss[] = $chartjs;
        return $chartjss;
    }

    public function getDataSerieChartJs($ecritures, $name, $i){
        $chartjs = ['annee'=> $name, 'data' => [], 'color' => $this->getColor()[$i]];
        $value=0;
        foreach($ecritures as $ecriture){
            $value += $ecriture['value'];
            $chartjs['data'][] = ['date' => $ecriture['date']->format("d-m-Y"), 'value' => $ecriture['sum_value'], 'name' => $ecriture['name']  ];
        }
        return $chartjs;
    }

    #[Route(path: '/cours', name: 'cours')]
    public function coursAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $campagne = $this->getCurrentCampagne($request);

        $courss = $em->getRepository(Cours::class)->findAll();

        $produits = [];
        foreach($courss as $c){
            $produit = $c->produit;
            $value = $c->value;
            if (!array_key_exists($produit, $produits)) {
                $produits[$produit] = ['name'=>$produit,'last' => $value,  'min' => $value, 'max' => $value];
            }
            $produits[$produit]['min'] = min($produits[$produit]['min'], $value);
            $produits[$produit]['max'] = max($produits[$produit]['max'], $value);
        }
        $courss = $em->getRepository(Cours::class)->getAllForCampagne($campagne);

        return $this->render('Gestion/cours.html.twig', array(
            'campagnes' => $this->campagnes,
            'campagne_id' => $campagne->id,
            'courss' => $courss,
            'produits' => $produits
        ));
    }

    #[Route(path: '/cours/0', name: 'cours_9')]
    public function coursNew0Action(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $campagne = $this->check_user($request);

        $labels = ["soja_ab", "soja_c2", "mais_ab", "ble_ab", "ble_c2"];
        $courss = [];
        foreach($labels as $l){
            $courss[] = ['name'=>$l, 'value'=>0];
        }
        $courss = $em->getRepository(Cours::class)->setArray($this->company, $courss);
        if ($request->getMethod() == 'POST') {
            $em->getRepository(Cours::class)->saveArray($this->company, $request->request->all());
            return $this->redirectToRoute('cours');
        }
        $date = new DateTime();
        return $this->render('Gestion/cours_new.html.twig', array(
            'date' => $date->format("d/m/Y"),
            'courss' => $courss,
        ));
    }

    #[Route(path: '/cours/new', name: 'cours_new')]
    public function coursNewAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $campagne = $this->check_user($request);

        $labels = ["soja_ab", "soja_c2", "mais_ab", "ble_ab", "ble_c2"];
        $courss = [];
        foreach($labels as $l){
            $courss[] = ['name'=>$l, 'value'=>0];
        }
        $courss = $em->getRepository(Cours::class)->setArray($this->company, $courss);
        if ($request->getMethod() == 'POST') {
            $em->getRepository(Cours::class)->saveArray($this->company, $request->request->all());
            return $this->redirectToRoute('cours');
        }
        $date = new DateTime();
        return $this->render('Gestion/cours_new.html.twig', array(
            'date' => $date->format("d/m/Y"),
            'courss' => $courss,
        ));
    }

    #[Route(path: '/comptes2', name: 'comptes2')]
    public function comptes2Action(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $c = $this->getCurrentCampagne($request);

        $comptes = $em->getRepository(Compte::class)->getAllForCompany($this->company);

        $comptes_campagnes = [];
        foreach ($this->campagnes as $campagne) {
            $res = 0;
            foreach ($comptes as $compte) {
                if ($compte->type == 'campagne' || $compte->getPriceCampagne($campagne) != 0){
                    $res = $res + $compte->getPriceCampagne($campagne);
                }
            }
            $comptes_campagnes[$campagne->name] = $res;
        }

        return $this->render('Gestion/comptes.html.twig', array(
            'campagnes' => $this->campagnes,
            'campagne_id' => $campagne->id,
            'comptes' => $comptes,
            'comptes_campagnes' => $comptes_campagnes
        ));
    }

    #[Route(path: '/comptes', name: 'comptes')]
    public function comptesAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $c = $this->getCurrentCampagne($request);

        $comptes = $em->getRepository(Compte::class)->getAllForCompany($this->company);

        $comptes_campagnes = [];
        foreach ($this->campagnes as $campagne) {
            $res = 0;
            foreach ($comptes as $compte) {
                if ($compte->type == 'campagne' || $compte->getPriceCampagne($campagne) != 0){
                    $res = $res + $compte->getPriceCampagne($campagne);
                }
            }
            $comptes_campagnes[$campagne->name] = $res;
        }

        return $this->render('Gestion/comptes.html.twig', array(
            'campagnes' => $this->campagnes,
            'campagne_id' => $campagne->id,
            'comptes' => $comptes,
            'comptes_campagnes' => $comptes_campagnes
        ));
    }

    #[Route(path: '/bilan_comptes', name: 'bilan_comptes')]
    public function bilanCompteAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $c = $this->getCurrentCampagne($request);

        $campagnes2 = [];
        foreach($this->companies as $company){
            $campagnes = $em->getRepository(Campagne::class)->getAllForCompany($company);
            foreach ($this->campagnes as $campagne) {
                $campagnes2[] = $campagne->name." ".$company->name;
            }
            $campagnes2[] = "solde ".$company->name;
            $campagnes2[] = "prev ".$company->name;
        }

        $campagnes = rsort($campagnes2);

        $comptes_campagnes = [];
        foreach($this->companies as $company){
            $comptes = $em->getRepository(Compte::class)->getAllForCompany($company);

            foreach($comptes as $compte){
                if (!array_key_exists($compte->identifiant, $comptes_campagnes)) {
                    $comptes_campagnes[$compte->identifiant] = ["identifiant"=>$compte->identifiant, "name"=>$compte->label];
                    foreach ($campagnes2 as $campagne) {
                        $comptes_campagnes[$compte->identifiant][$campagne] = 0;
                    }
                }
                $comptes_campagnes[$compte->identifiant]["prev ".$company->name] = $compte->previsionnel;
                $comptes_campagnes[$compte->identifiant]["solde ".$company->name] = $compte->getPrice();
                $campagnes = $em->getRepository(Campagne::class)->getAllForCompany($company);
                foreach ($this->campagnes as $campagne) {
                    $comptes_campagnes[$compte->identifiant][$campagne->name." ".$company->name] = $compte->getPriceCampagne($campagne);
                }
            }
        }

        $comptes_campagnes2 = [];
        foreach($comptes_campagnes as $c){
            $comptes_campagnes2[] = $c;
        }

        $comptes_campagnes2 = array_sort($comptes_campagnes2, 'identifiant', SORT_ASC);

        return $this->render('Gestion/bilan2_comptes.html.twig', array(
            'campagnes2' => $campagnes2,
            'comptes_campagnes' => $comptes_campagnes2
        ));
    }

    #[Route(path: '/comptes_by_year', name: 'comptes_by_year')]
    public function comptesByYearAction(Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();

        $operations = $em->getRepository(Operation::class)->findByCompany($this->company);
        $comptes = $em->getRepository(Compte::class)->getAllForCompany($this->company);
        $years = ['2022','2021','2020', '2019', '2018','2017', '2016', '2015', '2014'];


        //dump($comptes);

        $res2 = [];
        foreach($comptes as $c){
            $compte = strval($c);
            $res2[$compte] = [];
            foreach($years as $y){
                $res2[$compte][$y] = 0;
            }
        }


        foreach ($operations as $o) {
            $year = $o->date->format('Y');
            foreach($o->ecritures as $e){
                $compte = strval($e->compte);
                $res2[$compte][$year] += $e->value;
            }
        }

        return $this->render('Gestion/comptes_by_year.html.twig', array(
            'res' => $res2,
            'years' => $years,
            'comptes' => $comptes
        ));
    }

    #[Route(path: '/bilan_banque', name: 'bilan_banque')]
    public function banqueEditAction(Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();
        $session = $request->getSession();

        $operations = [];
        $ecritures = [];
        $ecritures_futures = [];
        {
            $operations = $em->getRepository(Operation::class)->getAllForCompany($this->company);
            $value = 0;
            $l = count($operations);
            for($i = 0; $i < $l; ++$i){
                $operation = $operations[$l-$i-1];
                foreach($operation->ecritures as $e){
                    if($e->compte->type == "banque"){
                        $ecriture = ['operation_id'=>$operation->id,'date'=>$operation->date, 'name'=>$operation->name, 'value'=>-$e->value];
                        $ecriture['campagne'] = "";
                        $ecriture['facture'] = $operation->facture;
                        if($e->campagne){
                            $ecriture['campagne'] = $e->campagne->name;
                        }

                        $value += $ecriture['value'];
                        $ecriture['sum_value'] = $value;

                        $ecritures[] = $ecriture;
                    }

                }

            }
        }

        $chartjss = $this->getDataWithDates($ecritures);
        $ecritures = array_reverse($ecritures);

        $chartjss = array_reverse($chartjss);

        return $this->render('Gestion/banques.html.twig', array(
            'ecritures' => $ecritures,
            'chartjss' => $chartjss
        ));
    }

    #[Route(path: '/bilan_emprunt', name: 'bilan_emprunt')]
    public function empruntEditAction(Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();
        $session = $request->getSession();

        $operations = [];
        $ecritures = [];
        $ecritures_futures = [];
        {
            $operations = $em->getRepository(Operation::class)->getAllForCompany($this->company);
            //$operations = $em->getRepository(Operation::class)->getAllDesc();
            $value = 0;
            $l = count($operations);
            for($i = 0; $i < $l; ++$i){
                $operation = $operations[$l-$i-1];
                foreach($operation->ecritures as $e){
                    if($e->compte->type == "emprunt"){
                        $ecriture = ['operation_id'=>$operation->id,'date'=>$operation->date, 'name'=>$operation->name, 'value'=>-$e->value];
                        $ecriture['campagne'] = "";
                        $ecriture['facture'] = $operation->facture;
                        if($e->campagne){
                            $ecriture['campagne'] = $e->campagne->name;
                        }

                        $value += $ecriture['value'];
                        $ecriture['sum_value'] = $value;

                        $ecritures[] = $ecriture;
                    }

                }

            }
        }

        $chartjss = $this->getDataWithoutDates($ecritures);
        $ecritures = array_reverse($ecritures);

        $chartjss = array_reverse($chartjss);

        return $this->render('Gestion/banques.html.twig', array(
            'ecritures' => $ecritures,
            'chartjss' => $chartjss
        ));
    }


    #[Route(path: '/compte/{compte_id}', name: 'compte')]
    public function compteEditAction($compte_id, Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();
        $session = $request->getSession();

        $operations = [];
        $ecritures_by_campagne = [];
        $ecritures = [];
        $ecritures_futures = [];
        if($compte_id == '0'){
            $compte = new Compte();
            $compte->company = $this->company;
        } else {
            $compte = $em->getRepository(Compte::class)->findOneById($compte_id);
            $operations = $em->getRepository(Operation::class)->getAllForCompte($compte);
            $value = 0;
            $l = count($operations);
            for($i = 0; $i < $l; ++$i){
                $operation = $operations[$i];
                foreach($operation->ecritures as $e){
                    if($e->compte == $compte){
                        $campagne = "";
                        if($e->campagne){
                            $campagne = $e->campagne->name;
                        }
                        $ecriture = ['operation_id'=>$operation->id,'date'=>$operation->date, 'name'=>$operation->name, 'value'=>$e->value, 'campagne'=>$campagne, "i"=>$i];
                        $ecriture['facture'] = $operation->facture;

                        if($compte->type == 'banque'){
                            $ecriture['value'] = -$ecriture['value'];
                        }

                        $value += $ecriture['value'];
                        $ecriture['sum_value'] = $value;

                        $ecritures[] = $ecriture;
                        if(!array_key_exists($campagne, $ecritures_by_campagne)){
                            $ecritures_by_campagne[$campagne] = ["value" => 0, "ecritures" => []];
                        }
                        $ecritures_by_campagne[$campagne]["ecritures"][] = $ecriture;
                        $ecritures_by_campagne[$campagne]["value"] += $ecriture['value'];
                    }

                }

            }
        }
        $form = $this->createForm(CompteType::class, $compte);
        $form->handleRequest($request);

        $chartjss = [];
        $chartjs = NULL;
        $year = 0;
        $value = 0;
        if(array_key_exists("", $ecritures_by_campagne)){
            $ecritures2 = $ecritures_by_campagne[""]["ecritures"];
            foreach($ecritures_by_campagne as $k => $value){
                if($k != ""){
                    foreach($value["ecritures"] as $e){
                        $ecritures2[] = $e;
                        //dump("toto");
                    }
                }
            }
            $ecritures2 = array_sort($ecritures2, "i");
            $chartjss = $this->getDataWithDates($ecritures2);
        } else {
            foreach($ecritures_by_campagne as $k => $value){
                $chartjss[] = $this->getDataCampagne($k, $value["ecritures"]);
            }
        }

        $chartjss = array_reverse($chartjss);

        $ecritures = array_reverse($ecritures);
        $ecritures_futures = array_reverse($ecritures_futures);

        if ($form->isSubmitted()) {
            $em->persist($compte);
            $em->flush();
            return $this->redirectToRoute('comptes');
        }

        $session->set("redirect_url_facture", $this->generateUrl('compte', array('compte_id' => $compte_id)));
        return $this->render('Gestion/compte.html.twig', array(
            'form' => $form->createView(),
            'compte' => $compte,
            'compte_id' => $compte->id,
            'ecritures' => $ecritures,
            'ecritures_futures' => $ecritures_futures,
            'chartjss' => $chartjss
        ));
    }

    #[Route(path: '/compte/{compte_id}/by_tag', name: 'compte_by_tag')]
    public function compteTagAction($compte_id, Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();
        $session = $request->getSession();

        $operations = [];
        $ecritures_by_campagne_by_tag = [];
        $ecritures = [];
        $ecritures_futures = [];
        if($compte_id == '0'){
            return;
        } else {
            $compte = $em->getRepository(Compte::class)->findOneById($compte_id);
            $factures = $em->getRepository(FactureFournisseur::class)->findByCompte($compte);
            $value = 0;
            foreach($factures as $f){
                $campagne = "";
                if($f->campagne){
                    $campagne = $f->campagne->name;
                }
                $str = $campagne." - ".$f->tag;
                if(!array_key_exists($str, $ecritures_by_campagne_by_tag)){
                    $ecritures_by_campagne_by_tag[$str] = [ "value" => $str, "sum" => 0, "facture" => []];
                }
                $ecritures_by_campagne_by_tag[$str]["facture"][] = $f;
                $ecritures_by_campagne_by_tag[$str]["sum"] += $f->montantHT;

            }
        }

        //dump($ecritures_by_campagne_by_tag);
        sort($ecritures_by_campagne_by_tag);
        return $this->render('Gestion/compte_by_tag.html.twig', array(
            'res' => $ecritures_by_campagne_by_tag,
            'compte' => $compte
        ));
    }

    #[Route(path: '/compte/{compte_id}/by_tag2', name: 'compte_by_tag2')]
    public function compteTagAction2($compte_id, Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();
        $session = $request->getSession();

        $operations = [];
        $ecritures_by_campagne_by_tag = [];
        $ecritures_by_campagne = [];
        $ecritures = [];
        $tags = [];
        $ecritures_futures = [];
        if($compte_id == '0'){
            return;
        } else {
            $compte = $em->getRepository(Compte::class)->findOneById($compte_id);
            $factures = $em->getRepository(FactureFournisseur::class)->findByCompte($compte);
            $value = 0;

            foreach($factures as $f){
                $tag = $f->tag;
                if(!in_array($tag, $tags)){
                    $tags[] = $tag;
                }
            }


            foreach($factures as $f){
                $campagne = "";
                if($f->campagne){
                    $campagne = $f->campagne->name;
                }
                $str = $campagne." - ".$f->tag;
                if(!array_key_exists($str, $ecritures_by_campagne_by_tag)){
                    $ecritures_by_campagne_by_tag[$str] = [ "value" => $str, "sum" => 0, "facture" => []];
                }
                $ecritures_by_campagne_by_tag[$str]["facture"][] = $f;
                $ecritures_by_campagne_by_tag[$str]["sum"] += $f->montantHT;
                if(!array_key_exists($campagne, $ecritures_by_campagne)){
                    $ecritures_by_campagne[$campagne] = [ "campagne" => $campagne, "tags" => []];
                    foreach($tags as $t){
                        $ecritures_by_campagne[$campagne]["tags"][$t] = 0;
                    }
                }
                $ecritures_by_campagne[$campagne]["tags"][$f->tag] += $f->montantHT;

            }
        }

        sort($ecritures_by_campagne);
        sort($ecritures_by_campagne_by_tag);
        return $this->render('Gestion/compte_by_tag2.html.twig', array(
            'res' => $ecritures_by_campagne_by_tag,
            'res2' => $ecritures_by_campagne,
            'tags' => $tags,
            'compte' => $compte
        ));
    }

    #[Route(path: '/operations', name: 'operations')]
    public function operationsAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $campagne = $this->getCurrentCampagne($request);

        $operations = $em->getRepository(Operation::class)->getAllForCompany($this->company);

        return $this->render('Gestion/operations.html.twig', array(
            'campagnes' => $this->campagnes,
            'campagne_id' => $campagne->id,
            'operations' => $operations,
        ));
    }

    #[Route(path: '/operation/{operation_id}', name: 'operation')]
    public function operationEditAction($operation_id, Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();
        if($operation_id == '0'){
            $operation = new Operation();
            $operation->name = "new";
            $operation->date = new Datetime();
            $operation->company = $this->company;
            $operation->ecritures = [];
            $em->persist($operation);
            $em->flush();
        } else {
            $operation = $em->getRepository(Operation::class)->findOneById($operation_id);
            if($operation->facture){
                return $this->redirectToRoute('facture_fournisseur', array('facture_id' => $operation->facture->id));
            }
        }
        $form = $this->createForm(OperationType::class, $operation);
        $form->handleRequest($request);


        if ($form->isSubmitted()) {
            $em->persist($operation);
            $em->flush();
            return $this->redirectToRoute('operations');
        }
        return $this->render('Gestion/operation.html.twig', array(
            'form' => $form->createView(),
            'operation' => $operation,
            'parcelles' => []
        ));
    }

    #[Route(path: '/operation/{operation_id}/delete', name: 'operation_delete')]
    public function operationDeleteAction($operation_id, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $em->getRepository(Operation::class)->delete($operation_id);
        return $this->redirectToRoute('operations');
    }

    #[Route(path: '/operation/{operation_id}/ecriture/{ecriture_id}', name: 'ecriture')]
    public function ecritureEditAction($operation_id, $ecriture_id, Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();
        if($ecriture_id == '0'){
            $ecriture = new Ecriture();
            $ecriture->operation = $em->getRepository(Operation::class)->findOneById($operation_id);;
        } else {
            $ecriture = $em->getRepository(Ecriture::class)->findOneById($ecriture_id);
        }
        $campagnes = $em->getRepository(Campagne::class)->getAllAndNullforCompany($this->company);
        $form = $this->createForm(EcritureType::class, $ecriture, array(
            'comptes' => $em->getRepository(Compte::class)->getAllForCompany($this->company),
            'campagnes' => $campagnes
        ));
        $form->handleRequest($request);


        if ($form->isSubmitted()) {
            $em->persist($ecriture);
            $em->flush();
            return $this->redirectToRoute('operation', array('operation_id' => $operation_id));
        }
        return $this->render('base_form.html.twig', array(
            'form' => $form->createView()
        ));
    }

    #[Route(path: '/facture_fournisseurs', name: 'factures_fournisseurs')]
    public function factureFournisseursAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $this->check_user($request);
        $facture_fournisseurs = $em->getRepository(FactureFournisseur::class)->getAllForCompany($this->company);

        return $this->render('Gestion/facture_fournisseurs.html.twig', array(
            'facture_fournisseurs' => $facture_fournisseurs
        ));
    }

    #[Route(path: '/facture_fournisseur/{facture_id}/delete_pdf', name: 'facture_fournisseur_delete_pdf')]
    public function factureFournisseurDeletePdfAction($facture_id, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $facture = $em->getRepository(FactureFournisseur::class)->findOneById($facture_id);
        $facture->factureFile = null;
        $em->getRepository(FactureFournisseur::class)->save($facture);
        return $this->redirectToRoute('facture_fournisseur', array('facture_id' => $facture_id));
    }

    #[Route(path: '/facture_fournisseur/{facture_id}', name: 'facture_fournisseur')]
    public function factureFournisseurAction($facture_id, Request $request)
    {
        $this->check_user($request);
        $session = $request->getSession();

        $em = $this->getDoctrine()->getManager();
        $operations = [];
        if($facture_id == '0'){
            $facture = new FactureFournisseur();
            $facture->company = $this->company;
            $facture->date = new Datetime();
            $facture->paiementDate = new Datetime();
            $facture->paiementOrder = 0;
        } else {
            $facture = $em->getRepository(FactureFournisseur::class)->findOneById($facture_id);
            $operations = $em->getRepository(Operation::class)->getForFacture($facture);
        }
        if($facture == NULL){
            return $this->redirectToRoute('factures_fournisseurs');
        }
        if($facture->type == "V"){
            $facture->type = "Vente";
            $facture->montantTTC = -$facture->montantTTC;
            $facture->montantHT = -$facture->montantHT;
        } else {
            $facture->type = "Achat";
        }

        $campagnes = $em->getRepository(Campagne::class)->getAllAndNullforCompany($this->company);
        $banques = $em->getRepository(Compte::class)->getAllBanques($this->company);
        $comptes = $em->getRepository(Compte::class)->getNoBanques($this->company);
        $form = $this->createForm(FactureFournisseurType::class, $facture, array(
            'banques' => $banques,
            'comptes' => $comptes,
            'campagnes' => $campagnes
        ));
        $form->handleRequest($request);


        if ($form->isSubmitted()) {
            if($facture->type == "Vente"){
                $facture->type = "V";
                $facture->montantTTC = -$facture->montantTTC;
                $facture->montantHT = -$facture->montantHT;
            } else {
                $facture->type = "A";
            }
            $em->getRepository(FactureFournisseur::class)->save($facture);

            $redirect_url_facture = $session->get("redirect_url_facture");
            if($redirect_url_facture){
                return $this->redirect($redirect_url_facture);
            }
            return $this->redirectToRoute('factures_fournisseurs');
        }
        return $this->render('Gestion/facture_fournisseur.html.twig', array(
            'form' => $form->createView(),
            'facture' => $facture,
            'operations' => $operations
        ));
    }

    #[Route(path: '/facture_fournisseur/{facture_id}/delete', name: 'facture_fournisseur_delete')]
    public function factureFournisseurDeleteAction($facture_id, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $facture = $em->getRepository(FactureFournisseur::class)->findOneById($facture_id);
        $em->getRepository(FactureFournisseur::class)->delete($facture);
        return $this->redirectToRoute('factures_fournisseurs');
    }
}
