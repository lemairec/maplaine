<?php

namespace App\Controller;

use App\Entity\Culture;
use App\Entity\Ilot;
use App\Entity\Parcelle;
use App\Service\TelepacParser;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

class ImportTelepacController extends CommonController
{
    public function __construct(
        \Symfony\Component\HttpFoundation\RequestStack $requestStack,
        Security $security,
        ManagerRegistry $doctrine,
        private TelepacParser $parser,
    ) {
        parent::__construct($requestStack, $security, $doctrine);
    }

    #[Route(path: '/import-pac', name: 'import_pac')]
    public function page(Request $request): Response
    {
        $campagne = $this->getCurrentCampagne($request);
        $em = $this->getDoctrine()->getManager();

        $cultures = $em->getRepository(Culture::class)->getAllforCompany($this->company);
        $campagnes = $this->campagnes;

        return $this->render('Default/import_pac.html.twig', [
            'cultures'   => $cultures,
            'campagnes'  => $campagnes,
            'campagne_id' => $this->getCurrentCampagne($request)->id,
            'navs'       => ['Import PAC' => 'import_pac'],
        ]);
    }

    #[Route(path: '/import-pac/preview', name: 'import_pac_preview', methods: ['POST'])]
    public function preview(Request $request): JsonResponse
    {
        $this->check_user($request);
        $em = $this->getDoctrine()->getManager();

        $file = $request->files->get('file');
        if (!$file || strtolower($file->getClientOriginalExtension()) !== 'xml') {
            return $this->json(['error' => 'Veuillez envoyer un fichier XML TeléPAC.'], 400);
        }

        try {
            $content = file_get_contents($file->getPathname());
            $data    = $this->parser->parse($content);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Erreur de lecture : ' . $e->getMessage()], 400);
        }

        // Correspondance cultures par codetelepac
        $cultures        = $em->getRepository(Culture::class)->getAllforCompany($this->company);
        $culturesByCode  = [];
        foreach ($cultures as $c) {
            if ($c->codetelepac) {
                $culturesByCode[$c->codetelepac] = $c;
            }
        }

        $allCodes = [];
        foreach ($data['ilots'] as $ilot) {
            foreach ($ilot['parcelles'] as $p) {
                if ($p['code_culture']) {
                    $allCodes[$p['code_culture']] = true;
                }
            }
        }

        $cultureMap = [];
        foreach (array_keys($allCodes) as $code) {
            $c = $culturesByCode[$code] ?? null;
            $cultureMap[$code] = $c ? [
                'id'          => (string) $c->id,
                'name'        => $c->name,
                'color'       => $c->color,
                'codetelepac' => $c->codetelepac,
            ] : null;
        }

        $nbParcelles = array_sum(array_map(fn($i) => count($i['parcelles']), $data['ilots']));

        return $this->json([
            'pacage'         => $data['pacage'],
            'exploitation'   => $data['exploitation'],
            'campagne_label' => $data['campagne_label'],
            'culture_map'    => $cultureMap,
            'ilots'          => $data['ilots'],
            'stats'          => [
                'nb_ilots'              => count($data['ilots']),
                'nb_parcelles'          => $nbParcelles,
                'nb_cultures_matched'   => count(array_filter($cultureMap)),
                'nb_cultures_unmatched' => count(array_filter($cultureMap, fn($v) => $v === null)),
            ],
        ]);
    }

    #[Route(path: '/import-pac/confirm', name: 'import_pac_confirm', methods: ['POST'])]
    public function confirm(Request $request): JsonResponse
    {
        $this->check_user($request);
        $em   = $this->getDoctrine()->getManager();
        $data = json_decode($request->getContent(), true);

        $campagneId      = $data['campagne_id']      ?? null;
        $ilots           = $data['ilots']            ?? [];
        $replaceExisting = $data['replace_existing'] ?? false;

        // Récupérer la campagne
        $campagne = $em->getRepository(\App\Entity\Campagne::class)->find($campagneId);
        if (!$campagne || (string) $campagne->company->id !== (string) $this->company->id) {
            return $this->json(['error' => 'Campagne introuvable.'], 404);
        }

        // Désactiver les parcelles existantes si demandé
        if ($replaceExisting) {
            $em->createQuery(
                'UPDATE App\Entity\Parcelle p SET p.active = 0 WHERE p.campagne = :c AND p.active = 1'
            )->setParameter('c', $campagne)->execute();
        }

        // Index cultures par codetelepac
        $cultures       = $em->getRepository(Culture::class)->getAllforCompany($this->company);
        $culturesByCode = [];
        foreach ($cultures as $c) {
            if ($c->codetelepac) $culturesByCode[$c->codetelepac] = $c;
        }

        // Index îlots existants par numéro
        $existingIlots  = $em->getRepository(Ilot::class)->getAllforCompany($this->company);
        $ilotsByNumber  = [];
        foreach ($existingIlots as $i) {
            $ilotsByNumber[(int) $i->number] = $i;
        }

        $createdIlots      = 0;
        $createdParcelles  = 0;
        $unmatchedCultures = [];

        foreach ($ilots as $ilotData) {
            $numero = (int) $ilotData['numero_ilot'];

            if (isset($ilotsByNumber[$numero])) {
                $ilot = $ilotsByNumber[$numero];
            } else {
                $totalSurface = array_sum(array_column($ilotData['parcelles'], 'surface_ha'));
                $ilot = new Ilot();
                $ilot->company  = $this->company;
                $ilot->number   = $numero;
                $ilot->name     = 'Îlot ' . $numero;
                $ilot->surface  = $totalSurface;
                $ilot->typeSol  = '';
                $em->persist($ilot);
                $em->flush();
                $ilotsByNumber[$numero] = $ilot;
                $createdIlots++;
            }

            foreach ($ilotData['parcelles'] as $parcData) {
                $code    = $parcData['code_culture'] ?? '';
                $culture = $code ? ($culturesByCode[$code] ?? null) : null;
                if ($code && !$culture) {
                    $unmatchedCultures[$code] = true;
                }

                $label = sprintf('I%d-P%d', $numero, $parcData['numero_parcelle']);

                $parcelle = new Parcelle();
                $parcelle->campagne     = $campagne;
                $parcelle->ilot         = $ilot;
                $parcelle->culture      = $culture;
                $parcelle->active       = 1;
                $parcelle->name         = $label;
                $parcelle->completeName = $label;
                $parcelle->surface      = $parcData['surface_ha'];
                $parcelle->commune      = $ilotData['commune'] ?? null;
                $parcelle->pacage       = $data['pacage']      ?? null;
                $parcelle->geoJson      = $parcData['geojson']
                    ? json_encode($parcData['geojson'])
                    : null;

                $em->persist($parcelle);
                $createdParcelles++;
            }
        }

        $em->flush();

        return $this->json([
            'ok'                 => true,
            'created_ilots'      => $createdIlots,
            'created_parcelles'  => $createdParcelles,
            'unmatched_cultures' => array_keys($unmatchedCultures),
            'redirect'           => $this->generateUrl('parcelles'),
        ]);
    }
}
