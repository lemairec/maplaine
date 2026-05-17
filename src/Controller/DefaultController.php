<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

use Symfony\Component\Mailer\MailerInterface;

use Datetime;

use App\Controller\CommonController;

use App\Entity\Achat;
use App\Entity\Campagne;
use App\Entity\Culture;
use App\Entity\Deplacement;
use App\Entity\Gasoil;
use App\Entity\Ilot;
use App\Entity\Intervention;
use App\Entity\InterventionParcelle;
use App\Entity\InterventionMateriel;
use App\Entity\InterventionProduit;
use App\Entity\Materiel\Materiel;
use App\Entity\Materiel\MaterielEntretien;
use App\Entity\Parcelle;
use App\Entity\Produit;
use App\Entity\Variete;


use App\Form\AchatType;
use App\Form\DataType;
use App\Form\CampagneType;
use App\Form\CompanyType;
use App\Form\CultureType;
use App\Form\DeplacementType;
use App\Form\GasoilType;
use App\Form\IlotType;
use App\Form\InterventionType;
use App\Form\InterventionParcelleType;
use App\Form\InterventionMaterielType;
use App\Form\InterventionProduitType;
use App\Form\LivraisonType;
use App\Form\MaterielEntretienType;
use App\Form\MaterielType;
use App\Form\ParcelleType;
use App\Form\ProduitType;
use App\Form\VarieteType;


