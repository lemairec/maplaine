import json
import uuid
from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from database import get_db
from models.parcelle import Parcelle
from models.culture import Culture
from models.ilot import Ilot
from schemas import (
    ParcelleOut, ParcelleCreate, ParcelleUpdate,
    ParcelleSplit, ParcelleMerge, CultureOut, IlotOut,
)

router = APIRouter(prefix="/api", tags=["parcelles"])


def _parcelle_to_out(p: Parcelle) -> dict:
    return {
        "id": p.id,
        "campagne_id": p.campagne_id,
        "ilot_id": p.ilot_id,
        "culture_id": p.culture_id,
        "active": p.active,
        "name": p.name,
        "complete_name": p.complete_name,
        "surface": p.surface,
        "commune": p.commune,
        "comment": p.comment,
        "geoJson": p.geoJson,
        "culture_name": p.culture.name if p.culture else None,
        "culture_color": p.culture.color if p.culture else None,
        "ilot_name": p.ilot.name if p.ilot else None,
    }


@router.get("/campagnes/{campagne_id}/parcelles", response_model=list[ParcelleOut])
def list_parcelles(campagne_id: str, db: Session = Depends(get_db)):
    parcelles = (
        db.query(Parcelle)
        .filter(Parcelle.campagne_id == campagne_id, Parcelle.active == 1)
        .all()
    )
    return [_parcelle_to_out(p) for p in parcelles]


@router.get("/parcelles/{parcelle_id}", response_model=ParcelleOut)
def get_parcelle(parcelle_id: str, db: Session = Depends(get_db)):
    p = db.query(Parcelle).get(parcelle_id)
    if not p:
        raise HTTPException(404, "Parcelle not found")
    return _parcelle_to_out(p)


@router.post("/parcelles", response_model=ParcelleOut)
def create_parcelle(data: ParcelleCreate, db: Session = Depends(get_db)):
    p = Parcelle(
        id=str(uuid.uuid4()),
        campagne_id=data.campagne_id,
        ilot_id=data.ilot_id,
        culture_id=data.culture_id,
        active=1,
        name=data.name,
        complete_name=data.complete_name,
        surface=data.surface,
        commune=data.commune,
        comment=data.comment,
        geoJson=data.geoJson,
    )
    db.add(p)
    db.commit()
    db.refresh(p)
    return _parcelle_to_out(p)


@router.put("/parcelles/{parcelle_id}", response_model=ParcelleOut)
def update_parcelle(parcelle_id: str, data: ParcelleUpdate, db: Session = Depends(get_db)):
    p = db.query(Parcelle).get(parcelle_id)
    if not p:
        raise HTTPException(404, "Parcelle not found")
    for field, value in data.model_dump(exclude_unset=True).items():
        setattr(p, field, value)
    db.commit()
    db.refresh(p)
    return _parcelle_to_out(p)


@router.delete("/parcelles/{parcelle_id}")
def delete_parcelle(parcelle_id: str, db: Session = Depends(get_db)):
    p = db.query(Parcelle).get(parcelle_id)
    if not p:
        raise HTTPException(404, "Parcelle not found")
    p.active = 0
    db.commit()
    return {"ok": True}


@router.post("/parcelles/{parcelle_id}/split", response_model=list[ParcelleOut])
def split_parcelle(parcelle_id: str, data: ParcelleSplit, db: Session = Depends(get_db)):
    """Split a parcelle into two new ones. The original is deactivated."""
    original = db.query(Parcelle).get(parcelle_id)
    if not original:
        raise HTTPException(404, "Parcelle not found")

    # Deactivate original
    original.active = 0

    base = {
        "campagne_id": original.campagne_id,
        "ilot_id": original.ilot_id,
        "culture_id": original.culture_id,
        "active": 1,
        "commune": original.commune,
    }
    pa = Parcelle(id=str(uuid.uuid4()), name=data.name_a, complete_name=data.name_a,
                  surface=data.surface_a, geoJson=data.geoJson_a, **base)
    pb = Parcelle(id=str(uuid.uuid4()), name=data.name_b, complete_name=data.name_b,
                  surface=data.surface_b, geoJson=data.geoJson_b, **base)
    db.add_all([pa, pb])
    db.commit()
    db.refresh(pa)
    db.refresh(pb)
    return [_parcelle_to_out(pa), _parcelle_to_out(pb)]


@router.post("/parcelles/merge", response_model=ParcelleOut)
def merge_parcelles(data: ParcelleMerge, db: Session = Depends(get_db)):
    """Merge multiple parcelles into one. Originals are deactivated."""
    parcelles = db.query(Parcelle).filter(Parcelle.id.in_(data.parcelle_ids)).all()
    if len(parcelles) != len(data.parcelle_ids):
        raise HTTPException(400, "Some parcelles not found")

    # Take properties from first parcelle
    first = parcelles[0]
    for p in parcelles:
        p.active = 0

    merged = Parcelle(
        id=str(uuid.uuid4()),
        campagne_id=first.campagne_id,
        ilot_id=first.ilot_id,
        culture_id=first.culture_id,
        active=1,
        name=data.name,
        complete_name=data.name,
        surface=data.surface,
        commune=first.commune,
        geoJson=data.geoJson,
    )
    db.add(merged)
    db.commit()
    db.refresh(merged)
    return _parcelle_to_out(merged)


@router.get("/companies/{company_id}/cultures", response_model=list[CultureOut])
def list_cultures(company_id: str, db: Session = Depends(get_db)):
    return db.query(Culture).filter(Culture.company_id == company_id).all()


@router.get("/companies/{company_id}/ilots", response_model=list[IlotOut])
def list_ilots(company_id: str, db: Session = Depends(get_db)):
    return db.query(Ilot).filter(Ilot.company_id == company_id).all()
