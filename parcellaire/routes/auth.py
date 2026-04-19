from fastapi import APIRouter, Depends, HTTPException, Response
from fastapi.responses import JSONResponse
from sqlalchemy.orm import Session
from pydantic import BaseModel
from database import get_db
from models.user import User
from auth import verify_password, create_session_cookie, COOKIE_NAME, get_current_user

router = APIRouter(tags=["auth"])


class LoginRequest(BaseModel):
    username: str
    password: str


@router.post("/api/login")
def login(data: LoginRequest, response: Response, db: Session = Depends(get_db)):
    user = db.query(User).filter(User.username == data.username).first()
    if not user or not user.enabled:
        raise HTTPException(status_code=401, detail="Identifiants invalides")
    if not verify_password(data.password, user.password):
        raise HTTPException(status_code=401, detail="Identifiants invalides")

    cookie = create_session_cookie(user.id)
    resp = JSONResponse({"ok": True, "username": user.username})
    resp.set_cookie(
        COOKIE_NAME, cookie,
        httponly=True,
        samesite="lax",
        max_age=60 * 60 * 24 * 30,
    )
    return resp


@router.post("/api/logout")
def logout():
    resp = JSONResponse({"ok": True})
    resp.delete_cookie(COOKIE_NAME)
    return resp


@router.get("/api/me")
def me(user: User = Depends(get_current_user)):
    return {
        "id": user.id,
        "username": user.username,
        "company_ids": [c.id for c in user.companies],
    }
