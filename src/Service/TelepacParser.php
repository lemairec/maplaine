<?php

namespace App\Service;

/**
 * Parse les fichiers XML TeléPAC et convertit les coordonnées
 * Lambert-93 (EPSG:2154) en WGS84 (EPSG:4326).
 */
class TelepacParser
{
    private const NS_TL  = 'urn:x-telepac:fr.gouv.agriculture.telepac:echange-producteur';
    private const NS_GML = 'http://www.opengis.net/gml';

    // Constantes IGN pour Lambert-93 → WGS84
    private const L93_N  = 0.7256077650532670;
    private const L93_C  = 11754255.4261;
    private const L93_XS = 700000.0;
    private const L93_YS = 12655612.0499;
    private const L93_E  = 0.0818191910428158; // excentricité GRS80

    public function parse(string $xmlContent): array
    {
        $root = new \SimpleXMLElement($xmlContent);
        $root->registerXPathNamespace('tl',  self::NS_TL);
        $root->registerXPathNamespace('gml', self::NS_GML);

        $producteur = $root->children(self::NS_TL)->producteur;
        if (!$producteur) {
            throw new \RuntimeException('Fichier XML invalide : élément <producteur> introuvable.');
        }

        $pacage        = (string) ($producteur->attributes()['numero-pacage'] ?? '');
        $campagneLabel = (string) ($producteur->attributes()['campagne']      ?? '');
        $exploitation  = '';

        $demandeur = $producteur->children(self::NS_TL)->demandeur;
        if ($demandeur) {
            $idSoc = $demandeur->children(self::NS_TL)->{'identification-societe'};
            if ($idSoc) {
                $exploitation = trim((string) ($idSoc->children(self::NS_TL)->exploitation ?? ''));
            }
        }

        $rpg = $producteur->children(self::NS_TL)->rpg;
        if (!$rpg) {
            throw new \RuntimeException('Fichier XML invalide : élément <rpg> introuvable.');
        }

        $ilots = [];
        foreach ($rpg->children(self::NS_TL)->ilot as $ilotElem) {
            $ilots[] = $this->parseIlot($ilotElem);
        }

        return [
            'pacage'         => $pacage,
            'exploitation'   => $exploitation,
            'campagne_label' => $campagneLabel,
            'ilots'          => $ilots,
        ];
    }

    private function parseIlot(\SimpleXMLElement $ilotElem): array
    {
        $attrs        = $ilotElem->attributes();
        $numeroIlot   = (int)    ($attrs['numero-ilot']           ?? 0);
        $numeroRef    = (string) ($attrs['numero-ilot-reference'] ?? '');
        $communeElem  = $ilotElem->children(self::NS_TL)->commune;
        $commune      = trim((string) ($communeElem ?? ''));

        $ilotGeomElem = $ilotElem->children(self::NS_TL)->geometrie;
        $ilotGeojson  = null;
        if ($ilotGeomElem) {
            $polygon = $ilotGeomElem->children(self::NS_GML)->Polygon;
            $ilotGeojson = $this->polygonToGeojson($polygon ?: null);
        }

        $parcelles = [];
        $parcellesContainer = $ilotElem->children(self::NS_TL)->parcelles;
        if ($parcellesContainer) {
            foreach ($parcellesContainer->children(self::NS_TL)->parcelle as $parcElem) {
                $parcelles[] = $this->parseParcelle($parcElem, $commune);
            }
        }

        return [
            'numero_ilot'      => $numeroIlot,
            'numero_reference' => $numeroRef,
            'commune'          => $commune,
            'ilot_geojson'     => $ilotGeojson,
            'parcelles'        => $parcelles,
        ];
    }