class DefaultController extends CommonController
{
    #[Route(path: '/', name: 'home')]
    public function indexAction(Request $request)
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return  $this->render('home.html.twig');
        }
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();
        $campagne = $this->getCurrentCampagne($request);
        $interventions = $em->getRepository(Intervention::class)->getLast5ForCampagne($campagne);
        $parcelles = $em->getRepository(Parcelle::class)->getAllForCampagne($campagne);

        $cultures = [];
        $total = 0;
        foreach ($parcelles as $p) {
            if($p->active && $p->culture){
                if (!array_key_exists($p->getCultureName(), $cultures)) {
                    $cultures[$p->getCultureName()] = ['color'=> $p->culture->color, 'surface'=> 0];
                }
                $cultures[$p->getCultureName()]['surface'] += $p->surface;
                $total += $p->surface;
            }
        }

        $this->mylog("Accès au site");

        $meteoCity = "Paris";
        if($this->company->meteoCity){
            $meteoCity = $this->company->meteoCity;
        }

        return $this->render('Default/home_connected.html.twig', array(
            'campagnes' => $this->campagnes,
            'campagne_id' => $campagne->id,
            'interventions' => $interventions,
            'parcelles' => $parcelles,
            'meteoCity' => $meteoCity,
            'company' => $this->company,
            'user' => $this->getUser(),
            'surfaceTotale' => $total,
            'cultures' => $cultures
        ));
    }

    #[Route(path: '/my-error', name: 'my-error')]
    public function myErrorAction(Request $request)
    {
    }


    #[Route(path: 'test_mail', name: 'test_mail')]
    public function testMail(Request $request,  MailerInterface $mailer)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->sendMail("noreply@maplaine.fr", 'lemairec02@gmail.com', "Test", $mailer);

        return  $this->render('home.html.twig');
    }

    #[Route(path: '/profile/historique', name: 'profile_historique')]
    public function profile(Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();
        $logs = $em->getRepository(Log::class)->find10ByUser($this->getUser());
        return $this->render('Profile/historique.html.twig', array(
            'logs' => $logs,
            'navs' => ["historique" => "profile_historique"]
        ));
    }


    #[Route(path: '/send_file')]
    public function sendFileAction()
    {
        return $this->render('Default/send_file.html.twig');
    }

    #[Route(path: '/ilots', name: 'ilots')]
    public function ilotsAction(Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();

        $ilots = $em->getRepository(Ilot::class)->getAllforCompany($this->company);
        $sum_ilots = array_reduce($ilots, function($i, $obj)
        {
            return $i += $obj->surface;
        });

        return $this->render('Default/ilots.html.twig', array(
            'ilots' => $ilots,
            'sum_ilots' => $sum_ilots,
            'navs' => ["Ilots" => "ilots"]
        ));
    }



    #[Route(path: '/assolement', name: 'assolement')]
    public function bilanAssolement2Action(Request $request)
    {
        return $this->getAssolement($request, 2);
    }

    #[Route(path: '/assolement2', name: 'assolement2')]
    public function bilanIlotsAction(Request $request)
    {
        return $this->getAssolement($request, 2);
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();

        $ilots = $em->getRepository(Ilot::class)->getAllforCompany($this->company);

        $campagnes = $em->getRepository(Campagne::class)->getAllforCompany($this->company);

        $res = [];
        foreach($ilots as $i){
            $ligne = ["ilot" => $i];
            foreach($campagnes as $c){
                $ligne[$c->name] = ["parcelles" => $em->getRepository(Parcelle::class)->getAllForIlotCampagne($i, $c)];
            }
            $res[] = $ligne;
        }

        $cultures = $em->getRepository(Culture::class)->getAllforCompany($this->company);
        $cultures_res = [];
        foreach($cultures as $c2){
            $ligne = ["culture" => $c2];
            foreach($campagnes as $c){
                $ligne[$c->name] = ["sum" => $em->getRepository(Parcelle::class)->getSumForCultureCampagne($c2, $c)];
            }
            $cultures_res[] = $ligne;
        }
        //dump($res);


        return $this->render('Default/assolement.html.twig', array(
            'ilots' => $res,
            'cultures' => $cultures_res,
            'campagnes2' => $campagnes,
            'navs' => ["Ilots" => "ilots"]
        ));
    }

    #[Route(path: '/assolement3', name: 'assolement3')]
    public function bilanIlots3Action(Request $request)
    {
        return $this->getAssolement($request, 3);
    }

    public function getAssolement(Request $request, $mode){
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();

        $ilots = $em->getRepository(Ilot::class)->getAllforCompany($this->company);

        $campagnes = $em->getRepository(Campagne::class)->getAllforCompany($this->company);

        $res = [];
        foreach($ilots as $i){
            $maxParcelles = 0;
            foreach($campagnes as $c){
                $c = count($em->getRepository(Parcelle::class)->getAllForIlotCampagne($i, $c));
                if($c>$maxParcelles){
                    $maxParcelles = $c;
                }
            }
            $ligne_ilot = ["ilot"=>$i, "parcelles"=>[], "parcelles_count"=>$maxParcelles];
            for($j = 0; $j < $maxParcelles; $j=$j+1){
                $ligne = ["ilot" => $i, "ilot_name" => $i->name."_".$j, "idx" => $j];
                foreach($campagnes as $c){
                    foreach($campagnes as $c){
                        $parcelles = $em->getRepository(Parcelle::class)->getAllForIlotCampagne($i, $c);
                        if($j<count($parcelles)){
                            $ligne[$c->name] = ["name" => $parcelles[$j]->name, "surface" => $parcelles[$j]->surface, "culture" => $parcelles[$j]->culture];
                        } else {
                            $ligne[$c->name] = ["name" => "", "surface" => 0,"culture" => ""];
                        }
                    }
                    $cultures_res[] = $ligne;
                }
                $ligne_ilot["parcelles"][] = $ligne;

            }
            $res[] = $ligne_ilot;

        }

        $cultures = $em->getRepository(Culture::class)->getAllforCompany($this->company);
        $cultures_res = [];
        foreach($cultures as $c2){
            $ligne = ["culture" => $c2];
            foreach($campagnes as $c){
                $ligne[$c->name] = ["sum" => $em->getRepository(Parcelle::class)->getSumForCultureCampagne($c2, $c)];
            }
            $cultures_res[] = $ligne;
        }
        //dump($res);
        if($mode==2){
            return $this->render('Default/assolement2.html.twig', array(
                'ilots' => $res,
                'cultures' => $cultures_res,
                'campagnes2' => $campagnes,
                'navs' => ["Ilots" => "ilots"]
            ));
        } else if($mode == 3){
            return $this->render('Default/assolement3.html.twig', array(
                'ilots' => $res,
                'cultures' => $cultures_res,
                'campagnes2' => $campagnes,
                'navs' => ["Ilots" => "ilots"]
            ));
        }
    }



    #[Route(path: '/ilot/{ilot_id}', name: 'ilot')]
    public function ilotEditAction($ilot_id, Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();
        $parcelles = [];
        if($ilot_id == '0'){
            $ilot = new Ilot();
            $ilot->company = $this->company;
        } else {
            $ilot = $em->getRepository(Ilot::class)->findOneById($ilot_id);
            $parcelles = $em->getRepository(Parcelle::class)->getAllForIlot($ilot);
        }
        $form = $this->createForm(IlotType::class, $ilot);
        $form->handleRequest($request);


        if ($form->isSubmitted()) {
            if($ilot_id == '0'){
                $this->mylog("Création de l'ilot : ".$ilot);
            } else {
                $this->mylog("Modification de l'ilot : ".$ilot);
            }
            $em->persist($ilot);
            $em->flush();
            return $this->redirectToRoute('ilots');
        }
        return $this->render('Default/ilot.html.twig', array(
            'form' => $form->createView(),
            'parcelles' => $parcelles,
            'navs' => ["Ilots" => "ilots"]
        ));
    }


    #[Route(path: '/campagnes', name: 'campagnes')]
    public function campagnesAction(Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();

        $campagnes = $em->getRepository(Campagne::class)->getAllforCompany($this->company);
        return $this->render('Default/campagnes.html.twig', array(
            'campagnes2' => $campagnes,
            'navs' => ["Campagnes" => "campagnes"]
        ));
    }

    #[Route(path: '/campagne/{campagne_id}', name: 'campagne')]
    public function campagneEditAction($campagne_id, Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();
        if($campagne_id == '0'){
            $campagne = new Campagne();
            $campagne->company = $this->company;
        } else {
            $campagne = $em->getRepository(Campagne::class)->findOneById($campagne_id);
        }
        $form = $this->createForm(CampagneType::class, $campagne);
        if($this->getUser()->getUsername() == "lejard"){
            $form->add('commercialisation');
        }
        $form->handleRequest($request);


        if ($form->isSubmitted()) {
            $em->persist($campagne);
            $em->flush();
            return $this->redirectToRoute('campagnes');
        }
        return $this->render('base_form.html.twig', array(
            'form' => $form->createView(),
            'navs' => ["Campagnes" => "campagnes"]
        ));
    }

    #[Route(path: '/cultures', name: 'cultures')]
    public function culturesAction(Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();

        $cultures = $em->getRepository(Culture::class)->getAllforCompany($this->company);
        return $this->render('Default/cultures.html.twig', array(
            'cultures' => $cultures,
            'navs' => ["Cultures" => "cultures"]
        ));
    }

    #[Route(path: '/culture/{culture_id}', name: 'culture')]
    public function cultureEditAction($culture_id, Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();
        if($culture_id == '0'){
            $culture = new Culture();
            $culture->company = $this->company;
        } else {
            $culture = $em->getRepository(Culture::class)->findOneById($culture_id);
        }
        $form = $this->createForm(CultureType::class, $culture);
        if($this->getUser()->getUsername() == "lejard"){
            $form->add('commercialisation');
            $form->add('rendementObj');
            $form->add('prixObj');
        }
        $form->handleRequest($request);


        if ($form->isSubmitted()) {
            $pos = strpos($culture->color, "#");
            if ($pos === false) {
                $culture->color = "#".$culture->color;
            }
            if($culture_id == '0'){
                $this->mylog("Création de la culture : ".$culture);
            } else {
                $this->mylog("Modification de la culture : ".$culture);
            }

            $em->persist($culture);
            $em->flush();
            return $this->redirectToRoute('cultures');
        }
        return $this->render('Default/culture.html.twig', array(
            'form' => $form->createView(),
            'navs' => ["Cultures" => "cultures"]
        ));
    }

    public function getParcelles(Request $request, $table){

        $em = $this->getDoctrine()->getManager();
        $campagne = $this->getCurrentCampagne($request);

        $cultures = [];
        $total = 0;

        $is = $em->getRepository(Ilot::class)->getAllForCompany($campagne->company);
        $ilots = [];
        foreach ($is as $i) {
            $ilots[] = ["id" => $i->id, "name" => $i->name, "surface_totale" => $i->surface, "surface" => 0];
        }

        $parcelles = $em->getRepository(Parcelle::class)->getAllForCampagne($campagne);
        foreach ($parcelles as $p) {
            if($p->active && $p->culture){
                if (!array_key_exists($p->getCultureName(), $cultures)) {
                    $cultures[$p->getCultureName()] = 0;
                }
                $cultures[$p->getCultureName()] += $p->surface;
                $total += $p->surface;
            }
            if($p->ilot){
                for ( $i = 0; $i< count($ilots);++$i) {
                    if($ilots[$i]["id"] == $p->ilot->id){
                        $ilots[$i]["surface"] = $ilots[$i]["surface"] + $p->surface;
                    }
                }
            }
        }

        $ilots2 = [];
        foreach ($ilots as $ilot){
            if($ilot["surface_totale"] > $ilot["surface"]){
                $ilot["surface_restante"] = $ilot["surface_totale"]-$ilot["surface"];
                $ilots2[] = $ilot;
            }
        }
        if($total == 0){
            $total = 1;//todo berk
        }

        if($table == 0){
            return $this->render('Default/parcelles_t.html.twig', array(
                'ilots' => $ilots2,
                'campagnes' => $this->campagnes,
                'campagne_id' => $campagne->id,
                'parcelles' => $parcelles,
                'cultures' => $cultures,
                'total' => $total,
                'navs' => ["Parcelles" => "parcelles"]
            ));
        } else if($table == 1){
            return $this->render('Default/parcelles_map.html.twig', array(
                'ilots' => [],
                'campagnes' => $this->campagnes,
                'campagne_id' => $campagne->id,
                'parcelles' => $parcelles,
                'cultures' => [],
                'total' => $total,
                'navs' => ["Parcelles" => "parcelles"]
            ));
        } else {
            return $this->render('Default/parcelles_t2.html.twig', array(
                'ilots' => $ilots2,
                'campagnes' => $this->campagnes,
                'campagne_id' => $campagne->id,
                'parcelles' => $parcelles,
                'cultures' => $cultures,
                'total' => $total,
                'navs' => ["Parcelles" => "parcelles"]
            ));
        }
    }

    #[Route(path: 'parcelles', name: 'parcelles2')]
    public function parcellesAction(Request $request)
    {
        return $this->getParcelles($request, 0);

    }

    #[Route(path: 'parcelles_t', name: 'parcelles')]
    public function parcellesTAction(Request $request)
    {
        return $this->getParcelles($request, 1);
    }

     #[Route(path: 'parcelles_t2', name: 'parcelles3')]
    public function parcellesTAction3(Request $request)
    {
        return $this->getParcelles($request, 2);
    }

    #[Route(path: '/parcelle/{parcelle_id}', name: 'parcelle')]
    public function parcelleEditAction($parcelle_id, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $campagne = $this->getCurrentCampagne($request);
        $ilots = $em->getRepository(Ilot::class)->getAllforCompany($this->company);
        $cultures = $em->getRepository(Culture::class)->getAllforCompany($this->company);
        if($parcelle_id == '0'){
            $parcelle = new Parcelle();
            $parcelle->active = 1;
            $parcelle->campagne = $campagne;
            $parcelle->surface = $request->query->get("surface", 0);
            $parcelle->ilot = $em->getRepository(Ilot::class)->find($request->query->get("ilot_id", ""));
            $parcelle->geoJson = "";
        } else {
            $parcelle = $em->getRepository(Parcelle::class)->findOneById($parcelle_id);
        }

        $form = $this->createForm(ParcelleType::class, $parcelle, array(
            'ilots' => $ilots,
            'cultures' => $cultures
        ));
        $form->handleRequest($request);


        if ($form->isSubmitted()) {
            $parcelle = $em->getRepository(Parcelle::class)->save($parcelle);
            return $this->redirectToRoute('parcelles');
        }
        $interventions = [];
        if($parcelle->id && $parcelle->id != '0'){
            $interventions = $em->getRepository(Intervention::class)->getAllForParcelle($parcelle);
         }
        $priceHa = 0;
        foreach($interventions as $it){
            $priceHa += $it->getPriceHa();
        }
        return $this->render('Default/parcelle.html.twig', array(
            'form' => $form->createView(),
            'parcelle_id' => $parcelle_id,
            'parcelle' => $parcelle,
            'interventions' => $interventions,
            'priceHa' => $priceHa,
            'navs' => ["Parcelles" => "parcelles"]
        ));
    }

    #[Route(path: '/parcelle/{parcelle_id}/variete/{variete_id}', name: 'variete')]
    public function varieteEditAction($parcelle_id, $variete_id, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $campagne = $this->getCurrentCampagne($request);
        $parcelle = $em->getRepository(Parcelle::class)->findOneById($parcelle_id);
        $cultures = $em->getRepository(Culture::class)->getAllforCompany($this->company);
        if($variete_id == '0'){
            $variete = new Variete();
            $variete->parcelle = $parcelle;
            $variete->ordre = 0;

            $sum = 0;
            foreach($parcelle->varietes as $v){
                $sum += $v->surface;
            }
            $variete->surface = $parcelle->surface-$sum;
        } else {
            $variete = $em->getRepository('App:Variete')->findOneById($variete_id);
        }

        $form = $this->createForm(VarieteType::class, $variete, array(
            'cultures' => $cultures
        ));

        $form->handleRequest($request);


        if ($form->isSubmitted()) {
            $em->persist($variete);
            $em->flush();
            return $this->redirectToRoute('parcelle',['parcelle_id'=> $parcelle_id]);
        }

        return $this->render('base_form.html.twig', array(
            'form' => $form->createView(),
            'navs' => ["Campagnes" => "campagnes"]
        ));

    }

    #[Route(path: '/parcelle/{parcelle_id}/variete/{variete_id}/delete', name: 'variete_delete')]
    public function varieteDeleteAction($parcelle_id, $variete_id, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $campagne = $this->getCurrentCampagne($request);
        $parcelle = $em->getRepository(Parcelle::class)->findOneById($parcelle_id);
        $variete = $em->getRepository('App:Variete')->findOneById($variete_id);

        $em->remove($variete);
        $em->flush();
        return $this->redirectToRoute('parcelle',['parcelle_id'=> $parcelle_id]);

    }

    #[Route(path: '/parcelles/merge', name: 'parcelles_merge', methods: ['POST'])]
    public function parcellesMergeAction(Request $request): JsonResponse
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();

        $data = json_decode($request->getContent(), true);
        $ids     = $data['parcelle_ids'] ?? [];
        $newName = trim($data['name'] ?? '');
        $geoJson = $data['geoJson'] ?? null;

        if (count($ids) < 2 || !$newName) {
            return new JsonResponse(['error' => 'Données invalides'], 400);
        }

        $originals = $em->getRepository(Parcelle::class)
            ->createQueryBuilder('p')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()->getResult();

        if (count($originals) !== count($ids)) {
            return new JsonResponse(['error' => 'Parcelles introuvables'], 404);
        }

        $first = $originals[0];
        $totalSurface = array_sum(array_map(fn($p) => $p->surface ?? 0, $originals));

        $originalIdsList = array_map(fn($p) => $p->id, $originals);
        $links = $em->getRepository(InterventionParcelle::class)
            ->createQueryBuilder('ip')
            ->where('ip.parcelle IN (:ids)')
            ->setParameter('ids', $originalIdsList)
            ->getQuery()->getResult();

        foreach ($originals as $p) {
            $p->active = 0;
        }

        $merged = new Parcelle();
        $merged->campagne     = $first->campagne;
        $merged->ilot         = $first->ilot;
        $merged->culture      = $first->culture;
        $merged->active       = 1;
        $merged->name         = $newName;
        $merged->completeName = $newName;
        $merged->surface      = $totalSurface;
        $merged->commune      = $first->commune;
        $merged->geoJson      = $geoJson ?? $first->geoJson;
        $em->persist($merged);
        $em->flush();

        $seenInterventions = [];
        foreach ($links as $link) {
            $iid = (string) $link->intervention->id;
            if (!in_array($iid, $seenInterventions)) {
                $newLink = new InterventionParcelle();
                $newLink->intervention = $link->intervention;
                $newLink->parcelle     = $merged;
                $em->persist($newLink);
                $seenInterventions[] = $iid;
            }
            $em->remove($link);
        }
        $em->flush();

        return new JsonResponse(['ok' => true, 'redirect' => $this->generateUrl('parcelles')]);
    }

    #[Route(path: '/parcelle/{parcelle_id}/delete', name: 'parcelle_delete')]
    public function parcelleDeleteAction($parcelle_id, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $em->getRepository(Parcelle::class)->delete($parcelle_id);
        return $this->redirectToRoute('parcelles');
    }

    #[Route(path: '/calendar', name: 'calendar')]
    public function calendar(Request $request)
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();
        $interventions = $em->getRepository(Intervention::class)->getAllForCompany($this->company);
        $gasoils = $em->getRepository(Gasoil::class)->getAllForCompany($this->company);
        $deplacements = $em->getRepository('App:Deplacement')->getAllForCompany($this->company);
        return $this->render('Default/calendar.html.twig', array(
            'interventions' => $interventions,
            'gasoils' => $gasoils,
            'deplacements' => $deplacements
        ));
    }


    #[Route(path: 'deplacements', name: 'deplacements')]
    public function deplacementsAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $campagne = $this->getCurrentCampagne($request);

        $deplacements = $em->getRepository('App:Deplacement')->getAllForCampagne($campagne);
        return $this->render('Default/deplacements.html.twig', array(
            'campagnes' => $this->campagnes,
            'campagne_id' => $campagne->id,
            'deplacements' => $deplacements
        ));
    }

    #[Route(path: '/deplacement/{deplacement_id}', name: 'deplacement')]
    public function deplacementEditAction($deplacement_id, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $campagne = $this->getCurrentCampagne($request);
        if($deplacement_id == '0'){
            $deplacement = new Deplacement();
            $deplacement->name = "Warmo";
            $deplacement->km = 164;
            $deplacement->campagne = $campagne;
            $deplacement->date = new \DateTime();
        } else {
            $deplacement = $em->getRepository('App:Deplacement')->findOneById($deplacement_id);
        }
        $form = $this->createForm(DeplacementType::class, $deplacement);
        $form->handleRequest($request);


        if ($form->isSubmitted()) {
            $gasoil = $em->getRepository('App:Deplacement')->save($deplacement);
            return $this->redirectToRoute('deplacements');
        }
        return $this->render('Default/deplacement.html.twig', array(
            'form' => $form->createView(),
            'campagnes' => $this->campagnes,
            'campagne_id' => $campagne->id,
        ));
    }

    #[Route(path: '/carte', name: 'carte')]
    public function carte(Request $request)
    {
        return $this->render('carte.html.twig');
    }

    #[Route(path: '/traccia.gpx', name: 'traccia')]
    public function traccia(Request $request)
    {
        return $this->render('traccia.html.twig');
    }
}
