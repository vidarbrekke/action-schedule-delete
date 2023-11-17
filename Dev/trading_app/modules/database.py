from sqlalchemy import create_engine, Column, Integer, Float, String, DateTime
from sqlalchemy.orm import declarative_base, sessionmaker
from sqlalchemy.engine.reflection import Inspector

DATABASE_URI = 'sqlite:///trading_simulator.db'
Base = declarative_base()

class StockData(Base):
    __tablename__ = 'stock_data'
    id = Column(Integer, primary_key=True)
    ticker = Column(String(10), nullable=False)
    date = Column(DateTime, nullable=False)
    open = Column(Float)
    high = Column(Float)
    low = Column(Float)
    close = Column(Float)
    volume = Column(Float)

engine = create_engine(DATABASE_URI)
Session = sessionmaker(bind=engine)

def init_db():
    Base.metadata.create_all(engine)
    print("Database initialized successfully.")
    
    # Use Inspector to list tables
    inspector = Inspector.from_engine(engine)
    print("Existing tables in the database:", inspector.get_table_names())

if __name__ == "__main__":
    init_db()
