import uuid
from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session
from database import get_db
from models.intervention import Intervention, InterventionParcelle, InterventionProduit
from models.produit import Produit
from schemas import InterventionOut, InterventionCreate, ProduitOut

router = APIRouter(prefix="/api", tags=["interventions"])


def _intervention_to_out(i: Intervention) -> dict:
    return {
        "id": i.id,
        "campagne_id": i.campagne_id,
        "company_id": i.company_id,
        "datetime": i.datetime,
        "type": i.type,
        "surface": i.surface,
        "name": i.name,
        "comment": i.comment,
        "parcelle_ids": [ip.parcelle_id for ip in i.parcelles],
        "produits": [
            {
                "produit_id": ip.produit_id,
                "name": ip.name,
                "quantity": ip.quantity,
                "price": ip.produit.price if ip.produit else 0,
                "unity": ip.produit.unity if ip.produit else "",
            }
            for ip in i.produits
        ],
    }


@router.get("/campagnes/{campagne_id}/interventions", response_model=list[InterventionOut])
def list_interventions(campagne_id: str, db: Session = Depends(get_db)):
    interventions = (
        db.query(Intervention)
        .filter(Intervention.campagne_id == campagne_id)
        .order_by(Intervention.datetime.desc())
        .all()
    )
    return [_intervention_to_out(i) for i in interventions]


@router.get("/parcelles/{parcelle_id}/interventions", response_model=list[InterventionOut])
def list_parcelle_interventions(parcelle_id: str, db: Session = Depends(get_db)):
    interventions = (
        db.query(Intervention)
        .join(InterventionParcelle)
        .filter(InterventionParcelle.parcelle_id == parcelle_id)
        .order_by(Intervention.datetime.desc())
        .all()
    )
    return [_intervention_to_out(i) for i in interventions]


@router.post("/interventions", response_model=InterventionOut)
def create_intervention(data: InterventionCreate, db: Session = Depends(get_db)):
    intervention = Intervention(
        id=str(uuid.uuid4()),
        campagne_id=data.campagne_id,
        company_id=data.company_id,
        datetime=data.datetime,
        type=data.type,
        surface=data.surface,
        name=data.name,
        comment=data.comment,
    )
    db.add(intervention)
    db.flush()

    for pl in data.parcelles:
        ip = InterventionParcelle(
            id=str(uuid.uuid4()),
            intervention_id=intervention.id,
            parcelle_id=pl.parcelle_id,
        )
        db.add(ip)

    for pr in data.produits:
        ipr = InterventionProduit(
            id=str(uuid.uuid4()),
            intervention_id=intervention.id,
            produit_id=pr.produit_id,
            name=pr.name,
            quantity=pr.quantity,
        )
        db.add(ipr)

    db.commit()
    db.refresh(intervention)
    return _intervention_to_out(intervention)


@router.delete("/interventions/{intervention_id}")
def delete_intervention(intervention_id: str, db: Session = Depends(get_db)):
    i = db.query(Intervention).get(intervention_id)
    if not i:
        raise HTTPException(404, "Intervention not found")
    # Delete linked records first
    db.query(InterventionParcelle).filter(InterventionParcelle.intervention_id == intervention_id).delete()
    db.query(InterventionProduit).filter(InterventionProduit.intervention_id == intervention_id).delete()
    db.delete(i)
    db.commit()
    return {"ok": True}


@router.get("/companies/{company_id}/produits", response_model=list[ProduitOut])
def list_produits(company_id: str, db: Session = Depends(get_db)):
    return (
        db.query(Produit)
        .filter(Produit.company_id == company_id, Produit.disable == False)
        .all()
    )
