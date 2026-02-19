<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Mime\Email;

use App\Entity\Log;
use App\Entity\Company;
use App\Entity\Campagne;
use App\Entity\Parcelle;
use App\Entity\Intervention;
use App\Entity\InterventionRecolte;
use DateTime;

class CommonController extends AbstractController
{
    protected $security;
    protected $doctrine;
    public function __construct(RequestStack $requestStack, Security $security, ManagerRegistry $doctrine)
    {
        $this->requestStack = $requestStack;
        $this->security = $security;
        $this->doctrine = $doctrine;
    }

    public function getDoctrine(){
      return $this->doctrine;
    }


    public function mylog($string){
        $em = $this->getDoctrine()->getManager();

        $user = $this->getUser();
        //$this->company = $em->getRepository(Company::class)->findOrCreate($user);

        $log = new Log();
        $log->date = new DateTime();

        $log->user = $user;
        $log->company = $this->company;

        $log->description = $string;

        $em->persist($log);
        $em->flush();
    }

    public function mylog2($string, $detail){
        $em = $this->getDoctrine()->getManager();

        $user = $this->getUser();
        //$this->company = $em->getRepository(Company::class)->findOrCreate($user);

        $log = new Log();
        $log->date = new DateTime();

        $log->user = $user;
        $log->company = $this->company;

        $log->description = $string;
        $log->detail = json_encode($detail);

        $em->persist($log);
        $em->flush();
    }

    public function check_user($request){

        if (!$this->security->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw $this->createAccessDeniedException();
        }
        $this->getUser()->show_unity=true;
        $this->getUser()->show_unity=true;
        $em = $this->getDoctrine()->getManager();
        $user = $this->getUser();

        $this->companies = $em->getRepository(Company::class)->getAllForUser($user);
        $company_id = $this->getCurrentCompanyId($request);
        $this->company = $em->getRepository(Company::class)->findOneById($company_id);
        if($this->company == null){
            $this->company = $em->getRepository(Company::class)->findOrCreate($user);
        }
        $session = $this->requestStack->getSession();
        $session->set('companies', $this->companies);
        $session->set('company_id', $this->company->id);


        $this->getUser()->show_unity=true;

        $show_unity = $request->query->get('show_unity');
        if($show_unity == 'false'){
            $this->getUser()->show_unity=false;
        }

        $this->saveLastUrl($request);
    }

    public function getCurrentCompanyId($request){
        $session = $request->getSession();

        $company_id = $session->get('company_id', '');
        if($company_id == ''){
            $em = $this->getDoctrine()->getManager();
            $company = $em->getRepository(Company::class)->findAll()[0]->id;
        }
        $new_company_id = $request->query->get('company_id');
        if($new_company_id != ''){
            $session->set('company_id', $new_company_id);
            return $new_company_id;
        }

        return $company_id;
    }

    public function getCurrentCampagneId($request){
        $session = $request->getSession();

        $campagne_id = $session->get('campagne_id', '');
        if($campagne_id == ''){
            $em = $this->getDoctrine()->getManager();
            $campagne = $em->getRepository(Campagne::class)->findAll()[0]->id;
        }
        $new_campagne_id = $request->query->get('campagne_id');
        if($new_campagne_id != ''){
            $session->set('campagne_id', $new_campagne_id);
            return $new_campagne_id;
        }



        return $campagne_id;
    }

    public function getCurrentCampagne($request){
        $campagne_id = $this->getCurrentCampagneId($request);
        $em = $this->getDoctrine()->getManager();
        $this->check_user($request);

        $campagne = $em->getRepository(Campagne::class)->findOneById($campagne_id);
        if(!property_exists($this, 'campagnes')){
            $this->campagnes = $em->getRepository(Campagne::class)->getAllforCompany($this->company);
        }
        if(count($this->campagnes)<1){
            $em->getRepository(Campagne::class)->createCurrentCampagne($this->company);
            $this->campagnes = $em->getRepository(Campagne::class)->getAllforCompany($this->company);
        }

        if($campagne){
            return $campagne;
        } else {
            return $this->campagnes[0];
        }
    }

