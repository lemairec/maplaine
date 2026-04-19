from sqlalchemy import Column, String, Boolean
from sqlalchemy.orm import relationship
from database import Base


class User(Base):
    __tablename__ = "_fos_user"

    id = Column(String(36), primary_key=True)
    username = Column(String(180), unique=True, nullable=False)
    username_canonical = Column(String(180), unique=True)
    email = Column(String(180))
    email_canonical = Column(String(180))
    enabled = Column(Boolean, default=True)
    password = Column(String(255))
    roles = Column(String(1024))

    companies = relationship(
        "Company",
        secondary="company_user",
        back_populates="users",
    )
