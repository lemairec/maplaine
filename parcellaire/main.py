from fastapi import FastAPI, Request
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
from routes.general import router as general_router
from routes.parcelles import router as parcelles_router
from routes.interventions import router as interventions_router

app = FastAPI(title="Gestion Parcellaire")

app.mount("/static", StaticFiles(directory="static"), name="static")
templates = Jinja2Templates(directory="templates")

app.include_router(general_router)
app.include_router(parcelles_router)
app.include_router(interventions_router)


@app.get("/")
def index(request: Request):
    return templates.TemplateResponse("index.html", {"request": request})
