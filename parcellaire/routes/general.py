from fastapi import APIRouter, Depends
from sqlalchemy.orm import Session
from database import get_db
from models.company import Company
from models.campagne import Campagne
from schemas import CompanyOut, CampagneOut

router = APIRouter(prefix="/api", tags=["general"])


@router.get("/companies", response_model=list[CompanyOut])
def list_companies(db: Session = Depends(get_db)):
    return db.query(Company).all()


@router.get("/companies/{company_id}/campagnes", response_model=list[CampagneOut])
def list_campagnes(company_id: str, db: Session = Depends(get_db)):
    return (
        db.query(Campagne)
        .filter(Campagne.company_id == company_id)
        .order_by(Campagne.name.desc())
        .all()
    )
