<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
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
use App\Entity\User;



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


use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class HomeController extends CommonController
{
    #[Route(path: '/', name: 'home')]
    public function indexAction(Request $request)
    {
        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            return  $this->render('home.html.twig');
        }
        
        if(!$this->getUser()->hasRole('ROLE_ADMIN') &&$this->getUser()->hasRole('ROLE_COTATION')){
            return $this->redirectToRoute('cotation_home');
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

    #[Route(path: '/k8f96gtb', name: 'connection_user')]
    public function testAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $user_id = $request->query->get('user_id');
        if($user_id == ''){
            return new Response("user not found");
        }
        $user = $em->getRepository(User::class)->findOneById($user_id);

        $this->company = null;
        $this->campagne = null;

        $session = $this->requestStack->getSession();
        $session->set('companies', null);
        $session->set('company_id', null);

        $this->security->login($user, 'form_login', 'main', [(new RememberMeBadge())->enable()]);

        $user->setLastLogin(new \DateTime());
        $em->persist($user);
        $em->flush();

        //home
        return $this->redirectToRoute('home');
        //return $this->indexAction($request);
    }
}
