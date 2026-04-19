from sqlalchemy import Column, String, Float, Text, DateTime, Time, ForeignKey
from sqlalchemy.orm import relationship
from database import Base


class Intervention(Base):
    __tablename__ = "intervention"

    id = Column(String(36), primary_key=True)
    campagne_id = Column(String(36), ForeignKey("campagne.id"), nullable=False)
    company_id = Column(String(36), ForeignKey("company.id"), nullable=False)
    datetime = Column(DateTime)
    datetimeEnd = Column("datetimeEnd", DateTime)
    duration = Column(Time)
    type = Column(String(255))
    surface = Column(Float)
    name = Column(Text)
    comment = Column(Text)
    meteoJson = Column("meteoJson", Text)
    migration = Column(String(255))

    campagne = relationship("Campagne")
    company = relationship("Company")
    parcelles = relationship("InterventionParcelle", back_populates="intervention")
    produits = relationship("InterventionProduit", back_populates="intervention")


class InterventionParcelle(Base):
    __tablename__ = "intervention_parcelle"

    id = Column(String(36), primary_key=True)
    intervention_id = Column(String(36), ForeignKey("intervention.id"), nullable=False)
    parcelle_id = Column(String(36), ForeignKey("parcelle.id"), nullable=False)

    intervention = relationship("Intervention", back_populates="parcelles")
    parcelle = relationship("Parcelle")


class InterventionProduit(Base):
    __tablename__ = "intervention_produit"

    id = Column(String(36), primary_key=True)
    intervention_id = Column(String(36), ForeignKey("intervention.id"), nullable=False)
    produit_id = Column(String(36), ForeignKey("produit.id"), nullable=False)
    name = Column(String(255))
    quantity = Column(Float)

    intervention = relationship("Intervention", back_populates="produits")
    produit = relationship("Produit")
