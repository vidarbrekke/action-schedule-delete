import sqlite3
import sys
import os

def main(ticker):
    # Update this path to point to the correct database file inside the 'data' directory
    database_path = "./data/trading_app.db"
    absolute_db_path = os.path.abspath(database_path)

    # Print the absolute path of the database for verification
    print(f"Connecting to database at {absolute_db_path}")
    
    # Connect to the SQLite database
    conn = sqlite3.connect(database_path)
    cursor = conn.cursor()

    # Check if the ticker exists in the 'stocks' table
    print(f"Checking for ticker {ticker} in 'stocks' table...")
    cursor.execute("SELECT * FROM stocks WHERE symbol = ?", (ticker,))
    stock = cursor.fetchone()
    if stock:
        print(f"Ticker {ticker} exists in 'stocks' table with ID: {stock[0]}")
    else:
        print(f"Ticker {ticker} does not exist in 'stocks' table.")

    # Print out the structure of the 'stocks' table
    print("\nFetching structure of 'stocks' table...")
    cursor.execute("PRAGMA table_info(stocks);")
    stocks_info = cursor.fetchall()
    print("Structure of 'stocks' table:")
    for info in stocks_info:
        print(info)

    # Print out the structure of the 'prices' table
    print("\nFetching structure of 'prices' table...")
    cursor.execute("PRAGMA table_info(prices);")
    prices_info = cursor.fetchall()
    print("Structure of 'prices' table:")
    for info in prices_info:
        print(info)

    # Close the database connection
    conn.close()

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Usage: python db_check.py <ticker_symbol>")
        sys.exit(1)
    ticker_symbol = sys.argv[1]
    main(ticker_symbol)
