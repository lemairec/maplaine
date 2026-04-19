from sqlalchemy import Column, String, Float, Boolean, ForeignKey
from sqlalchemy.orm import relationship
from database import Base


class Produit(Base):
    __tablename__ = "produit"

    id = Column(String(36), primary_key=True)
    company_id = Column(String(36), ForeignKey("company.id"), nullable=False)
    complete_name = Column("complete_name", String(255))
    name = Column(String(255))
    comment = Column(String(255))
    type = Column(String(255))
    bio = Column(Boolean, default=False)
    unity = Column(String(255))
    qty = Column("qty", Float, default=0)
    price = Column(Float, default=0)
    disable = Column(Boolean, default=False)
    n = Column("n", Float, default=0)
    p = Column("p", Float, default=0)
    k = Column("k", Float, default=0)
    mg = Column("mg", Float, default=0)
    s = Column("s", Float, default=0)

    company = relationship("Company")
