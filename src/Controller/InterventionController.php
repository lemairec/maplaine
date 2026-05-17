<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use Datetime;

use App\Controller\CommonController;

use App\Entity\Parcelle;
use App\Entity\Alerte;
use App\Entity\Produit;
use App\Entity\Intervention;
use App\Entity\InterventionParcelle;
use App\Entity\InterventionProduit;
use App\Entity\InterventionRecolte;

class InterventionController extends CommonController
{
    #[Route(path: '/interventions', name: 'interventions')]
    public function interventions(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $campagne = $this->getCurrentCampagne($request);
        $interventions = $em->getRepository(Intervention::class)->getAllForCampagne($campagne);
        return $this->render('Default/interventions.html.twig', array(
            'campagnes' => $this->campagnes,
            'campagne_id' => $campagne->id,
            'interventions' => $interventions,
            'navs' => ["Interventions" => "interventions"]
        ));
    }

    #[Route(path: '/intervention/{intervention_id}', name: 'intervention', methods: ['GET'])]
    public function interventionGetAction($intervention_id, Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();
        $campagne = $this->getCurrentCampagne($request);

        $parcelles2 =  $em->getRepository(Parcelle::class)->getAllForCampagne($campagne);

        $parcelles = [];
        $produitsIntervention = [];
        $recoltesIntervention = [];
        $date = new \Datetime();
        $type = "";
        $name = "";
        $comment = "";

        $intervention = $em->getRepository(Intervention::class)->find($intervention_id);
        if($intervention){
            if($campagne->id != $intervention->campagne->id){
                $campagne = $intervention->campagne;
                $parcelles2 =  $em->getRepository(Parcelle::class)->getAllForCampagne($campagne);
            }
            $date = $intervention->datetime;
            $type = $intervention->type;
            $name = $intervention->name;
            $comment = $intervention->comment;
            foreach($intervention->produits as $produit){
                $produitsIntervention[] = ["name" => $produit->name, "qty" => $produit->quantity];
            }
            foreach($intervention->recoltes as $recolte){
                $recoltesIntervention[] = ["datetime" => $recolte->datetime->format('d/m/Y H:i')
                    , "poid_norme" => $recolte->poid_norme
                    , "poid_total" => $recolte->poid_total
                    , "tare" => $recolte->tare
                    , "espece" => $recolte->espece
                    , "caracteristiques" => $recolte->getCarateristiques()];
            }
            foreach($parcelles2 as $parcelle){
                if (!$parcelle->active) continue;
                $checked = false;
                foreach($intervention->parcelles as $p){
                    if($parcelle->id == $p->parcelle->id){
                        $checked = true;
                    }
                }

                $parcelles[] = ["id" => $parcelle->id, "name" => $parcelle->completeName, "surface"=>$parcelle->surface, "checked"=>$checked];
            }
        } else {
            foreach($parcelles2 as $parcelle){
                if (!$parcelle->active) continue;
                $parcelles[] = ["id" => $parcelle->id, "name" => $parcelle->completeName, "surface"=>$parcelle->surface, "checked"=>false];
            }
        }

        $produits = $em->getRepository(Produit::class)->getAllName($campagne);

        return $this->render('Default/intervention.html.twig', array(
            'id' => $intervention_id,
            'date' => $date->format('d/m/Y'),
            'type' => $type,
            'name' => $name,
            'comment' => $comment,
            'produits' => $produits,
            'produitsIntervention' => $produitsIntervention,
            'recoltesIntervention' => $recoltesIntervention,
            'parcelles' => $parcelles,
            'navs' => ["Interventions" => "interventions"]
        ));
    }

    #[Route(path: '/api/intervention', name: 'intervention_api')]
    public function annoncesApiAction(Request $request)
    {
        $data = json_decode($request->getContent(), true);
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();
        $campagne = $this->getCurrentCampagne($request);
        $em->getRepository(Alerte::class)->removeAlerteCampagne($campagne);

        $data = $data["intervention"];

        $intervention = $em->getRepository(Intervention::class)->find($data["id"]);
        if($intervention){
            $em->getRepository(Intervention::class)->my_clear($intervention);
        } else {
            $intervention = new Intervention();
            $intervention->campagne = $campagne;
            $intervention->company = $campagne->company;
        }
        $intervention->surface = 0;
        $intervention->type = $data["type"];
        $intervention->comment = $data["comment"];
        $intervention->name = $data["name"];
        $intervention->datetime = DateTime::createFromFormat('d/m/Y', $data["date"]);
        $em->persist($intervention);
        $em->flush();

        foreach($data["parcelles"] as $parcelle){
            if($parcelle["checked"]){
                $it = new InterventionParcelle();
                $it->intervention = $intervention;
                $it->parcelle = $em->getRepository(Parcelle::class)->find($parcelle["id"]);
                $em->persist($it);
                $em->flush();
                $intervention->surface += $it->parcelle->surface;
            }
        }

        foreach($data["produits"] as $produit){
            $it = new InterventionProduit();
            $it->intervention = $intervention;
            $it->name = ($produit["name"]);
            $it->quantity = $this->parseFloat($produit["qty"]);
            $em->getRepository(InterventionProduit::class)->save($it, $campagne);
        }

        foreach($data["recoltes"] as $recolte){
            $it = new InterventionRecolte();
            $it->intervention = $intervention;
            $it->datetime = DateTime::createFromFormat('d/m/Y H:i', $recolte["datetime"]);
            $it->poid_norme = $this->parseFloat($recolte["poid_norme"]);
            $it->poid_total = $this->parseFloat($recolte["poid_total"]);
            $it->tare = $this->parseFloat($recolte["tare"]);
            $it->espece = $recolte["espece"];
            $it->caracteristiques = [];
            $res = explode(";", $recolte["caracteristiques"]);
            dump($res);
            foreach($res as $r){
                $l = explode(" ", trim($r));
                dump($l);
                if(count($l)>1){
                    $it->caracteristiques[$l[0]] = $l[1];
                }
            }
            $em->persist($it);
            $em->flush();

        }



        $em->persist($intervention);
        $em->flush();


        if($data["id"] == '0'){
            $this->mylog2("Création de l'intervention du ".$data["date"]." ".$intervention->name, $data);
        } else {
            $this->mylog2("Modification de l'intervention du ".$data["date"]." ".$intervention->name, $data);
        }

        return new JsonResponse("OK");

    }

