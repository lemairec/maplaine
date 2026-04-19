from sqlalchemy import Column, String, Integer, ForeignKey
from sqlalchemy.orm import relationship
from database import Base


class Campagne(Base):
    __tablename__ = "campagne"

    id = Column(String(36), primary_key=True)
    company_id = Column(String(36), ForeignKey("company.id"), nullable=False)
    name = Column(String(255), nullable=False)
    commercialisation = Column(String(255))
    color = Column(String(25))
    anneeStart = Column("anneeStart", Integer)

    company = relationship("Company")
