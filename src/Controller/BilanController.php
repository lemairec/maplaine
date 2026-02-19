<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

use Datetime;
use App\Entity\InterventionRecolte;
use App\Entity\Campagne;
use App\Entity\Intervention;
use App\Entity\Parcelle;

use App\Entity\Culture;


use App\Controller\CommonController;

use Dompdf\Dompdf;
use Dompdf\Options;

class BilanController extends CommonController
{
    #[Route(path: '/fiches_parcellaires', name: 'fiches_parcellaires')]
    public function ficheParcellairesAction(Request $request)
    {
        $campagne = $this->getCurrentCampagne($request);
        $parcelles = $this->getParcellesForFiches($campagne);

        $visibility = "visibility: hidden;";
        if($this->getUser()->getUsername() =="lejard"){
            $visibility = "";
        }
        return $this->render('Bilan/fiches_parcellaires.html.twig', array(
            'parcelles' => $parcelles,
            'campagnes' => $this->campagnes,
            'campagne_id' => $campagne->id,
            'visibility'=> $visibility

        ));
    }

    #[Route(path: '/fiches_parcellaires_pdf', name: 'fiches_parcellaires_pdf')]
    public function ficheParcellairesPdfAction(Request $request)
    {
        $campagne = $this->getCurrentCampagne($request);
        $parcelles = $this->getParcellesForFiches($campagne);

        $visibility = "visibility: hidden;";
        if($this->getUser()->getUsername() =="lejard"){
            $visibility = "";
        }
        $html = $this->renderView('Bilan/fiches_parcellaires_pdf.html.twig', array(
            'parcelles' => $parcelles,
            'campagne' => $campagne,
            'visibility'=> $visibility

        ));
        //return $this->render('Bilan/fiches_parcellaires_pdf.html.twig', ['parcelles' => $parcelles, 'campagne' => $campagne]);

        // Configure Dompdf according to your needs
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isHtml5ParserEnabled', true);
        $dompdf = new Dompdf($pdfOptions);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream("fiches_parcellaires.pdf", [
            "Attachment" => false
        ]);

        return new Response("ok");
    }

    #[Route(path: '/bilan_intervention_prix', name: 'bilan_intervention_prix')]
    public function ficheInterventionPrixAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $campagne = $this->getCurrentCampagne($request);
        $interventions = $em->getRepository(Intervention::class)->getAllForCampagne($campagne);

