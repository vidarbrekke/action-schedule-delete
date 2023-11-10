# db_setup.py

import sqlite3
import os
import logging

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Database configuration
DATABASE_PATH = os.path.join("data", "trading_app.db")
SQL_CREATE_STOCKS_TABLE = """
    CREATE TABLE IF NOT EXISTS stocks (
        id INTEGER PRIMARY KEY,
        symbol TEXT NOT NULL UNIQUE,
        name TEXT
    );
"""
SQL_CREATE_PRICES_TABLE = """
    CREATE TABLE IF NOT EXISTS prices (
        id INTEGER PRIMARY KEY,
        stock_id INTEGER NOT NULL,
        date TEXT NOT NULL,
        open REAL NOT NULL,
        high REAL NOT NULL,
        low REAL NOT NULL,
        close REAL NOT NULL,
        volume INTEGER NOT NULL,
        UNIQUE(stock_id, date),
        FOREIGN KEY (stock_id) REFERENCES stocks (id)
    );
"""

def create_connection(db_file):
    """Create a database connection to the SQLite database specified by db_file"""
    try:
        conn = sqlite3.connect(db_file)
        conn.execute("PRAGMA foreign_keys = ON;")  # Enforce foreign key constraints
        return conn
    except sqlite3.Error as e:
        logger.error(f"Database connection error: {e}")
        return None

def create_table(conn, create_table_sql):
    """Create a table from the create_table_sql statement"""
    try:
        c = conn.cursor()
        c.execute(create_table_sql)
    except sqlite3.Error as e:
        logger.error(f"Error creating table: {e}")

def check_tables_exist(conn, table_names):
    """Check if the specified tables already exist in the database"""
    try:
        cur = conn.cursor()
        cur.execute("SELECT name FROM sqlite_master WHERE type='table';")
        existing_tables = {table[0] for table in cur.fetchall()}
        return all(table in existing_tables for table in table_names)
    except sqlite3.Error as e:
        logger.error(f"Error checking tables: {e}")
        return False

def print_tables_in_db(conn):
    """Print the names of all tables in the database"""
    try:
        cur = conn.cursor()
        cur.execute("SELECT name FROM sqlite_master WHERE type='table';")
        tables = cur.fetchall()
        logger.info(f"Tables in the database: {', '.join([table[0] for table in tables])}")
    except sqlite3.Error as e:
        logger.error(f"Error printing table names: {e}")

def initialize_database():
    """Initialize the database with required tables"""
    conn = create_connection(DATABASE_PATH)
    if conn is not None:
        if not check_tables_exist(conn, ['stocks', 'prices']):
            create_table(conn, SQL_CREATE_STOCKS_TABLE)
            create_table(conn, SQL_CREATE_PRICES_TABLE)
            logger.info("Tables created successfully.")
            print("Created tables: stocks, prices")
        else:
            logger.info("Existing tables found. No need to create tables.")
            print("Tables already exist: stocks, prices")
        print_tables_in_db(conn)
        conn.close()
        print(f"Database path: {DATABASE_PATH}")
    else:
        logger.error("Unable to create or connect to the database.")

if __name__ == '__main__':
    initialize_database()