    #[Route(path: '/intervention/{intervention_id}/delete', name: 'intervention_delete')]
    public function interventionDeleteAction($intervention_id, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $em->getRepository(Intervention::class)->delete($intervention_id);
        return $this->redirectToRoute('interventions');
    }

    #[Route(path: '/intervention/{intervention_id}/parcelle/{intervention_parcelle_id}', name: 'intervention_parcelle')]
    public function interventionParcelleAction($intervention_id, $intervention_parcelle_id, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $campagne = $this->getCurrentCampagne($request);
        if($intervention_parcelle_id == '0'){
            $intervention_parcelle = new InterventionParcelle();
            $intervention_parcelle->intervention = $em->getRepository(Intervention::class)->findOneById($intervention_id);
        } else {
            $intervention_parcelle = $em->getRepository(InterventionParcelle::class)->findOneById($intervention_parcelle_id);
        }
        $parcelles =  $em->getRepository(Parcelle::class)->getAllForCampagne($campagne);
        $form = $this->createForm(InterventionParcelleType::class, $intervention_parcelle, array(
            'parcelles' => $parcelles
        ));
        $form->handleRequest($request);


        if ($form->isSubmitted()) {
            $em->getRepository(InterventionParcelle::class)->save($intervention_parcelle);
            return $this->redirectToRoute('intervention', array('intervention_id' => $intervention_id));
        }
        return $this->render('base_form.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    #[Route(path: '/intervention/{intervention_id}/parcelle/{intervention_parcelle_id}/delete', name: 'intervention_parcelle_delete')]
    public function interventionParcelleDeleteAction($intervention_id, $intervention_parcelle_id, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $em->getRepository(InterventionParcelle::class)->delete($intervention_parcelle_id);
        return $this->redirectToRoute('intervention', array('intervention_id' => $intervention_id));
    }

    #[Route(path: '/intervention/{intervention_id}/produit/{intervention_produit_id}', name: 'intervention_produit')]
    public function interventionProduitAction($intervention_id, $intervention_produit_id, Request $request)
    {
        $campagne = $this->getCurrentCampagne($request);
        $em = $this->getDoctrine()->getManager();
        if($intervention_produit_id == '0'){
            $intervention_produit = new InterventionProduit();
            $intervention_produit->intervention = $em->getRepository(Intervention::class)->findOneById($intervention_id);
        } else {
            $intervention_produit = $em->getRepository(InterventionProduit::class)->findOneById($intervention_produit_id);
        }
        $form = $this->createForm(InterventionProduitType::class, $intervention_produit);
        $form->handleRequest($request);
        $produits = $em->getRepository(Produit::class)->getAllName($campagne);

        if ($form->isSubmitted()) {
            $em->getRepository(InterventionProduit::class)->save($intervention_produit, $campagne);
            return $this->redirectToRoute('intervention', array('intervention_id' => $intervention_id));
        }
        return $this->render('Default/intervention_produit.html.twig', array(
            'form' => $form->createView(),
            'produits' => $produits,
            'surface_totale' => $intervention_produit->intervention->surface

        ));
    }

    #[Route(path: '/intervention/{intervention_id}/materiel/{intervention_materiel_id}', name: 'intervention_materiel')]
    public function interventionMaterielAction($intervention_id, $intervention_materiel_id, Request $request)
    {
        $campagne = $this->getCurrentCampagne($request);
        $em = $this->getDoctrine()->getManager();
        if($intervention_materiel_id == '0'){
            $intervention_materiel = new InterventionMateriel();
            $intervention_materiel->intervention = $em->getRepository(Intervention::class)->findOneById($intervention_id);
        } else {
            $intervention_materiel = $em->getRepository(InterventionMateriel::class)->findOneById($intervention_materiel);
        }
        $materiels =  $em->getRepository(Materiel::class)->getAllForCompany($this->company);
        $form = $this->createForm(InterventionMaterielType::class, $intervention_materiel, array(
            'materiels' => $materiels
        ));
        $form->handleRequest($request);


        if ($form->isSubmitted()) {
            $em->getRepository(InterventionMateriel::class)->save($intervention_materiel);
            return $this->redirectToRoute('intervention', array('intervention_id' => $intervention_id));
        }
        return $this->render('base_form.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    #[Route(path: '/intervention/{intervention_id}/produit/{intervention_produit_id}/delete', name: 'intervention_produit_delete')]
    public function interventionProduitDeleteAction($intervention_id, $intervention_produit_id, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $em->getRepository(InterventionProduit::class)->delete($intervention_produit_id);
        return $this->redirectToRoute('intervention', array('intervention_id' => $intervention_id));
    }

}
