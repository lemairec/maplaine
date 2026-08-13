<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\Group;
use App\Entity\User;
use App\Entity\Company;
use App\Entity\Parcelle;
use App\Entity\Intervention;
use App\Entity\Log;
use App\Entity\MetaCulture;

use App\Form\UserType;
use App\Form\CompanyAdminType;
use App\Form\GroupType;
use App\Form\MetaCultureType;

class AdminController extends CommonController
{
        #[Route(path: '/admin', name: 'admin')]
        public function adminAction(Request $request){
            return new Response('Admin page');
        }


        #[Route(path: '/admin456/users', name: 'admin_users')]
        public function adminUsersAction(Request $request)
        {
            $em = $this->getDoctrine()->getManager();
            $users = $em->getRepository(User::class)->findAll();
            $logs = $em->getRepository(Log::class)->countByUser();
            $parcelles = $em->getRepository(Parcelle::class)->countByCompany();
            $interventions = $em->getRepository(Intervention::class)->countByCompany();

            $logs_dict = [];
            foreach ($logs as $log) {
                $logs_dict[$log['user_id']] = intval($log['count']);
            }

            foreach($users as $user){
                $companies = $em->getRepository(Company::class)->getAllForUser($user);
                $user->logs = 0;
                $user->parcelles = 0;
                $user->interventions = 0;
                if (isset($logs_dict[$user->id])){
                    $user->logs = $logs_dict[$user->id];
                }
                foreach ($companies as $company) {
                    if (isset($parcelles_dict[$company->id])){
                        $user->parcelles = $parcelles_dict[$company->id];
                    }
                    if (isset($interventions_dict[$company->id])){
                        $user->interventions = $interventions_dict[$company->id];
                    }
                }
            }

            return $this->render('Admin/users.html.twig', array(
                'users' => $users
            ));

        }

        #[Route(path: '/admin456/groups', name: 'admin_groups')]
        public function adminGroupsAction(Request $request)
        {
            $em = $this->getDoctrine()->getManager();
            $groups = $em->getRepository(Group::class)->findAll();
            
            return $this->render('Admin/groups.html.twig', array(
                'groups' => $groups
            ));

        }

        #[Route(path: '/admin456/group/{id}', name: 'admin_group')]
        public function adminGroupAction($id, Request $request)
        {
            $em = $this->getDoctrine()->getManager();

            $user = $em->getRepository(Group::class)->findOneById($id);
            $form = $this->createForm(GroupType::class, $user);
            $form->handleRequest($request);

            $logs = $em->getRepository(Log::class)->findByUser($user);


            if ($form->isSubmitted()) {
                $em->persist($user);
                $em->flush();
                return $this->redirectToRoute('admin_users');
            }
            return $this->render('base_form.html.twig', array(
                'form' => $form->createView(),
                'logs' => $logs,
                'user' => $user
            ));
        }

        #[Route(path: '/admin456/user/{id}', name: 'admin_user')]
        public function adminUserAction($id, Request $request)
        {
            $em = $this->getDoctrine()->getManager();

            $user = $em->getRepository(User::class)->findOneById($id);
            $form = $this->createForm(UserType::class, $user);
            $form->handleRequest($request);

            $logs = $em->getRepository(Log::class)->findByUser($user);


            if ($form->isSubmitted()) {
                $em->persist($user);
                $em->flush();
                return $this->redirectToRoute('admin_users');
            }
            return $this->render('Admin/user.html.twig', array(
                'form' => $form->createView(),
                'logs' => $logs,
                'user' => $user
            ));
        }

        #[Route(path: '/admin456/companies', name: 'admin_companies')]
        public function adminCompaniesAction(Request $request)
        {
            $em = $this->getDoctrine()->getManager();
            $companies = $em->getRepository(Company::class)->findAll();

            return $this->render('Admin/companies.html.twig', array(
                'companies' => $companies
            ));

        }

        #[Route(path: '/admin456/company/{id}', name: 'admin_company')]
        public function adminCompanyeAction($id, Request $request)
        {
            $em = $this->getDoctrine()->getManager();
            if($id == '0'){
                return;
            } else {
                $company = $em->getRepository(Company::class)->findOneById($id);
            }
            $form = $this->createForm(CompanyAdminType::class, $company);
            $form->handleRequest($request);


            if ($form->isSubmitted()) {
                $em->persist($company);
                $em->flush();
                return $this->redirectToRoute('admin_companies');
            }
            return $this->render('base_form.html.twig', array(
                'form' => $form->createView(),
            ));
        }

        #[Route(path: '/admin456/metacultures', name: 'admin_metacultures')]
        public function adminMetaCulturesAction(Request $request)
        {
            $em = $this->getDoctrine()->getManager();
            $metacultures = $em->getRepository(MetaCulture::class)->findAll();

            return $this->render('Admin/metacultures.html.twig', array(
                'metacultures' => $metacultures
            ));

        }

        #[Route(path: '/admin456/metaculture/{id}', name: 'admin_metaculture')]
        public function adminMetaCultureAction($id, Request $request)
        {
            $em = $this->getDoctrine()->getManager();
            if($id == '0'){
                $metaculture = new MetaCulture();
            } else {
                $metaculture = $em->getRepository(MetaCulture::class)->findOneById($id);
            }
            $form = $this->createForm(MetaCultureType::class, $metaculture);
            $form->handleRequest($request);


            if ($form->isSubmitted()) {
                $em->persist($metaculture);
                $em->flush();
                return $this->redirectToRoute('admin_metacultures');
            }
            return $this->render('base_form.html.twig', array(
                'form' => $form->createView(),
            ));
        }
}
