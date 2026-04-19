from pydantic import BaseModel
from typing import Optional
from datetime import datetime


# --- Company ---
class CompanyOut(BaseModel):
    id: str
    name: str
    city: Optional[str] = None

    class Config:
        from_attributes = True


# --- Campagne ---
class CampagneOut(BaseModel):
    id: str
    company_id: str
    name: str
    color: Optional[str] = None
    anneeStart: Optional[int] = None

    class Config:
        from_attributes = True


# --- Culture ---
class CultureOut(BaseModel):
    id: str
    name: str
    color: Optional[str] = None
    codetelepac: Optional[str] = None

    class Config:
        from_attributes = True


# --- Ilot ---
class IlotOut(BaseModel):
    id: str
    name: str
    surface: Optional[float] = None
    typeSol: Optional[str] = None

    class Config:
        from_attributes = True


# --- Parcelle ---
class ParcelleOut(BaseModel):
    id: str
    campagne_id: str
    ilot_id: Optional[str] = None
    culture_id: Optional[str] = None
    active: int = 1
    name: Optional[str] = None
    complete_name: Optional[str] = None
    surface: Optional[float] = None
    commune: Optional[str] = None
    comment: Optional[str] = None
    geoJson: Optional[str] = None
    culture_name: Optional[str] = None
    culture_color: Optional[str] = None
    ilot_name: Optional[str] = None

    class Config:
        from_attributes = True


class ParcelleCreate(BaseModel):
    campagne_id: str
    ilot_id: Optional[str] = None
    culture_id: Optional[str] = None
    name: Optional[str] = None
    complete_name: str = ""
    surface: Optional[float] = None
    commune: Optional[str] = None
    comment: Optional[str] = None
    geoJson: Optional[str] = None


class ParcelleUpdate(BaseModel):
    ilot_id: Optional[str] = None
    culture_id: Optional[str] = None
    name: Optional[str] = None
    complete_name: Optional[str] = None
    surface: Optional[float] = None
    commune: Optional[str] = None
    comment: Optional[str] = None
    geoJson: Optional[str] = None
    active: Optional[int] = None


class ParcelleSplit(BaseModel):
    """Split a parcelle into two by providing two GeoJSON geometries."""
    geoJson_a: str
    geoJson_b: str
    surface_a: float
    surface_b: float
    name_a: str
    name_b: str


class ParcelleMerge(BaseModel):
    """Merge multiple parcelles into one."""
    parcelle_ids: list[str]
    name: str
    geoJson: str
    surface: float


# --- Produit ---
class ProduitOut(BaseModel):
    id: str
    name: str
    complete_name: Optional[str] = None
    type: str
    unity: Optional[str] = None
    price: float = 0

    class Config:
        from_attributes = True


# --- Intervention ---
class InterventionParcelleLinkIn(BaseModel):
    parcelle_id: str


class InterventionProduitLinkIn(BaseModel):
    produit_id: str
    name: str
    quantity: float


class InterventionOut(BaseModel):
    id: str
    campagne_id: str
    company_id: str
    datetime: Optional[datetime] = None
    type: str
    surface: float
    name: Optional[str] = None
    comment: Optional[str] = None
    parcelle_ids: list[str] = []
    produits: list[dict] = []

    class Config:
        from_attributes = True


class InterventionCreate(BaseModel):
    campagne_id: str
    company_id: str
    datetime: datetime
    type: str
    surface: float
    name: Optional[str] = None
    comment: Optional[str] = None
    parcelles: list[InterventionParcelleLinkIn] = []
    produits: list[InterventionProduitLinkIn] = []
