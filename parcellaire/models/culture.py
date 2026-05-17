from sqlalchemy import Column, String, Float, ForeignKey
from sqlalchemy.orm import relationship
from database import Base


class Culture(Base):
    __tablename__ = "culture"

    id = Column(String(36), primary_key=True)
    company_id = Column(String(36), ForeignKey("company.id"), nullable=False)
    name = Column(String(255), nullable=False)
    codetelepac = Column(String(45))
    color = Column(String(25))
    commercialisation = Column(String(255))
    rendement_obj = Column("rendement_obj", Float)
    prix_obj = Column("prix_obj", Float)

    company = relationship("Company")
