# reads data from the database

import sqlite3
import os
import pandas as pd

def create_connection(testing=False):
    """Create a database connection to the SQLite database."""
    if testing:
        # Use an in-memory database for testing
        return sqlite3.connect(':memory:')

    script_dir = os.path.dirname(os.path.abspath(__file__))
    database_path = os.path.join(script_dir, "trading_app.db")
    return sqlite3.connect(database_path)

def fetch_data(symbol, start_date, end_date, testing=False):
    """
    Fetch stock data for a given symbol between specified start and end dates.
    :param symbol: Stock symbol
    :param start_date: Start date in 'YYYY-MM-DD' format
    :param end_date: End date in 'YYYY-MM-DD' format
    :param testing: Boolean flag to use in-memory database for testing
    :return: DataFrame with the fetched data
    """
    conn = create_connection(testing)
    query = """
        SELECT p.date, p.open, p.high, p.low, p.close, p.volume
        FROM prices p
        JOIN stocks s ON p.stock_id = s.id
        WHERE s.symbol = ? AND p.date BETWEEN ? AND ?
        ORDER BY p.date;
    """

    df = pd.read_sql_query(query, conn, params=(symbol, start_date, end_date))
    conn.close()
    return df

if __name__ == "__main__":
    # Example usage
    symbol = "AMD"
    start_date = "2023-01-01"
    end_date = "2023-01-31"
    data = fetch_data(symbol, start_date, end_date)
    print(data.head())  # Print the first few rows of the data
