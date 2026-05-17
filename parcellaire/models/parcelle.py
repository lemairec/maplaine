from sqlalchemy import Column, String, Float, Integer, Text, ForeignKey
from sqlalchemy.orm import relationship
from database import Base


class Parcelle(Base):
    __tablename__ = "parcelle"

    id = Column(String(36), primary_key=True)
    campagne_id = Column(String(36), ForeignKey("campagne.id"), nullable=False)
    ilot_id = Column(String(36), ForeignKey("ilot.id"))
    culture_id = Column(String(36), ForeignKey("culture.id"))
    active = Column(Integer, default=1)
    pacage = Column(String(45))
    precedent = Column(String(45))
    couvert = Column(String(45))
    ordre = Column(Integer)
    commune = Column(String(45))
    surface = Column(Float)
    name = Column(String(255))
    complete_name = Column("complete_name", String(255))
    import_ref = Column("import_ref", String(45))
    comment = Column(String(2048))
    geoJson = Column("geoJson", Text)

    campagne = relationship("Campagne")
    ilot = relationship("Ilot")
    culture = relationship("Culture")
