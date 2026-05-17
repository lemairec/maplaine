import json
import traceback
import uuid

from fastapi import APIRouter, Depends, File, Form, HTTPException, Request, UploadFile
from fastapi.responses import HTMLResponse
from fastapi.templating import Jinja2Templates
from pydantic import BaseModel
from sqlalchemy.orm import Session
from typing import Optional

from database import get_db
from models.campagne import Campagne
from models.culture import Culture
from models.ilot import Ilot
from models.parcelle import Parcelle
from services.telepac import parse_telepac

router = APIRouter(tags=["import"])
templates = Jinja2Templates(directory="templates")

# ---------------------------------------------------------------------------
# Page HTML
# ---------------------------------------------------------------------------


@router.get("/import", response_class=HTMLResponse)
def import_page(request: Request):
    return templates.TemplateResponse("import.html", {"request": request})


# ---------------------------------------------------------------------------
# API : prévisualisation
# ---------------------------------------------------------------------------


@router.post("/api/import/telepac/preview")
async def preview_telepac(
    file: UploadFile = File(...),
    company_id: Optional[str] = Form(None),
    db: Session = Depends(get_db),
):
    """
    Parse le fichier XML et retourne les données pour prévisualisation.
    Vérifie aussi les correspondances de culture (codetelepac).
    """
    if not file.filename or not file.filename.lower().endswith(".xml"):
        raise HTTPException(400, "Veuillez envoyer un fichier XML TeléPAC.")

    try:
        content = await file.read()
        try:
            data = parse_telepac(content)
        except Exception as exc:
            raise HTTPException(400, f"Erreur de lecture du fichier XML : {exc}")

        # Correspondances cultures
        culture_map: dict[str, dict | None] = {}
        if company_id:
            cultures = db.query(Culture).filter(Culture.company_id == company_id).all()
            cultures_by_code = {c.codetelepac: c for c in cultures if c.codetelepac}
        else:
            cultures_by_code = {}

        all_codes = {
            p["code_culture"]
            for ilot in data["ilots"]
            for p in ilot["parcelles"]
            if p["code_culture"]
        }

        for code in all_codes:
            c = cultures_by_code.get(code)
            culture_map[code] = (
                {"id": c.id, "name": c.name, "color": c.color, "codetelepac": c.codetelepac}
                if c
                else None
            )

        return {
            "pacage": data["pacage"],
            "exploitation": data["exploitation"],
            "campagne_label": data["campagne_label"],
            "culture_map": culture_map,
            "ilots": data["ilots"],
            "stats": {
                "nb_ilots": len(data["ilots"]),
                "nb_parcelles": sum(len(i["parcelles"]) for i in data["ilots"]),
                "nb_cultures_matched": sum(1 for v in culture_map.values() if v),
                "nb_cultures_unmatched": sum(1 for v in culture_map.values() if not v),
            },
        }
    except HTTPException:
        raise
    except Exception as exc:
        tb = traceback.format_exc()
        raise HTTPException(500, f"Erreur interne : {exc}\n\n{tb}")


# ---------------------------------------------------------------------------
# API : import
# ---------------------------------------------------------------------------


class ImportParcelleIn(BaseModel):
    numero_parcelle: int
    code_culture: str
    surface_ha: float
    geojson: dict | None = None


class ImportIlotIn(BaseModel):
    numero_ilot: int
    numero_reference: str
    commune: str
    parcelles: list[ImportParcelleIn]


class ImportConfirmIn(BaseModel):
    company_id: str
    campagne_id: str
    ilots: list[ImportIlotIn]
    replace_existing: bool = False  # si True, désactive les parcelles existantes de la campagne


@router.post("/api/import/telepac/confirm")
def confirm_import(data: ImportConfirmIn, db: Session = Depends(get_db)):
    """
    Importe les îlots et parcelles dans la base de données.
    - Crée ou réutilise les îlots existants (par numero_ilot + company).
    - Matche chaque parcelle sur la culture via codetelepac.
    - Retourne un résumé des entités créées.
    """
    # Vérifications de base
    campagne = db.query(Campagne).get(data.campagne_id)
    if not campagne or campagne.company_id != data.company_id:
        raise HTTPException(404, "Campagne introuvable pour cette société.")

    if data.replace_existing:
        db.query(Parcelle).filter(
            Parcelle.campagne_id == data.campagne_id, Parcelle.active == 1
        ).update({"active": 0})

    # Cultures indexées par codetelepac
    cultures = db.query(Culture).filter(Culture.company_id == data.company_id).all()
    cultures_by_code = {c.codetelepac: c for c in cultures if c.codetelepac}

    # Îlots existants pour la société, indexés par numero
    existing_ilots = (
        db.query(Ilot).filter(Ilot.company_id == data.company_id).all()
    )
    ilots_by_number = {i.number: i for i in existing_ilots}

    created_ilots = 0
    created_parcelles = 0
    unmatched_cultures: set[str] = set()

    for ilot_in in data.ilots:
        # Récupère ou crée l'îlot
        ilot = ilots_by_number.get(ilot_in.numero_ilot)
        if ilot is None:
            ilot = Ilot(
                id=str(uuid.uuid4()),
                company_id=data.company_id,
                number=ilot_in.numero_ilot,
                name=f"Îlot {ilot_in.numero_ilot}",
                surface=sum(p.surface_ha for p in ilot_in.parcelles),
                typeSol="inconnu"
            )
            db.add(ilot)
            db.flush()
            ilots_by_number[ilot_in.numero_ilot] = ilot
            created_ilots += 1

        for parc_in in ilot_in.parcelles:
            culture = cultures_by_code.get(parc_in.code_culture)
            if not culture and parc_in.code_culture:
                unmatched_cultures.add(parc_in.code_culture)

            import_ref = f"i{ilot_in.numero_ilot}_p{parc_in.numero_parcelle}"
            name = f"import_{import_ref}"
            parcelle = Parcelle(
                id=str(uuid.uuid4()),
                campagne_id=data.campagne_id,
                ilot_id=ilot.id,
                culture_id=culture.id if culture else None,
                active=1,
                name=name,
                complete_name=name,
                surface=parc_in.surface_ha,
                commune=ilot_in.commune,
                import_ref=import_ref,
                geoJson=json.dumps(parc_in.geojson) if parc_in.geojson else None,
            )
            db.add(parcelle)
            created_parcelles += 1

    db.commit()

    return {
        "ok": True,
        "created_ilots": created_ilots,
        "created_parcelles": created_parcelles,
        "unmatched_cultures": sorted(unmatched_cultures),
    }
