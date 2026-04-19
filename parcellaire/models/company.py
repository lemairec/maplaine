from sqlalchemy import Column, String, Table, ForeignKey
from sqlalchemy.orm import relationship
from database import Base

company_user = Table(
    "_fos_user_user_company",
    Base.metadata,
    Column("user_id", String(36), ForeignKey("company.id"), primary_key=True),
    Column("company_id", String(36), ForeignKey("_fos_user.id"), primary_key=True),
)


class Company(Base):
    __tablename__ = "company"

    id = Column(String(36), primary_key=True)
    name = Column(String(255), nullable=False)
    adresse = Column(String(255))
    city = Column(String(255))
    city_code = Column(String(255))
    site1_name = Column(String(255))
    site1_url = Column(String(255))

    users = relationship("User", secondary=company_user, back_populates="companies")