        $res = [];
        $sum = 0;
        foreach (array_reverse($interventions) as $it) {
            $price = $it->getPriceHa()*$it->surface;
            $sum += $price;
            $res[] = ["intervention" => $it, "prix" => $price, "sum" => $sum ];
        }
        $res = array_reverse($res);
        $visibility = "visibility: hidden;";
        if($this->getUser()->getUsername() =="lejard"){
            $visibility = "";
        }
        return $this->render('Bilan/bilan_intervention_prix.html.twig', array(
            'res' => $res,
            'campagnes' => $this->campagnes,
            'campagne_id' => $campagne->id
        ));
    }

    #[Route(path: '/bilan', name: 'bilan')]
    public function bilanAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $campagne = $this->getCurrentCampagne($request);

        $campagnes2 = [];

        foreach($this->campagnes as $campagne){
            $parcelles = $this->getParcellesForFiches($campagne);

            $cultures = [];
            foreach ($parcelles as $p) {
                if (!array_key_exists($p->getCultureName(), $cultures)) {
                    $cultures[$p->getCultureName()] = ['color'=> $p->getCultureColor(),'culture'=>$p->getCultureName(), 'culture'=>$p->getCultureName()
                        ,'surface'=>0, 'priceHa'=>0, 'rendement'=>0, 'poid_norme'=>0
                        ,'price_total' => 0];
                }

                $cultures[$p->getCultureName()]['surface'] += $p->surface;
                $cultures[$p->getCultureName()]['priceHa'] += $p->surface*$p->priceHa;
                $cultures[$p->getCultureName()]['poid_norme'] += $p->poid_norme;
            }

            $commercialisations = $em->getRepository('App:Commercialisation')->getAllForCampagne($campagne);

            foreach($commercialisations as $commercialisation){
                $cultures[strval($commercialisation->culture)]['price_total'] += $commercialisation->price_total;
            }

            $marge = 0;
            foreach ($cultures as $key => $value) {
                $cultures[$key]['margesHa'] = 0;
                $cultures[$key]['chargesHa'] = $cultures[$key]['priceHa']/$cultures[$key]['surface'];
                $cultures[$key]['rendementHa'] = $cultures[$key]['poid_norme']/$cultures[$key]['surface'];
                if($cultures[$key]['poid_norme'] != 0){
                    $cultures[$key]['price'] = $cultures[$key]['price_total']/$cultures[$key]['poid_norme'];
                    $cultures[$key]['margesHa'] = $cultures[$key]['rendementHa']*$cultures[$key]['price'] - $cultures[$key]['chargesHa'];
                } else {
                    $cultures[$key]['price'] = 0;
                }
                $marge += $cultures[$key]['price_total'] - $cultures[$key]['priceHa'];
            }

            $c = ['parcelles' => $parcelles, 'cultures'=> $cultures, 'campagne'=>$campagne, 'marge'=>$marge];
            $campagnes2[] = $c;


        }

        return $this->render('Bilan/bilan.html.twig', array(
            'campagnes2' => $campagnes2
        ));
    }


    #[Route(path: '/bilan_engrais', name: 'bilan_engrais')]
    public function bilanEngraisAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $campagne = $this->getCurrentCampagne($request);

        $cultures = [];

        foreach($this->campagnes as $campagne){
            $parcelles = $em->getRepository(Parcelle::class)->getAllForCampagneWithoutActive($campagne);

            foreach ($parcelles as $p) {
                if (!array_key_exists($p->getCultureName(), $cultures)) {
                    $cultures[$p->getCultureName()] = ['color'=> $p->getCultureColor(),'culture'=>$p->getCultureName(), 'culture'=>$p->getCultureName()
                        ,'campagne' => []];
                }

                if (!array_key_exists($campagne->name, $cultures[$p->getCultureName()]['campagne'])) {
                    $cultures[$p->getCultureName()]['campagne'][$campagne->name] = ['name' => $campagne->name, 'parcelles' => []];
                }

                if($p->id != '0'){
                    $p->interventions = $em->getRepository(Intervention::class)->getAllForParcelle($p, 'ASC');
                }
                $p->engrais_n = 0;
                $p->engrais_p = 0;
                $p->engrais_k = 0;
                $p->engrais_mg = 0;
                $p->engrais_so3 = 0;
                $p->priceHa = 0;
                $interventions = [];
                foreach($p->interventions as $it){
                    $n = 0;
                    $s = 0;
                    foreach($it->produits as $produit){
                        $p->engrais_n += $produit->getQuantityHa() * $produit->produit->getEngraisN();
                        $p->engrais_p += $produit->getQuantityHa() * $produit->produit->getEngraisP();
                        $p->engrais_k += $produit->getQuantityHa() * $produit->produit->getEngraisK();
                        $p->engrais_mg += $produit->getQuantityHa() * $produit->produit->getEngraisMg();
                        $p->engrais_so3 += $produit->getQuantityHa() * $produit->produit->getEngraisSO3();

                    }
                    if($n>5){
                        $interventions[] = ['date' => $it->datetime, 'n'=>$n, 's'=>$s];
                    }
                }
                $parcelle = ['interventions' => $interventions, 'name' => $p->completeName, 'n' => $p->engrais_n, 'p' => $p->engrais_p, 'k' => $p->engrais_k, 'mg' => $p->engrais_mg, 's' => $p->engrais_so3];

                $cultures[$p->getCultureName()]['campagne'][$campagne->name]['parcelles'][] = $parcelle;
            }

        }

        return $this->render('Bilan/bilan_engrais.html.twig', array(
            'cultures' => $cultures
        ));
    }

    #[Route(path: '/bilan_engrais2', name: 'bilan_engrais2')]
    public function bilanEngrais2Action(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $campagne = $this->getCurrentCampagne($request);

        $engrais = [];
        $produits = $em->getRepository(Produit::class)->getAllForCompany($this->company);
        foreach($produits as $p){

            $campagnes = [];

            $achats = $em->getRepository(Achat::class)->getAllForProduit($p);
            foreach($achats as $a){
                $c = $a->campagne->name;
                if(!array_key_exists($c,$campagnes)){
                    $campagnes[$c] = ["name"=> $c, "qty"=>0, "price"=>0, ];
                }
                $campagnes[$c]["qty"] += $a->qty;
                $campagnes[$c]["price"] += $a->price_total;

                if($campagnes[$c]["qty"] != 0){
                    $campagnes[$c]["price_qty"] = $campagnes[$c]["price"]/$campagnes[$c]["qty"];
                } else {
                    $campagnes[$c]["price_qty"] = 0;
                }

            }

            if($p->engrais_n != 0 || $p->engrais_p !=0 || $p->engrais_k !=0 || $p->engrais_mg !=0 || $p->engrais_so3 !=0){
                $engrais[] = ["name" => $p->name, "type" => $p->type, "bio" => $p->bio, "engrais_n" => $p->engrais_n
                , "engrais_p" => $p->engrais_p, "engrais_k" => $p->engrais_k, "engrais_mg" => $p->engrais_mg, "engrais_so3" => $p->engrais_so3
                , "engrais_mo" => $p->engrais_mo, "engrais_cn" => $p->engrais_cn
                , "campagnes" => $campagnes ];
            }

        }


        return $this->render('Bilan/bilan_engrais2.html.twig', array(
            'engrais' => $engrais
        ));
    }

    #[Route(path: '/bilan_charges', name: 'bilan_charges')]
    public function bilan2Action(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $campagne = $this->getCurrentCampagne($request);

        $cultures = [];

        $parcelles = $this->getParcellesForFiches($campagne);


        foreach ($parcelles as $p) {
            if (!array_key_exists($p->getCultureName(), $cultures)) {
                $cultures[$p->getCultureName()] = ['culture'=>$p->getCultureName()
                ,'surface'=>0, 'priceHa'=>0, 'rendement'=>0, 'poid_norme'=>0
                , 'color'=>$p->getCultureColor(), 'details'=>[]];
            }


            $p->interventions = [];
            if($p->id != '0'){
                $p->interventions = $em->getRepository(Intervention::class)->getAllForParcelle($p);
            }
            $p->details = [];
            foreach($p->interventions as $it){
                foreach($it->produits as $produit){
                    if (!array_key_exists($produit->produit->type, $p->details)) {
                        $p->details[$produit->produit->type] = 0;
                    }
                    $p->details[$produit->produit->type]  += $produit->getQuantityHa() * $produit->produit->price;
                    if (!array_key_exists($produit->produit->type, $cultures[$p->getCultureName()]['details'])) {
                        $cultures[$p->getCultureName()]['details'][$produit->produit->type] = 0;
                    }
                    $cultures[$p->getCultureName()]['details'][$produit->produit->type] += $produit->getQuantityHa() * $p->surface * $produit->produit->price;
                }
                ksort($p->details);
            }



            $cultures[$p->getCultureName()]['surface'] += $p->surface;
            $cultures[$p->getCultureName()]['priceHa'] += $p->surface*$p->priceHa;
            ksort($cultures[$p->getCultureName()]['details']);

            $cultures[$p->getCultureName()]['poid_norme'] += $p->poid_norme;
        }


        return $this->render('Bilan/bilan_charges.html.twig', array(
            'campagnes' => $this->campagnes,
            'campagne_id' => $campagne->id,
            'parcelles' => $parcelles,
            'cultures' => $cultures,
        ));
    }


    #[Route(path: '/bilan_dates', name: 'bilan_dates')]
    public function bilanDatesAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $campagnes2 = $em->getRepository(Campagne::class)->getAllForCompany($this->getCurrentCampagne($request)->company);
        $cultures = [];

        foreach($campagnes2 as $campagne){
            $interventions = $em->getRepository(Intervention::class)->getAllForCampagne($campagne);

            foreach ($interventions as $intervention) {
                $intervention_culture = "";
                foreach($intervention->parcelles as $it){
                    $p = $it->parcelle;
                    $culture = $p->getCultureName();
                    if($intervention_culture != $culture){
                        if (!array_key_exists($p->getCultureName(), $cultures)) {
                            $cultures[$p->getCultureName()] = ['interventions'=>[]];

                        }


                        if (!array_key_exists($intervention->type, $cultures[$p->getCultureName()]["interventions"])) {
                            $cultures[$p->getCultureName()]["interventions"][$intervention->type] = [];
                            foreach($campagnes2 as $c){
                                $cultures[$p->getCultureName()]["interventions"][$intervention->type][$c->name] = ['name' => $intervention->type, 'dates' => []];
                            }
                        }
                        $cultures[$p->getCultureName()]["interventions"][$intervention->type][$campagne->name]["dates"][]
                            = ['date' => $intervention->datetime, 'id' => $intervention->id, 'name' => $intervention->name];

                        $intervention_culture = $culture;

                    }
                }
            }

        }

        return $this->render('Bilan/bilan_dates.html.twig', array(
            'campagnes2' => $campagnes2,
            'cultures' => $cultures,
        ));
    }

    #[Route(path: '/bilan_produits', name: 'bilan_produits')]
    public function bilanProduitsAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $campagnes2 = $em->getRepository(Campagne::class)->getAllForCompany($this->getCurrentCampagne($request)->company);
        $cultures = [];

        foreach($campagnes2 as $campagne){
            $parcelles = $this->getParcellesForFiches($campagne);

            foreach ($parcelles as $p) {
                if (!array_key_exists($p->getCultureName(), $cultures)) {
                    $cultures[$p->getCultureName()] = ['culture'=>$p->getCultureName()
                    ,'produits'=>[], 'surface'=>[]];
                    foreach($campagnes2 as $c){
                        $cultures[$p->getCultureName()]["surface"][$c->name] = 0;
                    }
                }
                $cultures[$p->getCultureName()]["surface"][$campagne->name] += $p->surface;
                $culture = $cultures[$p->getCultureName()];

                $p->interventions = [];
                if($p->id != '0'){
                    $p->interventions = $em->getRepository(Intervention::class)->getAllForParcelle($p);
                }
                $p->details = [];
                foreach($p->interventions as $it){
                    foreach($it->produits as $produit){
                        $produit_name = $produit->produit->type. " - ".$produit->produit->name;
                        if (!array_key_exists($produit_name, $culture["produits"])) {
                            $cultures[$p->getCultureName()]["produits"][$produit_name] = [];
                            foreach($campagnes2 as $c){
                                $cultures[$p->getCultureName()]["produits"][$produit_name][$c->name] = 0;
                            }
                        }
                        $cultures[$p->getCultureName()]["produits"][$produit_name][$campagne->name] += $produit->getQuantityHa() * $p->surface * $produit->produit->price;
                    }
                }

                ksort($cultures[$p->getCultureName()]['produits']);
            }
        }

        return $this->render('Bilan/bilan_produits.html.twig', array(
            'campagnes2' => $campagnes2,
            'cultures' => $cultures,
        ));
    }

    #[Route(path: '/bilan_rendements', name: 'bilan_rendements')]
    public function bilanRendementsAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $campagnes2 = $em->getRepository(Campagne::class)->getAllForCompany($this->getCurrentCampagne($request)->company);
        $rendements = [];
        $cultures = [];

        foreach($campagnes2 as $campagne){
            $parcelles = $this->getParcellesForFiches($campagne);

            $rendements[$campagne->name] = ['parcelles'=>[], 'name'=>$campagne->name];
            foreach($parcelles as $p){
                $rendement = 0;
                if($p->surface!=0){
                  $rendement = $p->poid_norme/$p->surface;
                }
                $color = 0;
                $culture = "-";
                $culture_id = 0;
                if($p->culture!=NULL){
                  $color = $p->culture->color;
                  $culture = $p->culture->__toString();
                  $culture_id = $p->culture->id;
                }
                $rendements[$campagne->name]['parcelles'][$p->id] = ['name'=>$p->completeName
                    , 'espece' => $p->culture, 'color' => $color, 'surface'=>$p->surface
                    , 'poid' => $p->poid_norme, 'rendement' => $rendement, 'caracteristiques' => $p->caracteristiques];


                if (!array_key_exists($culture, $cultures)) {
                    $cultures[$culture] = ["years" => [], 'culture_id'=>$culture_id];
                }
                if (!array_key_exists($campagne->name, $cultures[$culture]["years"])) {
                    $cultures[$culture]["years"][$campagne->name] = ['poid' => 0, 'surface' => 0, 'rendement'=> 0];
                }
                $cultures[$culture]["years"][$campagne->name]['poid'] += $p->poid_norme;
                $cultures[$culture]["years"][$campagne->name]['surface'] += $p->surface;
            }

            foreach($cultures as $key => $value){
                $nb = 0;
                $sum = 0;
                $min = 0;
                $max = 0;
                foreach($cultures[$key]["years"] as $key2 => $value2){
                    $rendement = $value2['poid']/$value2['surface'];
                    $cultures[$key]["years"][$key2]['rendement'] = $rendement;
                    if($rendement != 0){
                        $nb++;
                        $sum+=$rendement;
                        if($min == 0 || $rendement < $min){
                            $min = $rendement;
                        }
                        if($max == 0 || $rendement > $max){
                            $max = $rendement;
                        }
                    }
                }
                $rendement = 0;
                if($nb != 0){
                    $rendement = $sum/$nb;
                }
                $cultures[$key]["rendement_moy"] = $rendement;
                $cultures[$key]["name"] = $key;
                $cultures[$key]["rendement_min"] = $min;
                $cultures[$key]["rendement_max"] = $max;
                //$cultures[$key][0] = ['poid' => 1, 'surface' => 1, 'rendement'=>$rendement];
            }
        }

        $chartjs_labels = [];
        foreach($cultures as $key => $value){
            $chartjs_labels[] = $key;
        }

        $chartjs_campagnes = [];
        foreach($campagnes2 as $campagne){
            $chartjs_campagne = ['name'=> $campagne->name, 'color'=> $campagne->color, 'data'=> []];
            foreach($cultures as $key => $value){
                if(array_key_exists($key, $cultures)){
                    if(array_key_exists($campagne->name, $cultures[$key]["years"])){
                        $chartjs_campagne['data'][]= $cultures[$key]["years"][$campagne->name]['rendement'];
                        continue;
                    }
                }
                $chartjs_campagne['data'][]= NULL;
            }
            $chartjs_campagnes[] = $chartjs_campagne;
        }

        foreach($cultures as $key => $value){
            $c = $em->getRepository(Culture::class)->find($value["culture_id"]);
            $rdt = 0;
            $poid = 0;
            if($c){
                $rdt = $c->rendementObj;
            }
            $value["rendement_obj"] = $rdt;
            $value["poid"] = $poid.$value["name"];
            $cultures2[] = $value;
        }

        usort($cultures2, function($a, $b) { // anonymous function
            return strcmp($a["poid"],$b["poid"]);
        });

        return $this->render('Bilan/bilan_rendements.html.twig', array(
            'campagnes' => $this->campagnes,
            'campagne_id' => $campagne->id,
            'campagnes2' => $campagnes2,
            'rendements' => $rendements,
            'cultures' => $cultures,
            'cultures2' => $cultures2,
            'chartjs_labels' => $chartjs_labels,
            'chartjs_campagnes' => $chartjs_campagnes,
        ));
    }

    #[Route(path: '/bilan_comptes', name: 'bilan_comptes')]
    public function comptesAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $campagne = $this->getCurrentCampagne($request);

        $comptes = $em->getRepository(Compte::class)->getAllForCampagne($campagne);

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

        return $this->render('Bilan/bilan_comptes.html.twig', array(
            'campagnes' => $this->campagnes,
            'campagne_id' => $campagne->id,
            'comptes' => $comptes,
            'comptes_campagnes' => $comptes_campagnes
        ));
    }

    public function cmp($line1, $line2) {
        if($line1['date'] == $line2['date']){
            if($line1['type'] == "gasoil"){
                return -1;
            }
            if($line2['type'] == "gasoil"){
                return 1;
            }
        }
        return ($line1['date'] > $line2['date']) ? -1 : 1;
    }

    #[Route(path: '/bilan_gasoil', name: 'bilan_gasoil')]
    public function bilanGasoilAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $campagnes2 = $em->getRepository(Campagne::class)->getAllForCompany($this->getCurrentCampagne($request)->company);
        $lines = [];
        foreach($campagnes2 as $campagne){
            $interventions = $em->getRepository(Intervention::class)->getAllForCampagne($campagne);

            $gasoils = $em->getRepository(Gasoil::class)->getAllForCampagne($campagne);
            foreach($gasoils as $gasoil){
                if($gasoil->litre < 0){
                    $lines[] = ['date' => $gasoil->date, 'type' => 'gasoil', 'litre' => -$gasoil->litre, 'object'=> json_encode($gasoil), 'sumHa' => 0, 'sumL' => 0, 'conso' => 0];
                }
            }

            foreach($interventions as $intervention){
                $lines[] = ['date' => $intervention->datetime, 'type' => $intervention->type, 'litre' => 0, 'ha' => $intervention->surface, 'object'=> json_encode($intervention), 'sumHa' => 0, 'sumL' => 0, 'conso' => 0];
            }

        }

        uasort($lines, array($this, 'cmp'));
        $lines2 = [];
        foreach ($lines as $line) {
            $lines2[] = $line;
        }

        $sumHa = 0;
        $sumL = 0;
        $prevHa = 0;
        for( $i = count($lines2)-1; $i >= 0 ; --$i){
            if($lines2[$i]["type"] == "gasoil"){
                if($sumHa>0){
                    $prevHa = $sumHa;
                }
                $sumHa = 0;
                $sumL = $sumL + $lines2[$i]["litre"];
                $lines2[$i]["sumL"] = $sumL;
                if($prevHa>0){
                    $lines2[$i]["conso"] = $sumL/$prevHa;
                }
            } else {
                $sumL = 0;
                $sumHa = $sumHa + $lines2[$i]["ha"];
                $lines2[$i]["sumHa"] = $sumHa;
            }

        }


        return $this->render('Bilan/bilan_gasoils.html.twig', array(
            'lines' => $lines2,
        ));
    }
}
