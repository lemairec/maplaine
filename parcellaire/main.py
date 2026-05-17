from fastapi import FastAPI, Request
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
from fastapi.responses import RedirectResponse, JSONResponse
from starlette.middleware.base import BaseHTTPMiddleware
from routes.general import router as general_router
from routes.parcelles import router as parcelles_router
from routes.interventions import router as interventions_router
from routes.auth import router as auth_router
from routes.import_telepac import router as import_telepac_router
from auth import get_session_user_id

from migrations import run_migrations

app = FastAPI(title="Gestion Parcellaire")


@app.on_event("startup")
def startup():
    run_migrations()

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

# Ensure unhandled exceptions always return JSON (never HTML)
@app.exception_handler(Exception)
async def unhandled_exception_handler(request: Request, exc: Exception):
    import traceback
    return JSONResponse(
        status_code=500,
        content={"detail": f"{type(exc).__name__}: {exc}"},
    )

app.include_router(auth_router)
app.include_router(general_router)
app.include_router(parcelles_router)
app.include_router(interventions_router)
app.include_router(import_telepac_router)


@app.get("/login")
def login_page(request: Request):
    return templates.TemplateResponse("login.html", {"request": request})


@app.get("/")
def index(request: Request):
    return templates.TemplateResponse("index.html", {"request": request})

@app.get("/interventions")
def interventions_page(request: Request):
    return templates.TemplateResponse("interventions.html", {"request": request})


@app.get("/cahier-parcellaire")
def cahier_parcellaire_page(request: Request):
    return templates.TemplateResponse("cahier_parcellaire.html", {"request": request})
