from sqlalchemy import Column, String, Float, Integer, ForeignKey
from sqlalchemy.orm import relationship
from database import Base


class Ilot(Base):
    __tablename__ = "ilot"

    id = Column(String(36), primary_key=True)
    company_id = Column(String(36), ForeignKey("company.id"), nullable=False)
    number = Column(Integer)
    surface = Column(Float)
    name = Column(String(255))
    typeSol = Column("typeSol", String(255))
    comment = Column(String(255))

    company = relationship("Company")
