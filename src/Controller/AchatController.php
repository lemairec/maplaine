<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Datetime;


use App\Controller\CommonController;

use App\Entity\Achat;
use App\Entity\Produit;
use App\Entity\Gestion\FactureFournisseur;

use App\Form\AchatType;
use App\Form\DataType;


class AchatController extends CommonController
{
    #[Route(path: '/achats', name: 'achats')]
    public function achatsAction(Request $request)
    {
        $campagne = $this->getCurrentCampagne($request);
        $em = $this->getDoctrine()->getManager();

        $achats = $em->getRepository(Achat::class)
            ->createQueryBuilder('p')
            ->where('p.campagne = :campagne')
            ->add('orderBy','p.date DESC, p.type ASC')
            ->setParameter('campagne', $campagne)
            ->getQuery()->getResult();

        return $this->render('Default/achats.html.twig', array(
            'campagnes' => $this->campagnes,
            'campagne_id' => $campagne->id,
            'achats' => $achats,
            'navs' => ["Produits" => "produits", "Achats" => "achats"]
        ));
    }

    #[Route(path: '/achat/{achat_id}', name: 'achat')]
    public function achatEditAction($achat_id, Request $request)
    {
        $campagne = $this->getCurrentCampagne($request);
        $em = $this->getDoctrine()->getManager();

        $facture = null;
        $facture_id = $request->query->get('facture_id');
        if($facture_id){
            $facture = $em->getRepository(FactureFournisseur::class)->findOneById($facture_id);
            if($facture && $facture->campagne){
                $campagne = $facture->campagne;
            }
        }

        $produits = $em->getRepository(Produit::class)->findByCompany($campagne->company);
        $factures = $em->getRepository(FactureFournisseur::class)->getAllForCampagne($campagne);
        if($achat_id == '0'){
            $achat = new Achat();
            $achat->date = new \DateTime();
            if($facture){
                $achat->facture = $facture;
            }
        } else {
            $achat = $em->getRepository(Achat::class)->findOneById($achat_id);
            $achat->name = $achat->produit->__toString();
        }

        $form = $this->createForm(AchatType::class, $achat, array(
            'produits' => $produits,
            'factures' => $factures
        ));
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $em->getRepository(Achat::class)->save($achat, $campagne);
            if($facture){
                return $this->redirectToRoute('facture_fournisseur', array('facture_id' => $facture->id));
            }
            return $this->redirectToRoute('achats');
        }
        return $this->render('Default/achat.html.twig', array(
            'form' => $form->createView(),
            'produits' => $produits,
            'campagnes' => $this->campagnes,
            'campagne_id' => $campagne->id,
            'navs' => ["Produits" => "produits", "Achats" => "achats"]
        ));
    }

    #[Route(path: '/achat/{achat_id}/delete', name: 'achat_delete')]
    public function achatDeleteAction($achat_id, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $em->getRepository(Achat::class)->delete($achat_id);
        return $this->redirectToRoute('achats');
    }

    #[Route(path: '/achats_data', name: 'achats_data')]
    public function achatsDataEditAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(DataType::class);
        $form->handleRequest($request);

        $campagne = $this->getCurrentCampagne($request);
        if ($form->isSubmitted()) {
            $data = $form->getData();
            $em->getRepository(Achat::class)->saveCAJData($data['data'], $campagne);
            //return $this->redirectToRoute('achats');
        }
        return $this->render('base_form.html.twig', array(
            'form' => $form->createView(),
            'campagnes' => $this->campagnes,
            'campagne_id' => $campagne->id,
        ));
    }
}
