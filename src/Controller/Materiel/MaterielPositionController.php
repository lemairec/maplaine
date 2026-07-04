<?php

namespace App\Controller\Materiel;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use DateTime;

use App\Controller\CommonController;
use App\Entity\Company;
use App\Entity\Campagne;
use App\Entity\Parcelle;
use App\Entity\User;
use App\Entity\Materiel\Materiel;
use App\Entity\Materiel\MaterielPosition;
use App\Entity\Materiel\MaterielTravail;
use App\Entity\Materiel\MaterielTravailParcelle;

class MaterielPositionController extends CommonController
{
    /**
     * POST /api/materiel/position
     *
     * Body JSON:
     *   user      : user GUID
     *   tracteur  : materiel GUID
     *   latitude  : float
     *   longitude : float
     *
     * The company is resolved from the parcelle the position falls into,
     * searched across all companies the user belongs to.
     */
    #[Route(path: '/api/materiel/position', name: 'api_materiel_position', methods: ['POST'])]
    public function position(Request $request): JsonResponse
    {
        $em = $this->getDoctrine()->getManager();
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        $user = $em->getRepository(User::class)->find($data['user'] ?? '');
        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        $materiel = $em->getRepository(Materiel::class)->find($data['tracteur'] ?? '');
        if (!$materiel) {
            return new JsonResponse(['error' => 'Materiel not found'], 404);
        }

        $lat = (float) ($data['latitude'] ?? 0);
        $lon = (float) ($data['longitude'] ?? 0);
        $now = new DateTime();
        $today = new DateTime('today');

        $company = null;
        $companyParcelles = [];
        foreach ($em->getRepository(Company::class)->getAllForUser($user) as $candidateCompany) {
            $campagnes = $em->getRepository(Campagne::class)->getAllforCompany($candidateCompany);
            if (empty($campagnes)) {
                continue;
            }

            foreach( $campagnes as $campagne) {
                if ($campagne->isActive()) {
                    $parcelles = $em->getRepository(Parcelle::class)->getAllForCampagneWithoutActive($campagne);
                
                    foreach ($parcelles as $parcelle) {
                        if (!$parcelle->geoJson) {
                            continue;
                        }
                        $geo = json_decode($parcelle->geoJson, true);
                        if (!$geo || !isset($geo['coordinates'])) {
                            continue;
                        }
                        if ($this->isPointInGeoJsonPolygon($lat, $lon, $geo)) {
                            $company = $candidateCompany;
                            $companyParcelles = $parcelles;
                            break 2;
                        }
                    }
                }
            }
        }

        if (!$company) {
            $company = $materiel->company;
        }

        $position = new MaterielPosition();
        $position->company = $company;
        $position->materiel = $materiel;
        $position->datetime = $now;
        $position->latitude = $lat;
        $position->longitude = $lon;
        $em->persist($position);

        $travail = $em->getRepository(MaterielTravail::class)->findByMaterielCompanyDate($materiel, $company, $today);
        if (!$travail) {
            $travail = new MaterielTravail();
            $travail->company = $company;
            $travail->materiel = $materiel;
            $travail->date = $today;
            $travail->datetimeBegin = $now;
            $travail->datetimeEnd = $now;
            $em->persist($travail);
            $em->flush();
        } else {
            $travail->datetimeEnd = $now;
            $em->persist($travail);
        }

        $alreadyLinked = [];
        foreach ($travail->parcelles as $tp) {
            $alreadyLinked[$tp->parcelle->id] = true;
        }

        foreach ($companyParcelles as $parcelle) {
            if (isset($alreadyLinked[$parcelle->id])) {
                continue;
            }
            if (!$parcelle->geoJson) {
                continue;
            }
            $geo = json_decode($parcelle->geoJson, true);
            if (!$geo || !isset($geo['coordinates'])) {
                continue;
            }
            if ($this->isPointInGeoJsonPolygon($lat, $lon, $geo)) {
                $tp = new MaterielTravailParcelle();
                $tp->travail = $travail;
                $tp->parcelle = $parcelle;
                $em->persist($tp);
                $alreadyLinked[$parcelle->id] = true;
            }
        }

        $em->flush();

        $parcellesResult = [];
        foreach ($travail->parcelles as $tp) {
            $parcellesResult[] = [
                'id'   => $tp->parcelle->id,
                'name' => $tp->parcelle->completeName,
            ];
        }

        return new JsonResponse([
            'travail_id'     => $travail->id,
            'date'           => $travail->date->format('Y-m-d'),
            'datetime_begin' => $travail->datetimeBegin->format('Y-m-d H:i:s'),
            'datetime_end'   => $travail->datetimeEnd->format('Y-m-d H:i:s'),
            'parcelles'      => $parcellesResult,
        ]);
    }

    /**
     * Ray-casting point-in-polygon on a GeoJSON Polygon or MultiPolygon.
     * GeoJSON coordinates are [longitude, latitude].
     */
    private function isPointInGeoJsonPolygon(float $lat, float $lon, array $geo): bool
    {
        if ($geo['type'] === 'Polygon') {
            return $this->isPointInRings($lat, $lon, $geo['coordinates']);
        }

        if ($geo['type'] === 'MultiPolygon') {
            foreach ($geo['coordinates'] as $rings) {
                if ($this->isPointInRings($lat, $lon, $rings)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isPointInRings(float $lat, float $lon, array $rings): bool
    {
        $exterior = $rings[0];
        if (!$this->raycast($lat, $lon, $exterior)) {
            return false;
        }
        // exclude holes
        for ($i = 1; $i < count($rings); $i++) {
            if ($this->raycast($lat, $lon, $rings[$i])) {
                return false;
            }
        }
        return true;
    }

    private function raycast(float $lat, float $lon, array $ring): bool
    {
        $n = count($ring);
        $inside = false;
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = $ring[$i][0]; // longitude
            $yi = $ring[$i][1]; // latitude
            $xj = $ring[$j][0];
            $yj = $ring[$j][1];
            if ((($yi > $lat) !== ($yj > $lat)) &&
                ($lon < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi)) {
                $inside = !$inside;
            }
        }
        return $inside;
    }
}