    private function parseParcelle(\SimpleXMLElement $parcElem, string $commune): array
    {
        $desc           = $parcElem->children(self::NS_TL)->{'descriptif-parcelle'};
        $numeroParcelle = $desc ? (int) ($desc->attributes()['numero-parcelle'] ?? 0) : 0;

        $codeCulture = '';
        if ($desc) {
            $cultPrinc = $desc->children(self::NS_TL)->{'culture-principale'};
            if ($cultPrinc) {
                $codeCulture = trim((string) ($cultPrinc->children(self::NS_TL)->{'code-culture'} ?? ''));
            }
        }

        $surfElem  = $parcElem->children(self::NS_TL)->{'surface-admissible'};
        $surfAres  = $surfElem ? (float) (string) $surfElem : 0.0;
        $surfaceHa = round($surfAres / 100.0, 4);

        $geomElem = $parcElem->children(self::NS_TL)->geometrie;
        $geojson  = null;
        if ($geomElem) {
            $polygon = $geomElem->children(self::NS_GML)->Polygon;
            $geojson = $this->polygonToGeojson($polygon ?: null);
        }

        return [
            'numero_parcelle' => $numeroParcelle,
            'code_culture'    => $codeCulture,
            'surface_ha'      => $surfaceHa,
            'commune'         => $commune,
            'geojson'         => $geojson,
        ];
    }

    private function polygonToGeojson(?\SimpleXMLElement $polygon): ?array
    {
        if (!$polygon) return null;

        $rings = [];

        $outer = $polygon->children(self::NS_GML)->outerBoundaryIs
            ->children(self::NS_GML)->LinearRing
            ->children(self::NS_GML)->coordinates;
        $ring = $this->parseRing((string) ($outer ?? ''));
        if (!$ring) return null;
        $rings[] = $ring;

        foreach ($polygon->children(self::NS_GML)->innerBoundaryIs as $inner) {
            $coords = $inner->children(self::NS_GML)->LinearRing
                ->children(self::NS_GML)->coordinates;
            $innerRing = $this->parseRing((string) ($coords ?? ''));
            if ($innerRing) $rings[] = $innerRing;
        }

        return ['type' => 'Polygon', 'coordinates' => $rings];
    }

    private function parseRing(string $coordsText): array
    {
        $ring = [];
        foreach (preg_split('/\s+/', trim($coordsText)) as $pair) {
            if (!$pair) continue;
            $parts = explode(',', $pair);
            if (count($parts) < 2) continue;
            [$lon, $lat] = $this->lambert93ToWgs84((float) $parts[0], (float) $parts[1]);
            $ring[] = [$lon, $lat];
        }
        return $ring;
    }

    /**
     * Conversion Lambert-93 (EPSG:2154) → WGS84 (EPSG:4326).
     * Utilise les constantes officielles IGN.
     *
     * @return array [longitude, latitude] en degrés décimaux
     */
    private function lambert93ToWgs84(float $x, float $y): array
    {
        $n  = self::L93_N;
        $c  = self::L93_C;
        $xs = self::L93_XS;
        $ys = self::L93_YS;
        $e  = self::L93_E;

        $deltaX = $x - $xs;
        $deltaY = $ys - $y;

        $r     = sqrt($deltaX ** 2 + $deltaY ** 2);
        $gamma = atan2($deltaX, $deltaY);

        $lambda = deg2rad(3.0) + $gamma / $n; // longitude (rad)

        $latIso = -log(abs($r / $c)) / $n;

        // Itération pour passer de la latitude isométrique à la latitude géographique
        $phi = 2.0 * atan(exp($latIso)) - M_PI / 2.0;
        for ($i = 0; $i < 100; $i++) {
            $prev     = $phi;
            $eSinPhi  = $e * sin($phi);
            $phi      = 2.0 * atan(
                exp($latIso) * ((1.0 + $eSinPhi) / (1.0 - $eSinPhi)) ** ($e / 2.0)
            ) - M_PI / 2.0;
            if (abs($phi - $prev) < 1e-11) break;
        }

        return [round(rad2deg($lambda), 7), round(rad2deg($phi), 7)];
    }
}
