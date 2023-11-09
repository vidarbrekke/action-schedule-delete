import sqlite3
import pandas as pd

def get_stock_data_from_db(ticker, db_path='data/stock_data.db'):
    with sqlite3.connect(db_path) as conn:
        query = f"SELECT DATE, CLOSE FROM stock_data WHERE TICKER = '{ticker}' ORDER BY DATE"
        stock_data = pd.read_sql_query(query, conn, parse_dates=['DATE'])
        stock_data.set_index('DATE', inplace=True)
    return stock_data

# Add more database-related functions as needed
