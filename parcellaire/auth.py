import bcrypt
from itsdangerous import URLSafeTimedSerializer
from fastapi import Request, HTTPException, Depends
from sqlalchemy.orm import Session
from database import get_db
from models.user import User
from config import SECRET_KEY

serializer = URLSafeTimedSerializer(SECRET_KEY)
COOKIE_NAME = "session"
MAX_AGE = 60 * 60 * 24 * 30  # 30 days


def verify_password(plain: str, hashed: str) -> bool:
    """Verify a plaintext password against a Symfony bcrypt hash ($2y$...)."""
    # Symfony uses $2y$ prefix; bcrypt lib expects $2b$
    h = hashed.replace("$2y$", "$2b$", 1)
    return bcrypt.checkpw(plain.encode("utf-8"), h.encode("utf-8"))


def create_session_cookie(user_id: str) -> str:
    return serializer.dumps({"uid": user_id})


def get_session_user_id(request: Request) -> str:
    token = request.cookies.get(COOKIE_NAME)
    if not token:
        raise HTTPException(status_code=401, detail="Not authenticated")
    try:
        data = serializer.loads(token, max_age=MAX_AGE)
        return data["uid"]
    except Exception:
        raise HTTPException(status_code=401, detail="Invalid session")


def get_current_user(
    request: Request, db: Session = Depends(get_db)
) -> User:
    user_id = get_session_user_id(request)
    user = db.query(User).get(user_id)
    if not user or not user.enabled:
        raise HTTPException(status_code=401, detail="Not authenticated")
    return user