    public function saveLastUrl($request){
        /*$last_url = $this->get('session')->get('last_url', []);
        $url = $request->getUri();
        if(count($last_url)==0 || $last_url[count($last_url)-1] != $url){
            $last_url[] = $url;
            if(count($last_url) > 10){
                array_shift($last_url);
            }
            $this->get('session')->set('last_url', $last_url);
        }*/ //todo
        //print(json_encode( $this->get('session')->get('last_url', [])));
    }

    public function sendMail($from, $to, $str, $mailer){
        $email = (new Email())
            ->from($from)
            ->to($to)
            //->cc('cc@example.com')
            //->bcc('bcc@example.com')
            //->replyTo('fabien@example.com')
            //->priority(Email::PRIORITY_HIGH)
            ->subject('Time for Symfony Mailer!')
            ->text($str)
            ->html('<p>See Twig integration for better HTML integration!</p>');

        $mailer->send($email);
    }


    protected function stringlify($str){
        $str = str_replace(" - ", '_', $str);
        $str = str_replace(' ', '_', $str);
        $str = str_replace('-', '', $str);
        $str = str_replace(',', '', $str);
        $str = str_replace('/', '_', $str);
        $str = str_replace('&', '_', $str);
        $str = str_replace('é', 'e', $str);
        $str = str_replace('è', 'e', $str);
        $str = strtolower($str);
        return $str;
    }

    protected function parseFloat($str){
        return floatval(str_replace(",",".",$str));
    }

    public function getParcellesForFiches($campagne){
        $em = $this->getDoctrine()->getManager();
        $parcelles = $em->getRepository(Parcelle::class)->getAllForCampagneWithoutActive($campagne);
        foreach ($parcelles as $p) {
            $p->interventions = [];
            if($p->id != '0'){
                $p->interventions = $em->getRepository(Intervention::class)->getAllForParcelle($p);
            }
            $p->engrais_n = 0;
            $p->engrais_p = 0;
            $p->engrais_k = 0;
            $p->engrais_mg = 0;
            $p->engrais_so3 = 0;

            $p->poid_norme = 0;
            $p->priceHa = 0;

            $caracteristiques2 = [];


            foreach($p->interventions as $it){
                $p->priceHa += $it->getPriceHa();
                foreach($it->produits as $produit){
                    $p->engrais_n += $produit->getQuantityHa() * $produit->produit->getEngraisN();
                    $p->engrais_p += $produit->getQuantityHa() * $produit->produit->getEngraisP();
                    $p->engrais_k += $produit->getQuantityHa() * $produit->produit->getEngraisK();
                    $p->engrais_mg += $produit->getQuantityHa() * $produit->produit->getEngraisMg();
                    $p->engrais_so3 += $produit->getQuantityHa() * $produit->produit->getEngraisSO3();
                }
                $poid = 0;
                foreach($it->recoltes as $recolte){
                    $rendement = 0;
                    if($it->surface != 0){
                        $rendement = $recolte->poid_norme/$it->surface;
                    }
                    $poid += $recolte->poid_norme;
                    $poid_norme = $rendement*$p->surface;
                    if($recolte->caracteristiques){
                        foreach($recolte->caracteristiques as $key => $value){
                            if (!array_key_exists($key, $caracteristiques2)) {
                                $caracteristiques2[$key] = ["value"=>0, "poid"=>0];
                            }
                            $caracteristiques2[$key]["value"] += $this->parseFloat($value)*$poid_norme;
                            $caracteristiques2[$key]["poid"] += $poid_norme;
                        }
                    }
                    $p->poid_norme += $poid_norme;
                }
                $it->recolte_ha = $poid/$it->surface;;
                
            }

            if($p->surface == 0){
                $p->rendement = 0;
            } else {
                $p->rendement = $p->poid_norme/$p->surface;
            }

            $caracteristiques = [];
            foreach($caracteristiques2 as $key => $value){
                if($caracteristiques2[$key]["poid"]!= 0){
                    $caracteristiques[$key] = $caracteristiques2[$key]["value"]/$caracteristiques2[$key]["poid"];
                }
            }
            $p->caracteristiques = InterventionRecolte::getStaticCarateristiques($caracteristiques);

        }
        return $parcelles;
    }

}
