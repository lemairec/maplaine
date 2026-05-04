"""
Service de parsing des fichiers TeléPAC (format XML TELEPAC).

Les coordonnées GML sont en Lambert-93 (EPSG:2154) et sont converties en
WGS84 (EPSG:4326) pour le stockage GeoJSON.
"""
import xml.etree.ElementTree as ET
from pyproj import Transformer

NS_TL = "urn:x-telepac:fr.gouv.agriculture.telepac:echange-producteur"
NS_GML = "http://www.opengis.net/gml"

_transformer = Transformer.from_crs("EPSG:2154", "EPSG:4326", always_xy=True)


def _t(local: str) -> str:
    return f"{{{NS_TL}}}{local}"


def _gml(local: str) -> str:
    return f"{{{NS_GML}}}{local}"


def _parse_ring(coords_elem) -> list:
    """Convertit une liste de coordonnées Lambert-93 en liste [lon, lat]."""
    if coords_elem is None or not coords_elem.text:
        return []
    ring = []
    for pair in coords_elem.text.split():
        parts = pair.split(",")
        if len(parts) >= 2:
            x, y = float(parts[0]), float(parts[1])
            lon, lat = _transformer.transform(x, y)
            ring.append([round(lon, 7), round(lat, 7)])
    return ring


def _polygon_to_geojson(polygon_elem) -> dict | None:
    """Transforme un élément gml:Polygon en géométrie GeoJSON."""
    if polygon_elem is None:
        return None
    rings = []
    outer = polygon_elem.find(
        f"{_gml('outerBoundaryIs')}/{_gml('LinearRing')}/{_gml('coordinates')}"
    )
    ring = _parse_ring(outer)
    if not ring:
        return None
    rings.append(ring)
    # Trous éventuels
    for inner_elem in polygon_elem.findall(
        f"{_gml('innerBoundaryIs')}/{_gml('LinearRing')}/{_gml('coordinates')}"
    ):
        inner = _parse_ring(inner_elem)
        if inner:
            rings.append(inner)
    return {"type": "Polygon", "coordinates": rings}


def parse_telepac(content: bytes) -> dict:
    """
    Parse un fichier XML TeléPAC et retourne les données structurées.

    Retourne un dict avec :
      pacage        : numéro PACAGE
      exploitation  : nom de l'exploitation
      campagne_label: libellé campagne (ex. "Courante" ou "2026")
      ilots         : liste de dicts ilot :
          numero_ilot      : int
          numero_reference : str
          commune          : str
          ilot_geojson     : dict GeoJSON Polygon (ou None)
          parcelles        : liste de dicts parcelle :
              numero_parcelle : int
              code_culture    : str  (ex. "TRN", "CAG")
              surface_ha      : float (surface admissible en ha)
              geojson         : dict GeoJSON Polygon (ou None)
    """
    root = ET.fromstring(content)

    producteur = root.find(_t("producteur"))
    if producteur is None:
        raise ValueError("Fichier XML invalide : élément <producteur> introuvable.")

    pacage = producteur.get("numero-pacage", "")
    campagne_label = producteur.get("campagne", "")

    exploitation = ""
    demandeur = producteur.find(_t("demandeur"))
    if demandeur is not None:
        id_soc = demandeur.find(_t("identification-societe"))
        if id_soc is not None:
            expl_elem = id_soc.find(_t("exploitation"))
            if expl_elem is not None:
                exploitation = (expl_elem.text or "").strip()

    ilots = []
    rpg = producteur.find(_t("rpg"))
    if rpg is None:
        raise ValueError("Fichier XML invalide : élément <rpg> introuvable.")

    for ilot_elem in rpg.findall(_t("ilot")):
        numero_ilot = int(ilot_elem.get("numero-ilot", 0))
        numero_reference = ilot_elem.get("numero-ilot-reference", "")

        commune_elem = ilot_elem.find(_t("commune"))
        commune = (commune_elem.text or "").strip() if commune_elem is not None else ""

        ilot_geom = ilot_elem.find(f"{_t('geometrie')}/{_gml('Polygon')}")
        ilot_geojson = _polygon_to_geojson(ilot_geom)

        parcelles = []
        parcelles_container = ilot_elem.find(_t("parcelles"))
        if parcelles_container is not None:
            for parc_elem in parcelles_container.findall(_t("parcelle")):
                desc = parc_elem.find(_t("descriptif-parcelle"))
                numero_parcelle = int(desc.get("numero-parcelle", 0)) if desc is not None else 0

                code_culture = ""
                if desc is not None:
                    cult_princ = desc.find(_t("culture-principale"))
                    if cult_princ is not None:
                        cc_elem = cult_princ.find(_t("code-culture"))
                        if cc_elem is not None:
                            code_culture = (cc_elem.text or "").strip()

                surf_elem = parc_elem.find(_t("surface-admissible"))
                surface_ares = float(surf_elem.text) if surf_elem is not None and surf_elem.text else 0.0
                surface_ha = round(surface_ares / 100.0, 4)

                parc_geom = parc_elem.find(f"{_t('geometrie')}/{_gml('Polygon')}")
                parc_geojson = _polygon_to_geojson(parc_geom)

                parcelles.append(
                    {
                        "numero_parcelle": numero_parcelle,
                        "code_culture": code_culture,
                        "surface_ha": surface_ha,
                        "geojson": parc_geojson,
                    }
                )

        ilots.append(
            {
                "numero_ilot": numero_ilot,
                "numero_reference": numero_reference,
                "commune": commune,
                "ilot_geojson": ilot_geojson,
                "parcelles": parcelles,
            }
        )

    return {
        "pacage": pacage,
        "exploitation": exploitation,
        "campagne_label": campagne_label,
        "ilots": ilots,
    }
