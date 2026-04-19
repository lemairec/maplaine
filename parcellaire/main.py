from fastapi import FastAPI, Request
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
from fastapi.responses import RedirectResponse
from starlette.middleware.base import BaseHTTPMiddleware
from routes.general import router as general_router
from routes.parcelles import router as parcelles_router
from routes.interventions import router as interventions_router
from routes.auth import router as auth_router
from auth import get_session_user_id

app = FastAPI(title="Gestion Parcellaire")

app.mount("/static", StaticFiles(directory="static"), name="static")
templates = Jinja2Templates(directory="templates")


class AuthMiddleware(BaseHTTPMiddleware):
    OPEN_PATHS = {"/login", "/api/login", "/api/logout"}

    async def dispatch(self, request: Request, call_next):
        path = request.url.path
        if path.startswith("/static") or path in self.OPEN_PATHS:
            return await call_next(request)
        try:
            get_session_user_id(request)
        except Exception:
            if path.startswith("/api/"):
                from fastapi.responses import JSONResponse
                return JSONResponse({"detail": "Not authenticated"}, status_code=401)
            return RedirectResponse("/login")
        return await call_next(request)


app.add_middleware(AuthMiddleware)

app.include_router(auth_router)
app.include_router(general_router)
app.include_router(parcelles_router)
app.include_router(interventions_router)


@app.get("/login")
def login_page(request: Request):
    return templates.TemplateResponse("login.html", {"request": request})


@app.get("/")
def index(request: Request):
    return templates.TemplateResponse("index.html", {"request": request})
