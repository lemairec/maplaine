from sqlalchemy import inspect, text
from database import engine


_MIGRATIONS = [
    ("parcelle", "import_ref", "ALTER TABLE parcelle ADD COLUMN import_ref VARCHAR(45)")
]


def run_migrations():
    inspector = inspect(engine)
    with engine.connect() as conn:
        for table, column, sql in _MIGRATIONS:
            cols = {c["name"] for c in inspector.get_columns(table)}
            if column not in cols:
                conn.execute(text(sql))
                conn.commit()
