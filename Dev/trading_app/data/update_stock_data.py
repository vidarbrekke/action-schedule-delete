import os
import sys
import yfinance as yf
import sqlite3
import pandas as pd
from datetime import datetime

def fetch_data_from_yfinance(ticker, last_update_date):
    period = "5y" if last_update_date is None else "max"
    stock_data = yf.download(ticker, start=last_update_date, period=period)
    return stock_data

def update_database_with_data(conn, ticker_id, stock_data):
    insert_sql = """
        INSERT INTO prices (stock_id, date, open, high, low, close, volume)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON CONFLICT(stock_id, date) DO UPDATE SET
        open=excluded.open,
        high=excluded.high,
        low=excluded.low,
        close=excluded.close,
        volume=excluded.volume;
    """
    stock_data.reset_index(inplace=True)
    for _, row in stock_data.iterrows():
        # Debugging print statements
        print(f"Ticker ID: {ticker_id}, Type: {type(ticker_id)}")
        print(f"Row data: {row}, Type: {type(row)}")
        print(f"Row 'Date': {row['Date']}, Type: {type(row['Date'])}")
        print(f"Row 'Open': {row['Open']}, Type: {type(row['Open'])}")
        print(f"Row 'High': {row['High']}, Type: {type(row['High'])}")
        print(f"Row 'Low': {row['Low']}, Type: {type(row['Low'])}")
        print(f"Row 'Close': {row['Close']}, Type: {type(row['Close'])}")
        print(f"Row 'Volume': {row['Volume']}, Type: {type(row['Volume'])}")

        try:
            conn.execute(insert_sql, (
                ticker_id,
                row['Date'].strftime('%Y-%m-%d'),
                row['Open'],
                row['High'],
                row['Low'],
                row['Close'],
                row['Volume']
            ))
        except Exception as e:
            print(f"Error inserting data: {e}")
            break

def main(ticker):
    # Get the directory of the current script
    script_dir = os.path.dirname(os.path.abspath(__file__))
    # Join the directory path with the database filename
    database_path = os.path.join(script_dir, "./trading_app.db") 

    print(f"Connecting to database at {database_path}")
    conn = sqlite3.connect(database_path)
    last_update_query = """
        SELECT MAX(date) FROM prices WHERE stock_id = (
            SELECT id FROM stocks WHERE symbol = ?
        )
    """
    cursor = conn.cursor()
    print(f"Executing last update query for ticker: {ticker}")
    cursor.execute(last_update_query, (ticker,))
    last_update_date = cursor.fetchone()[0]
    print(f"Last update date for {ticker}: {last_update_date}")

    stock_data = fetch_data_from_yfinance(ticker, last_update_date)
    print(f"Fetched data for {ticker}: {stock_data}")

    print(f"Retrieving ticker ID for {ticker}")
    cursor.execute("SELECT id FROM stocks WHERE symbol = ?", (ticker,))
    ticker_id = cursor.fetchone()
    if ticker_id is not None:
        ticker_id = ticker_id[0]
    else:
        print(f"Ticker {ticker} not found in database. Inserting new record.")
        conn.execute("INSERT INTO stocks (symbol) VALUES (?)", (ticker,))
        ticker_id = cursor.lastrowid

    print(f"Updating database with data for ticker ID: {ticker_id}")
    update_database_with_data(conn, ticker_id, stock_data)

    conn.commit()
    conn.close()
    print("Database update complete.")

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python update_stock_data.py <ticker>")
        sys.exit(1)
    ticker = sys.argv[1]
    print(f"Starting update process for {ticker}")
    main(ticker)
